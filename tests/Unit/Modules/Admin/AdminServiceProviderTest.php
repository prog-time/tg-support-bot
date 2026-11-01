<?php

namespace Tests\Unit\Modules\Admin;

use App\Livewire\Settings\AiFaqFormPage;
use App\Livewire\Settings\AiFaqPage;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminServiceProviderTest extends TestCase
{
    public function test_registers_ai_faq_list_route(): void
    {
        $route = Route::getRoutes()->getByName('admin.settings.ai.faq');

        $this->assertNotNull($route);
        $this->assertSame('admin/settings/ai/faq', $route->uri());
        $this->assertSame(AiFaqPage::class, $route->getAction('controller'));
    }

    public function test_registers_ai_faq_create_route(): void
    {
        $route = Route::getRoutes()->getByName('admin.settings.ai.faq.create');

        $this->assertNotNull($route);
        $this->assertSame('admin/settings/ai/faq/create', $route->uri());
        $this->assertSame(AiFaqFormPage::class, $route->getAction('controller'));
    }

    public function test_ai_faq_edit_route_accepts_only_numeric_entry(): void
    {
        $route = Route::getRoutes()->getByName('admin.settings.ai.faq.edit');

        $this->assertNotNull($route);
        $this->assertSame('admin/settings/ai/faq/{entry}/edit', $route->uri());
        $this->assertSame(AiFaqFormPage::class, $route->getAction('controller'));
        $this->assertSame('[0-9]+', $route->wheres['entry']);
    }
}
