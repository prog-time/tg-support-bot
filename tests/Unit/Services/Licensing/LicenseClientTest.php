<?php

namespace Tests\Unit\Services\Licensing;

use App\Services\Licensing\LicenseClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for LicenseClient.
 *
 * Resolves the modules a license key grants by calling the License Server list
 * endpoint and (when present) verifying the Ed25519-signed response. All HTTP is
 * faked — no real server calls.
 */
class LicenseClientTest extends TestCase
{
    private const URL = 'https://licenses.iliya-code.ru/api/license/products';

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.server_url' => 'https://licenses.iliya-code.ru']);
    }

    /** base64url-encode without padding, as the License Server does. */
    private function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public function test_products_parses_plain_unsigned_body(): void
    {
        Http::fake([self::URL => Http::response([
            'products' => [
                ['product' => 'avito', 'name' => 'Avito', 'valid_until' => '2026-12-31', 'status' => 'active'],
            ],
        ], 200)]);

        $result = (new LicenseClient())->products('KEY');

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['products']);
        $this->assertSame('avito', $result['products'][0]['product']);
        $this->assertSame('2026-12-31', $result['products'][0]['valid_until']);
        $this->assertSame('active', $result['products'][0]['status']);
    }

    public function test_products_verifies_signed_token(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($keypair);
        $public = sodium_crypto_sign_publickey($keypair);
        config(['license.public_key' => base64_encode($public)]);

        $jsonBody = json_encode(['products' => [
            ['product' => 'avito', 'name' => 'Avito', 'valid_until' => null, 'status' => 'active'],
        ]]);
        $bodyB64 = $this->b64url($jsonBody);
        $token = $bodyB64 . '.' . $this->b64url(sodium_crypto_sign_detached($bodyB64, $secret));

        Http::fake([self::URL => Http::response(['token' => $token], 200)]);

        $result = (new LicenseClient())->products('KEY');

        $this->assertTrue($result['success']);
        $this->assertSame('Avito', $result['products'][0]['name']);
        $this->assertNull($result['products'][0]['valid_until']);
    }

    public function test_products_reports_invalid_key_from_signed_token(): void
    {
        // Mirrors the real server: a signed token whose payload is valid=false.
        $keypair = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($keypair);
        $public = sodium_crypto_sign_publickey($keypair);
        config(['license.public_key' => base64_encode($public)]);

        $jsonBody = json_encode(['valid' => false, 'reason' => 'not_found', 'products' => []]);
        $bodyB64 = $this->b64url($jsonBody);
        $token = $bodyB64 . '.' . $this->b64url(sodium_crypto_sign_detached($bodyB64, $secret));

        Http::fake([self::URL => Http::response(['token' => $token], 200)]);

        $result = (new LicenseClient())->products('BADKEY');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('не найден', $result['message']);
        $this->assertEmpty($result['products']);
    }

    public function test_products_rejects_tampered_signature(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        $public = sodium_crypto_sign_publickey($keypair);
        config(['license.public_key' => base64_encode($public)]);

        // Body signed with a DIFFERENT key → signature must fail verification.
        $otherSecret = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        $bodyB64 = $this->b64url(json_encode(['products' => []]));
        $token = $bodyB64 . '.' . $this->b64url(sodium_crypto_sign_detached($bodyB64, $otherSecret));

        Http::fake([self::URL => Http::response(['token' => $token], 200)]);

        $result = (new LicenseClient())->products('KEY');

        $this->assertFalse($result['success']);
        $this->assertEmpty($result['products']);
    }

    public function test_products_fails_on_http_error(): void
    {
        Http::fake([self::URL => Http::response(['error' => 'boom'], 500)]);

        $result = (new LicenseClient())->products('KEY');

        $this->assertFalse($result['success']);
    }

    public function test_products_fails_without_key(): void
    {
        Http::fake();

        $result = (new LicenseClient())->products('');

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_products_defaults_name_to_slug(): void
    {
        Http::fake([self::URL => Http::response([
            'products' => [['product' => 'avito', 'status' => 'active']],
        ], 200)]);

        $result = (new LicenseClient())->products('KEY');

        $this->assertSame('avito', $result['products'][0]['name']);
        $this->assertNull($result['products'][0]['valid_until']);
    }
}
