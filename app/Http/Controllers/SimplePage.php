<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class SimplePage
{
    /**
     * @return View
     */
    public function index(): View
    {
        if (config('app.url') === 'https://tg-support-bot.ru') {
            return view('site.home');
        } else {
            return view('site.home_client_version');
        }
    }

    public function liveChatPromo(): View
    {
        return view('site.live_chat_promo');
    }
}
