<?php

namespace App\Http\Controllers;

class SimplePage
{
    public function index()
    {
        if (config('app.url') === 'https://tg-support-bot.ru/') {
            return view('site.home');
        } else {
            return view('site.home_client_version');
        }
    }
}