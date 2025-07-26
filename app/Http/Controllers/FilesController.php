<?php

namespace App\Http\Controllers;

use App\Logging\LokiLogger;
use App\Services\File\FileService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class FilesController
 *
 * @package App\Http\Controllers
 */
class FilesController
{
    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Передать файл на просмотр
     *
     * @param string $fileId
     *
     * @return StreamedResponse
     */
    public function getFileStream(string $fileId): StreamedResponse
    {
        try {
            return $this->fileService->streamFile($fileId);
        } catch (\Exception $e) {
            (new LokiLogger())->log('tg_request', json_encode($e->getMessage()));
            die();
        }
    }

    /**
     * Передать файл на скачивание
     *
     * @param string $fileId
     *
     * @return Response
     */
    public function getFileDownload(string $fileId): Response
    {
        try {
            return $this->fileService->downloadFile($fileId);
        } catch (\Exception $e) {
            (new LokiLogger())->log('tg_request', json_encode($e->getMessage()));
            die();
        }
    }
}
