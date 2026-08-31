#!/bin/bash
# Smoke-test make init / docker/scripts/init-project.sh in an isolated temp dir.
# Does not touch the real project .env.
#
# Usage:
#   bash checks/scripts/init-smoke.sh
#   make init-smoke

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "🔍 Running init-project smoke tests..."

if [[ ! -f docker/scripts/init-project.sh ]]; then
  echo "❌ docker/scripts/init-project.sh not found"
  exit 1
fi

if [[ ! -f .env.example ]]; then
  echo "❌ .env.example not found"
  exit 1
fi

bash -n docker/scripts/init-project.sh
echo "✅ syntax OK"

TEST_ROOT="$(mktemp -d)"
cleanup() {
  rm -rf "$TEST_ROOT"
}
trap cleanup EXIT

mkdir -p "$TEST_ROOT/docker/scripts" "$TEST_ROOT/docker/nginx"
cp docker/scripts/init-project.sh "$TEST_ROOT/docker/scripts/"
cp .env.example "$TEST_ROOT/"
cp docker/nginx/default.http.conf.template "$TEST_ROOT/docker/nginx/"
if [[ -f docker/nginx/default.ssl.conf.template ]]; then
  cp docker/nginx/default.ssl.conf.template "$TEST_ROOT/docker/nginx/"
fi
chmod +x "$TEST_ROOT/docker/scripts/init-project.sh"

cd "$TEST_ROOT"

fail=0
pass() { echo "  ✅ $1"; }
bad() { echo "  ❌ $1"; fail=1; }

echo "➡️ Fresh init (defaults)"
# DB_DATABASE default, DB_USERNAME default, APP_ENV=1 (dev), MAIN_DOMAIN default, NGINX=1
printf '\n\n1\n\n1\n' | bash docker/scripts/init-project.sh >/tmp/init-smoke-1.out

grep -qE '^APP_KEY=base64:.+' .env && pass "APP_KEY generated" || bad "APP_KEY generated"
grep -qE '^DB_PASSWORD=.+' .env && pass "DB_PASSWORD generated" || bad "DB_PASSWORD generated"
grep -qE '^DB_DATABASE=tg_support$' .env && pass "DB_DATABASE default" || bad "DB_DATABASE default"
grep -qE '^DB_USERNAME=tg_support$' .env && pass "DB_USERNAME default" || bad "DB_USERNAME default"
grep -qE '^APP_ENV=local$' .env && pass "APP_ENV=local" || bad "APP_ENV=local"
grep -qE '^MAIN_DOMAIN=localhost$' .env && pass "MAIN_DOMAIN=localhost" || bad "MAIN_DOMAIN"
grep -qE '^NGINX_PORT=80:80$' .env && pass "NGINX_PORT" || bad "NGINX_PORT"
grep -qE '^NGINX_HTTPS_PORT=127\.0\.0\.1:8443:443$' .env && pass "NGINX_HTTPS stub" || bad "NGINX_HTTPS stub"
[[ -f docker/nginx/default.conf ]] && pass "nginx conf written" || bad "nginx conf written"
grep -q 'server_name localhost' docker/nginx/default.conf && pass "nginx server_name" || bad "nginx server_name"

KEY1="$(grep '^APP_KEY=' .env)"
PASS1="$(grep '^DB_PASSWORD=' .env)"

echo "➡️ Overwrite preserves secrets"
printf 'y\n' | bash docker/scripts/init-project.sh >/tmp/init-smoke-2.out

[[ "$(grep '^APP_KEY=' .env)" == "$KEY1" ]] && pass "APP_KEY preserved" || bad "APP_KEY preserved"
[[ "$(grep '^DB_PASSWORD=' .env)" == "$PASS1" ]] && pass "DB_PASSWORD preserved" || bad "DB_PASSWORD preserved"
grep -q 'already set' /tmp/init-smoke-2.out && pass "skip messages present" || bad "skip messages present"

echo "➡️ Partial fill only empty DB_USERNAME"
awk 'BEGIN{FS=OFS="="} /^DB_USERNAME=/{print "DB_USERNAME="; next} {print}' .env >.env.tmp
mv .env.tmp .env
printf 'y\ncustom_user\n' | bash docker/scripts/init-project.sh >/tmp/init-smoke-3.out

[[ "$(grep -c '^DB_USERNAME=' .env)" == "1" ]] && pass "single DB_USERNAME line" || bad "single DB_USERNAME line"
grep -qE '^DB_USERNAME=custom_user$' .env && pass "custom_user applied" || bad "custom_user applied (got $(grep '^DB_USERNAME=' .env))"
[[ "$(grep '^DB_PASSWORD=' .env)" == "$PASS1" ]] && pass "password intact after partial fill" || bad "password intact after partial fill"

echo "➡️ Cancel overwrite leaves .env untouched"
cp .env .env.before_cancel
set +e
printf 'n\n' | bash docker/scripts/init-project.sh >/tmp/init-smoke-4.out
cancel_rc=$?
set -e
[[ $cancel_rc -ne 0 ]] && pass "cancel exits non-zero" || bad "cancel should exit non-zero"
diff -q .env .env.before_cancel >/dev/null && pass "cancel leaves .env intact" || bad "cancel left .env intact"
grep -q 'Cancelled' /tmp/init-smoke-4.out && pass "cancel message" || bad "cancel message"

cd "$ROOT_DIR"

echo "➡️ Live compose config (optional)"
if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
  if [[ -f .env ]]; then
    docker compose config --quiet && pass "docker compose config" || bad "docker compose config"
  else
    echo "  ⚠️  no .env in project — skip compose config"
  fi
else
  echo "  ⚠️  Docker unavailable — skip compose config"
fi

if [[ $fail -ne 0 ]]; then
  echo "❌ init-project smoke failed"
  exit 1
fi

echo "✅ init-project smoke passed"
