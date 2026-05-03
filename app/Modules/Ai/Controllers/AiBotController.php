<?php

namespace App\Modules\Ai\Controllers;

use App\Modules\Ai\Jobs\AiBotWebhookJob;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AiBotController
{
    /**
     * Receive a Telegram webhook event from the AI bot.
     *
     * Parses the payload into a TelegramUpdateDto and dispatches
     * AiBotWebhookJob for async processing. Returns HTTP 200 immediately
     * so Telegram does not retry the delivery.
     *
     * @OA\Post(
     *     path="/api/ai-bot/webhook",
     *     summary="Receive AI bot Telegram webhook",
     *     tags={"AI Bot"},
     *     security={},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(type="object", description="Telegram Update object")
     *     ),
     *
     *     @OA\Response(response=200, description="Accepted"),
     *     @OA\Response(response=403, description="Forbidden — invalid secret token")
     * )
     *
     * @param Request $request
     *
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $updateDto = TelegramUpdateDto::fromRequest($request);

        if ($updateDto !== null) {
            AiBotWebhookJob::dispatch($updateDto);
        }

        return response()->noContent();
    }
}
