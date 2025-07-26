<?php

namespace App\Services\External\Source;

use App\Models\ExternalSource;
use App\Models\ExternalSourceAccessTokens;
use Illuminate\Support\Str;

class ExternalSourceTokensService
{
    private ExternalSourceAccessTokens $externalSourceAccessTokens;

    public function __construct()
    {
        $this->externalSourceAccessTokens = new ExternalSourceAccessTokens();
    }

    /**
     * @param int $sourceId
     *
     * @return void
     *
     * @throws \Exception
     */
    public function setAccessToken(int $sourceId): void
    {
        try {
            $sourceItem = ExternalSource::where('id', $sourceId)->first();

            if (!$sourceItem) {
                throw new \Exception('Токен не создался. Ресурс не найден!');
            }

            $newAccessToken = $this->generateToken();

            $accessTokenData = [
                'token' => $newAccessToken,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $accessTokensItem = $this->externalSourceAccessTokens->where('id', $sourceId)->first();
            if (!$accessTokensItem) {
                $this->externalSourceAccessTokens->create(array_merge($accessTokenData, [
                    'external_source_id' => $sourceId,
                ]));
            } else {
                $accessTokensItem->update($accessTokenData);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @return string
     */
    private function generateToken(): string
    {
        return Str::random(60);
    }
}
