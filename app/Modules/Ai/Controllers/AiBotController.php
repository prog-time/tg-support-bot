<?php

namespace App\Modules\Ai\Controllers;

use App\Modules\Ai\Actions\AiAcceptMessage;
use App\Modules\Ai\Actions\AiCancelMessage;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AiBotController
{
    /**
     * Handle webhook events delivered to the AI bot.
     *
     * The AI bot is used purely as a visual identity in the supergroup —
     * it posts AI drafts/replies into topics so managers can see what
     * the AI produced. Telegram delivers callback_query events on its
     * inline buttons (Accept/Cancel) to this webhook because the AI bot
     * is the author of those messages.
     *
     * Only callback_query updates are acted on; anything else is ignored.
     *
     * @OA\Post(
     *     path="/api/ai-bot/webhook",
     *     summary="Receive AI bot Telegram webhook (callbacks only)",
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

        if ($updateDto === null) {
            Log::channel('loki')->warning('AiBotController: DTO parsing returned null, skipping dispatch', [
                'source' => 'ai_bot_dto_null',
                'payload_keys' => array_keys($request->all()),
            ]);

            return response()->noContent();
        }

        if ($updateDto->typeQuery !== 'callback_query') {
            Log::channel('loki')->info('AiBotController: ignoring non-callback update', [
                'source' => 'ai_bot_ignored',
                'type_query' => $updateDto->typeQuery,
                'type_source' => $updateDto->typeSource,
            ]);

            return response()->noContent();
        }

        $callbackData = (string) $updateDto->callbackData;

        if (preg_match('/^ai_message_send_[0-9]+$/', $callbackData)) {
            Log::channel('loki')->info('AiBotController: accept callback', [
                'source' => 'ai_callback_accept',
                'callback_data' => $callbackData,
            ]);
            app(AiAcceptMessage::class)->execute($updateDto);
        } elseif (preg_match('/^ai_message_cancel_[0-9]+$/', $callbackData)) {
            Log::channel('loki')->info('AiBotController: cancel callback', [
                'source' => 'ai_callback_cancel',
                'callback_data' => $callbackData,
            ]);
            app(AiCancelMessage::class)->execute($updateDto);
        } else {
            Log::channel('loki')->info('AiBotController: unrecognized callback_data, ignoring', [
                'source' => 'ai_callback_unknown',
                'callback_data' => $callbackData,
            ]);
        }

        return response()->noContent();
    }
}
