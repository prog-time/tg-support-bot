<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Email\Services;

use App\Modules\Email\Services\EmailIgnoreListMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsSettings;

/**
 * Covers App\Modules\Email\Services\EmailIgnoreListMatcher::isIgnored().
 */
class EmailIgnoreListMatcherTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSettings;

    public function test_returns_false_when_list_is_empty(): void
    {
        $matcher = app(EmailIgnoreListMatcher::class);

        $this->assertFalse($matcher->isIgnored('someone@example.com'));
    }

    public function test_matches_full_address_case_insensitively(): void
    {
        $this->seedSetting('email.ignored_addresses', ['Newsletter@Example.com']);

        $matcher = app(EmailIgnoreListMatcher::class);

        $this->assertTrue($matcher->isIgnored('newsletter@example.com'));
        $this->assertTrue($matcher->isIgnored('NEWSLETTER@EXAMPLE.COM'));
        $this->assertFalse($matcher->isIgnored('other@example.com'));
    }

    public function test_matches_entire_domain_via_at_prefix(): void
    {
        $this->seedSetting('email.ignored_addresses', ['@promo.example.com']);

        $matcher = app(EmailIgnoreListMatcher::class);

        $this->assertTrue($matcher->isIgnored('offers@promo.example.com'));
        $this->assertTrue($matcher->isIgnored('news@promo.example.com'));
        $this->assertFalse($matcher->isIgnored('someone@example.com'));
    }

    public function test_ignores_blank_and_whitespace_only_entries(): void
    {
        $this->seedSetting('email.ignored_addresses', ['', '   ', 'blocked@example.com']);

        $matcher = app(EmailIgnoreListMatcher::class);

        $this->assertTrue($matcher->isIgnored('blocked@example.com'));
        $this->assertFalse($matcher->isIgnored(''));
    }
}
