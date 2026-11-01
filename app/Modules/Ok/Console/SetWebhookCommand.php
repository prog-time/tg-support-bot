<?php

namespace App\Modules\Ok\Console;

use App\Modules\Admin\Services\WebhookRegistrationService;
use Illuminate\Console\Command;

class SetWebhookCommand extends Command
{
    /** @var string */
    protected $signature = 'ok:set-webhook';

    /** @var string */
    protected $description = 'Subscribe the Odnoklassniki webhook to this application';

    /**
     * Subscribe the webhook again, as «Сохранить» on /admin/settings/ok does.
     * The output never contains the token or the secret.
     *
     * @param WebhookRegistrationService $webhook
     *
     * @return int
     */
    public function handle(WebhookRegistrationService $webhook): int
    {
        $result = $webhook->registerOk();

        if ($result['success']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
