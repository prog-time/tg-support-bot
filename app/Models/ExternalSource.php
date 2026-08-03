<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * @property int                     $id
 * @property string                  $name
 * @property string                  $type
 * @property string                  $webhook_url
 * @property int                     $user_id
 * @property array<int, string>|null $allowed_ips
 * @property string|null             $public_key
 * @property-read User $user
 */
class ExternalSource extends Model
{
    use HasFactory;

    /** Generic bearer-token API source (webhook_url + access token). */
    public const TYPE_API = 'api';

    /** JS live-chat widget source (public_key only, no bearer token). */
    public const TYPE_WIDGET = 'widget';

    protected $fillable = [
        'name',
        'type',
        'webhook_url',
        'user_id',
        'allowed_ips',
        'public_key',
    ];

    protected $casts = [
        'allowed_ips' => 'array',
    ];

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this source is a JS live-chat widget source.
     *
     * @return bool
     */
    public function isWidget(): bool
    {
        return $this->type === self::TYPE_WIDGET;
    }

    /**
     * Whether this source is a generic bearer-token API source.
     *
     * @return bool
     */
    public function isApi(): bool
    {
        return $this->type !== self::TYPE_WIDGET;
    }

    /**
     * Whether the given request IP is allowed for this source.
     *
     * Delegates to isRequestAllowed() using only the IP address.
     * An empty/NULL allowlist means no restriction — any IP is allowed.
     *
     * @param string|null $ip
     *
     * @return bool
     */
    public function isIpAllowed(?string $ip): bool
    {
        $allowed = array_values(array_filter(array_map('trim', $this->allowed_ips ?? [])));

        if (empty($allowed)) {
            return true;
        }

        return $ip !== null && in_array($ip, $allowed, true);
    }

    /**
     * Whether the given HTTP request is allowed for this source.
     *
     * Checks each entry in the allowed_ips list. Entries can be:
     *  - A valid IP address (compared to $request->ip())
     *  - A domain string (compared to the host from the Origin or Referer header)
     *    - Entries with a leading "*." are wildcard patterns matching exactly one
     *      subdomain level: "*.example.com" matches "foo.example.com" but not
     *      "example.com" or "foo.bar.example.com"
     *    - Non-wildcard entries require a case-insensitive exact host match
     *
     * An empty/NULL allowlist means no restriction — any request is allowed.
     *
     * @param Request $request
     *
     * @return bool
     */
    public function isRequestAllowed(Request $request): bool
    {
        $allowed = array_values(array_filter(array_map('trim', $this->allowed_ips ?? [])));

        if (empty($allowed)) {
            return true;
        }

        $requestIp = $request->ip();

        $originHost = null;
        $origin = $request->header('Origin');
        if ($origin) {
            $parsed = parse_url($origin);
            $originHost = isset($parsed['host']) ? strtolower($parsed['host']) : null;
        }

        if ($originHost === null) {
            $referer = $request->header('Referer');
            if ($referer) {
                $parsed = parse_url($referer);
                $originHost = isset($parsed['host']) ? strtolower($parsed['host']) : null;
            }
        }

        foreach ($allowed as $entry) {
            if (filter_var($entry, FILTER_VALIDATE_IP)) {
                if ($requestIp !== null && $requestIp === $entry) {
                    return true;
                }
            } else {
                if ($originHost === null) {
                    continue;
                }

                if (str_starts_with($entry, '*.')) {
                    $base = strtolower(substr($entry, 2));
                    $suffix = '.' . $base;
                    if (str_ends_with($originHost, $suffix)) {
                        $sub = substr($originHost, 0, strlen($originHost) - strlen($suffix));
                        if ($sub !== '' && ! str_contains($sub, '.')) {
                            return true;
                        }
                    }
                } else {
                    if ($originHost === strtolower($entry)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return HasMany
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(ExternalSourceAccessTokens::class, 'external_source_id');
    }

    /**
     * Return the single active access token record for this source, or null.
     *
     * @return ExternalSourceAccessTokens|null
     */
    public function activeToken(): ?ExternalSourceAccessTokens
    {
        /** @var ExternalSourceAccessTokens|null */
        return ExternalSourceAccessTokens::where('external_source_id', $this->id)
            ->where('active', true)
            ->first();
    }
}
