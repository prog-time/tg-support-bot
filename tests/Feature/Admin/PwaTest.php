<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_service_worker_route_serves_versioned_js_publicly(): void
    {
        // No actingAs — the SW must be fetchable outside the session.
        $response = $this->get('/admin/sw.js');

        $response->assertOk();
        $this->assertStringContainsString('application/javascript', (string) $response->headers->get('content-type'));
        $this->assertSame('/admin/', $response->headers->get('Service-Worker-Allowed'));

        $body = $response->getContent();
        $this->assertStringContainsString("const CACHE = 'admin-pwa-", $body);
        $this->assertStringContainsString('/offline.html', $body);
        $this->assertStringContainsString("req.mode === 'navigate'", $body);
        $this->assertStringContainsString("addEventListener('notificationclick'", $body);
        $this->assertStringContainsString("addEventListener('push'", $body);
        $this->assertStringContainsString('self.registration.showNotification', $body);
    }

    public function test_manifest_route_serves_valid_manifest_publicly(): void
    {
        $response = $this->get('/admin/manifest.webmanifest');

        $response->assertOk();
        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('content-type'));

        $manifest = json_decode((string) $response->getContent(), true);
        $this->assertSame('/admin/chats', $manifest['start_url']);
        $this->assertSame('/admin/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['theme_color']);
        $this->assertNotEmpty($manifest['background_color']);

        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);

        $purposes = array_column($manifest['icons'], 'purpose');
        $this->assertContains('any', $purposes);
        $this->assertContains('maskable', $purposes);
    }

    public function test_pwa_static_assets_exist(): void
    {
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('icons/icon-192.png'));
        $this->assertFileExists(public_path('icons/icon-512.png'));
        $this->assertFileExists(public_path('icons/icon-maskable-192.png'));
        $this->assertFileExists(public_path('icons/icon-maskable-512.png'));
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
    }

    public function test_admin_layouts_include_pwa_head_tags(): void
    {
        foreach (['admin-chat', 'admin-settings'] as $layout) {
            $html = (string) file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));
            $this->assertStringContainsString('rel="manifest"', $html, "{$layout} missing manifest link");
            $this->assertStringContainsString('name="theme-color"', $html, "{$layout} missing theme-color");
            $this->assertStringContainsString('apple-touch-icon', $html, "{$layout} missing apple-touch-icon");
        }
    }

    public function test_chat_workspace_shows_notifications_through_the_service_worker(): void
    {
        // Regression: a bare `new Notification(...)` call is unsupported by iOS
        // Safari (even for an installed home-screen PWA) — notifications must be
        // routed through ServiceWorkerRegistration.showNotification() instead.
        $html = (string) file_get_contents(resource_path('views/livewire/chat/conversation-page.blade.php'));

        $this->assertStringContainsString('serviceWorker.ready', $html);
        $this->assertStringContainsString('.showNotification(', $html);
        $this->assertStringNotContainsString('new Notification(', $html);
    }
}
