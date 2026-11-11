<?php

namespace App\Modules\Telegram\Api;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client factory for Telegram Bot API traffic.
 *
 * Applies the infrastructure transport options from config/traffic_source.php
 * (TELEGRAM_PROXY, TELEGRAM_FORCE_IPV4) to requests that go to api.telegram.org
 * and leaves every other host untouched.
 */
class TelegramHttp
{
    public const API_HOST = 'api.telegram.org';

    /**
     * Build a pending request configured for the Telegram Bot API.
     *
     * @return PendingRequest
     */
    public static function client(): PendingRequest
    {
        return Http::withOptions(self::options());
    }

    /**
     * Build a pending request for an arbitrary URL: Telegram transport options
     * are applied only when the URL points at the Telegram Bot API host.
     *
     * @param string $url Absolute URL that will be requested
     *
     * @return PendingRequest
     */
    public static function clientFor(string $url): PendingRequest
    {
        return self::isTelegramUrl($url) ? self::client() : Http::withOptions([]);
    }

    /**
     * Guzzle options for Telegram requests built from config.
     *
     * @return array<string, mixed>
     */
    public static function options(): array
    {
        $options = [];

        $proxy = trim((string) config('traffic_source.telegram.proxy'));
        if ($proxy !== '') {
            $options['proxy'] = $proxy;
        }

        if (config('traffic_source.telegram.force_ipv4')) {
            $options['force_ip_resolve'] = 'v4';
        }

        return $options;
    }

    /**
     * Whether the URL targets the Telegram Bot API host.
     *
     * @param string $url
     *
     * @return bool
     */
    public static function isTelegramUrl(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST)) === self::API_HOST;
    }
}
