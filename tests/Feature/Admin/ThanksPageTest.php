<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the «Поблагодарить автора» screen (/admin/thanks).
 *
 * The access expectations are the point of these tests: the page lives outside
 * the /admin/settings prefix precisely so managers can reach it, unlike every
 * settings screen except «Основные».
 */
class ThanksPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_thanks_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get(route('admin.thanks'))
            ->assertOk()
            ->assertSee('Спасибо, что пользуетесь');
    }

    public function test_manager_can_open_the_thanks_page(): void
    {
        // Regression guard: managers are redirected away from settings screens
        // by EnsureSettingsAccess. This page must NOT inherit that behaviour —
        // if someone moves the route under /admin/settings, this fails.
        $this->actingAs(User::factory()->manager()->create());

        $this->get(route('admin.thanks'))
            ->assertOk()
            ->assertSee('Спасибо, что пользуетесь');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.thanks'))
            ->assertRedirect(route('login'));
    }

    public function test_page_lists_the_configured_support_links(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get(route('admin.thanks'))
            ->assertOk()
            ->assertSee('https://github.com/prog-time/tg-support-bot', false)
            ->assertSee('https://t.me/pt_tg_support', false)
            ->assertSee('https://github.com/prog-time', false);
    }

    public function test_donation_card_is_shown_with_its_link(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get(route('admin.thanks'))
            ->assertOk()
            ->assertSee('Поддержать рублём')
            ->assertSee('https://godonate.ru/@ilya-lyashchuk', false);
    }

    public function test_a_card_without_a_link_is_not_rendered(): void
    {
        // Guards the @continue in the view: a card whose url is blank must not
        // render as a dead button. Checked through the shared markup — every
        // card carries an href, so an empty one would surface as href="".
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get(route('admin.thanks'))
            ->assertOk()
            ->assertDontSee('href=""', false);
    }
}
