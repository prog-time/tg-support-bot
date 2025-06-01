<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Controllers\TelegramBotController;
use Illuminate\Http\Request;

class TelegramBotControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the constructor of TelegramBotController.
     *
     * @return void
     */
    public function testConstructor()
    {
        $request = Request::create('/webhook', 'POST', [
            'typeSource' => 'private',
            'messageThreadId' => 12345,
            'pinnedMessageStatus' => false
        ]);

        $controller = new TelegramBotController($request);

        $this->assertEquals('telegram', $controller->platform);
    }

    /**
     * Test the isSupergroup method.
     *
     * @return void
     */
    public function testIsSupergroup()
    {
        $request = Request::create('/webhook', 'POST', [
            'typeSource' => 'supergroup',
            'messageThreadId' => 12345,
            'pinnedMessageStatus' => false
        ]);

        $controller = new TelegramBotController($request);

        $this->assertTrue($controller->isSupergroup());
    }

    /**
     * Test the checkBotQuery method.
     *
     * @return void
     */
    public function testCheckBotQuery()
    {
        $request = Request::create('/webhook', 'POST', [
            'typeSource' => 'private',
            'messageThreadId' => 12345,
            'pinnedMessageStatus' => true
        ]);

        $controller = new TelegramBotController($request);

        $this->expectException(\Exception::class);
        $controller->checkBotQuery();
    }
}