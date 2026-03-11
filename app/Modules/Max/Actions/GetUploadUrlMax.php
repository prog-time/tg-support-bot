<?php

namespace App\Modules\Max\Actions;

use MaxBotApi\Config;
use MaxBotApi\DTO\UploadResult;
use MaxBotApi\MaxClient;

class GetUploadUrlMax
{
    /**
     * Request a pre-signed upload URL from the Max API.
     *
     * @param string $type File type: 'image', 'video', 'audio', 'file'.
     *
     * @return UploadResult|null
     */
    public function execute(string $type = 'image'): ?UploadResult
    {
        try {
            return $this->fetchUploadUrl($type);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Perform the API call to get the upload URL.
     *
     * @param string $type File type passed to the Max SDK.
     *
     * @return UploadResult
     */
    protected function fetchUploadUrl(string $type): UploadResult
    {
        $client = new MaxClient(new Config(
            token: config('traffic_source.settings.max.token'),
        ));

        return $client->uploads->getUploadUrl($type);
    }
}
