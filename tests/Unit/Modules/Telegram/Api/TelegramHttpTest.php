<?php

namespace Tests\Unit\Modules\Telegram\Api;

use App\Modules\Telegram\Api\TelegramHttp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramHttpTest extends TestCase
{
    public function test_options_are_empty_by_default(): void
    {
        config(['traffic_source.telegram.proxy' => null, 'traffic_source.telegram.force_ipv4' => false]);

        $this->assertSame([], TelegramHttp::options());
    }

    public function test_options_include_proxy_when_configured(): void
    {
        config(['traffic_source.telegram.proxy' => 'socks5h://user:pass@10.0.0.1:1080']);

        $this->assertSame('socks5h://user:pass@10.0.0.1:1080', TelegramHttp::options()['proxy']);
    }

    public function test_blank_proxy_is_ignored(): void
    {
        config(['traffic_source.telegram.proxy' => '   ']);

        $this->assertArrayNotHasKey('proxy', TelegramHttp::options());
    }

    public function test_options_combine_proxy_and_force_ipv4(): void
    {
        config(['traffic_source.telegram.proxy' => 'http://10.0.0.1:3128', 'traffic_source.telegram.force_ipv4' => true]);

        $options = TelegramHttp::options();

        $this->assertSame('http://10.0.0.1:3128', $options['proxy']);
        $this->assertSame('v4', $options['force_ip_resolve']);
    }

    public function test_client_carries_proxy_option(): void
    {
        config(['traffic_source.telegram.proxy' => 'http://10.0.0.1:3128']);

        $this->assertSame('http://10.0.0.1:3128', TelegramHttp::client()->getOptions()['proxy']);
    }

    public function test_client_for_telegram_url_carries_proxy(): void
    {
        config(['traffic_source.telegram.proxy' => 'http://10.0.0.1:3128']);

        $client = TelegramHttp::clientFor('https://api.telegram.org/file/botTOKEN/photos/a.jpg');

        $this->assertSame('http://10.0.0.1:3128', $client->getOptions()['proxy']);
    }

    public function test_client_for_other_host_has_no_proxy(): void
    {
        config(['traffic_source.telegram.proxy' => 'http://10.0.0.1:3128', 'traffic_source.telegram.force_ipv4' => true]);

        $options = TelegramHttp::clientFor('https://cdn.max.ru/files/a.jpg')->getOptions();

        $this->assertArrayNotHasKey('proxy', $options);
        $this->assertArrayNotHasKey('force_ip_resolve', $options);
    }

    public function test_is_telegram_url(): void
    {
        $this->assertTrue(TelegramHttp::isTelegramUrl('https://api.telegram.org/botX/getMe'));
        $this->assertTrue(TelegramHttp::isTelegramUrl('https://API.Telegram.org/botX/getMe'));
        $this->assertFalse(TelegramHttp::isTelegramUrl('https://api.telegram.org.evil.com/botX/getMe'));
        $this->assertFalse(TelegramHttp::isTelegramUrl('not a url'));
    }

    public function test_request_is_sent_with_proxy_option(): void
    {
        config(['traffic_source.telegram.proxy' => 'socks5h://10.0.0.1:1080']);
        $sentOptions = [];

        Http::fake(function ($request, array $options) use (&$sentOptions) {
            $sentOptions = $options;

            return Http::response(['ok' => true]);
        });

        TelegramHttp::client()->get('https://api.telegram.org/botX/getMe');

        $this->assertSame('socks5h://10.0.0.1:1080', $sentOptions['proxy'] ?? null);
    }
}
