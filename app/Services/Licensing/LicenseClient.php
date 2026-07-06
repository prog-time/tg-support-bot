<?php

declare(strict_types=1);

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Core-level client for the License Server.
 *
 * Resolves which paid modules a single license key grants by calling the list
 * endpoint (POST {server_url}/api/license/products). The server replies with a
 * signed (Ed25519) token — verified here with config('license.public_key') so a
 * spoofed server cannot forge the list — whose payload carries a `products`
 * array. For flexibility an unsigned `{ "products": [...] }` body is also
 * accepted (signed form recommended).
 *
 * Each product is normalised to: product (slug), name (display), valid_until
 * (ISO date|null) and status (free string, e.g. active|expired|inactive).
 *
 * The license key is never logged.
 */
class LicenseClient
{
    /**
     * Fetch the list of modules a license key grants.
     *
     * @param string $key The license key to look up.
     *
     * @return array{success: bool, message: string, products: array<int, array{product: string, name: string, valid_until: string|null, status: string}>}
     */
    public function products(string $key): array
    {
        if (trim($key) === '') {
            return $this->fail('Введите лицензионный ключ.');
        }

        $base = rtrim((string) config('license.server_url'), '/');

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('license.http_timeout', 10))
                ->post($base . '/api/license/products', [
                    'key' => $key,
                    'instance' => $this->instance(),
                ]);

            if (! $response->successful()) {
                Log::channel('app')->warning('LicenseClient: products request failed', [
                    'status' => $response->status(),
                ]);

                return $this->fail('Сервер лицензий вернул ошибку (HTTP ' . $response->status() . ').');
            }

            // Prefer the signed token; fall back to a plain products body.
            $token = (string) $response->json('token', '');
            $payload = $token !== '' ? $this->openToken($token) : $response->json();

            if ($token !== '' && $payload === null) {
                return $this->fail('Подпись ответа сервера лицензий недействительна.');
            }

            // The server marks an unknown/expired key with valid=false and an
            // empty products list — surface that as a clear error rather than an
            // empty table (so a bad key is not treated as "no modules").
            if (is_array($payload) && ($payload['valid'] ?? null) === false) {
                return $this->fail($this->reasonMessage((string) ($payload['reason'] ?? 'unknown')));
            }

            $rawProducts = is_array($payload) && isset($payload['products']) && is_array($payload['products'])
                ? $payload['products']
                : null;

            if ($rawProducts === null) {
                return $this->fail('Сервер лицензий не вернул список модулей.');
            }

            return [
                'success' => true,
                'message' => 'Лицензионный ключ проверен.',
                'products' => $this->normalize($rawProducts),
            ];
        } catch (Throwable) {
            return $this->fail('Не удалось связаться с сервером лицензий.');
        }
    }

    /**
     * Normalise raw product entries into a stable shape.
     *
     * @param array<int|string, mixed> $rawProducts
     *
     * @return array<int, array{product: string, name: string, valid_until: string|null, status: string}>
     */
    private function normalize(array $rawProducts): array
    {
        $products = [];

        foreach ($rawProducts as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $slug = (string) ($entry['product'] ?? '');
            $name = (string) ($entry['name'] ?? '');
            $validUntil = isset($entry['valid_until']) && $entry['valid_until'] !== ''
                ? (string) $entry['valid_until']
                : null;
            $status = (string) ($entry['status'] ?? 'unknown');

            $products[] = [
                'product' => $slug,
                'name' => $name !== '' ? $name : ($slug !== '' ? $slug : '—'),
                'valid_until' => $validUntil,
                'status' => $status !== '' ? $status : 'unknown',
            ];
        }

        return $products;
    }

    /**
     * Map a server "valid=false" reason to a human message.
     */
    private function reasonMessage(string $reason): string
    {
        return match ($reason) {
            'not_found' => 'Лицензионный ключ не найден.',
            'expired' => 'Срок действия лицензии истёк.',
            'revoked' => 'Лицензия отозвана.',
            'inactive' => 'Лицензия неактивна.',
            default => 'Лицензия недействительна (' . $reason . ').',
        };
    }

    /**
     * Decode + Ed25519 signature-verify a "{body}.{sig}" token. Null if missing,
     * malformed, or tampered. Mirrors the modules' LicenseGuard verification.
     *
     * @return array<string, mixed>|null
     */
    private function openToken(string $token): ?array
    {
        if (! str_contains($token, '.')) {
            return null;
        }

        [$body, $sig] = explode('.', $token, 2);

        $rawSig = $this->base64UrlDecode($sig);
        $public = base64_decode((string) config('license.public_key'), true);

        if ($rawSig === null || $public === false || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        if (! sodium_crypto_sign_verify_detached($rawSig, $body, $public)) {
            return null;
        }

        $json = $this->base64UrlDecode($body);

        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function base64UrlDecode(string $data): ?string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    /** Stable per-installation identifier sent for optional instance binding. */
    private function instance(): ?string
    {
        $url = (string) config('app.url', '');

        return $url !== '' ? $url : null;
    }

    /**
     * @return array{success: bool, message: string, products: array<int, never>}
     */
    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message, 'products' => []];
    }
}
