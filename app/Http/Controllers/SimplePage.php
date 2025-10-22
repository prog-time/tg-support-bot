<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class SimplePage
{
    /**
     * @return View
     */
    public function index()
    {
        if (config('app.url') === 'https://tg-support-bot.ru') {
            return view('site.home');
        } else {
            return view('site.home_client_version');
        }
    }
}
