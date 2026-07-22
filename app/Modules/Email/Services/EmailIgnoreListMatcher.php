<?php

declare(strict_types=1);

namespace App\Modules\Email\Services;

use App\Services\Settings\SettingsService;

/**
 * Checks an incoming email's sender address against the admin-configured
 * ignore list (`email.ignored_addresses`, edited on the Email integration
 * screen), so mailing-list/newsletter senders never spawn a support topic.
 *
 * Each stored entry is either a full address ("news@example.com") or a
 * "@domain.com" suffix that blocks every sender on that domain. Matching is
 * case-insensitive.
 */
class EmailIgnoreListMatcher
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * @return bool true when the sender address should be silently dropped.
     */
    public function isIgnored(string $address): bool
    {
        $address = strtolower(trim($address));

        if ($address === '') {
            return false;
        }

        foreach ($this->entries() as $entry) {
            if (str_starts_with($entry, '@')) {
                if (str_ends_with($address, $entry)) {
                    return true;
                }

                continue;
            }

            if ($address === $entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string> lowercased, trimmed, non-empty entries.
     */
    private function entries(): array
    {
        $raw = $this->settings->get('email.ignored_addresses') ?? [];

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $entry): string => strtolower(trim((string) $entry)),
            $raw,
        ), static fn (string $entry): bool => $entry !== ''));
    }
}
