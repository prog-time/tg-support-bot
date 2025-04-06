<?php

namespace App\Exceptions;

use Exception;
use ProgTime\TgLogger\TgLogger;
use Throwable;

class TgBotException extends Exception
{
    /**
     * Render the exception as an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        TgLogger::sendLog($exception->getMessage(), 'error');
        TgLogger::sendLog($request->getContent(), 'debug');
    }
}
