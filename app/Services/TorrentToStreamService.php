<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Torrent to Stream Service
 *
 * Handles the complete workflow from torrent/magnet to Cloudflare Stream
 */
class TorrentToStreamService
{
    protected $putioService;
    protected $cloudflareService;
    protected $subtitleService;

    public function __construct(
        PutioService $putioService,
        CloudflareStreamService $cloudflareService,
        SubtitleService $subtitleService
    ) {
        $this->putioService = $putioService;
        $this->cloudflareService = $cloudflareService;
        $this->subtitleService = $subtitleService;
    }

    /**
     * Upload video from magnet URL (background processing)
     *
     * @param string $magnetUrl
     * @param array $metadata Video metadata
     * @return array
     */
    public function uploadFromMagnet(string $magnetUrl, array $metadata = []): array
    {
        // Add transfer to Put.io (without waiting for completion)
        $result = $this->processToStream($magnetUrl, $metadata, false);

        if ($result['success']) {
            // Dispatch background job with 30 second delay to allow transfer to start
            \App\Jobs\UploadVideoFromPutio::dispatch(
                $result['transfer_id'],
                $metadata['name'] ?? 'Untitled',
                $metadata['description'] ?? null
            )->delay(now()->addSeconds(30));
        }

        return $result;
    }

    /**
     * Process a magnet/torrent link to Cloudflare Stream
     *
     * @param string $magnetOrTorrentUrl
     * @param array $metadata Video metadata for Cloudflare
     * @param bool $waitForCompletion Whether to wait for Put.io download
     * @return array
     */
    public function processToStream(string $magnetOrTorrentUrl, array $metadata = [], bool $waitForCompletion = true): array
    {
        // Step 1: Add transfer to Put.io
        $transfer = $this->putioService->addTransfer($magnetOrTorrentUrl);

        if (!$transfer['success']) {
            return [
                'success' => false,
                'step' => 'putio_add_transfer',
                'message' => $transfer['message'],
            ];
        }

        $transferId = $transfer['transfer']['id'];
        Log::info('Put.io transfer started', ['transfer_id' => $transferId]);

        // Step 2: Wait for completion (optional)
        if ($waitForCompletion) {
            $completion = $this->putioService->waitForTransferCompletion($transferId);

            if (!$completion['success']) {
                return [
                    'success' => false,
                    'step' => 'putio_wait_completion',
                    'message' => $completion['message'],
                    'transfer_id' => $transferId,
                ];
            }

            $fileId = $completion['file_id'];
        } else {
            // Return early with transfer ID for async processing
            return [
                'success' => true,
                'step' => 'putio_transfer_started',
                'transfer_id' => $transferId,
                'message' => 'Transfer started, check status separately',
            ];
        }

        // Step 3: Get Put.io download URL
        $downloadUrl = $this->putioService->getFileUrl($fileId);

        if (!$downloadUrl) {
            return [
                'success' => false,
                'step' => 'putio_get_url',
                'message' => 'Could not get Put.io download URL',
                'file_id' => $fileId,
            ];
        }

        // Step 4: Upload to Cloudflare Stream
        $streamResult = $this->cloudflareService->uploadFromUrl($downloadUrl, $metadata);

        if (!$streamResult) {
            return [
                'success' => false,
                'step' => 'cloudflare_upload',
                'message' => 'Failed to upload to Cloudflare Stream',
                'download_url' => $downloadUrl,
            ];
        }

        // Step 5: Optionally clean up Put.io file
        // $this->putioService->deleteFile($fileId);

        return [
            'success' => true,
            'stream_uid' => $streamResult['uid'],
            'stream_status' => $streamResult['status']['state'] ?? 'unknown',
            'putio_file_id' => $fileId,
            'cloudflare_result' => $streamResult,
            'urls' => [
                'hls' => $this->cloudflareService->getStreamUrl($streamResult['uid']),
                'thumbnail' => $this->cloudflareService->getThumbnailUrl($streamResult['uid']),
                'embed' => $this->cloudflareService->getEmbedUrl($streamResult['uid']),
            ],
        ];
    }

    /**
     * Process completed Put.io transfer to Cloudflare (for async workflows)
     *
     * @param int $transferId
     * @param array $metadata
     * @return array
     */
    public function processCompletedTransfer(int $transferId, array $metadata = []): array
    {
        // Get transfer details
        $transfer = $this->putioService->getTransferStatus($transferId);

        if (!$transfer || $transfer['status'] !== 'COMPLETED') {
            return [
                'success' => false,
                'message' => 'Transfer not completed yet',
                'status' => $transfer['status'] ?? 'unknown',
            ];
        }

        $fileId = $transfer['file_id'];

        // Check if it's a folder and find the actual video file
        $fileInfo = $this->putioService->getFile($fileId);

        if ($fileInfo['content_type'] === 'application/x-directory') {
            Log::info('Transfer is a folder, finding video file inside', ['folder_id' => $fileId]);

            $files = $this->putioService->listFiles($fileId);

            // Pick the main video file (largest .mp4/.mkv/.mov/.avi)
            $videoFile = collect($files)
                ->filter(fn($f) => in_array(pathinfo($f['name'], PATHINFO_EXTENSION), ['mp4', 'mkv', 'mov', 'avi']))
                ->sortByDesc('size')
                ->first();

            if (!$videoFile) {
                return [
                    'success' => false,
                    'step' => 'find_video_file',
                    'message' => 'No video file found in folder',
                ];
            }

            $fileId = $videoFile['id'];
            Log::info('Found video file in folder', [
                'video_file_id' => $fileId,
                'name' => $videoFile['name'],
                'size' => $videoFile['size'],
            ]);
        }

        // Continue with steps 3-5 from processToStream()
        $downloadUrl = $this->putioService->getFileUrl($fileId);

        if (!$downloadUrl) {
            return [
                'success' => false,
                'step' => 'putio_get_url',
                'message' => 'Could not get Put.io download URL',
            ];
        }

        // Use direct upload instead of URL upload to avoid Put.io rate limiting
        Log::info('Using direct upload method', ['file_id' => $fileId]);

        $streamResult = $this->uploadFromPutioToCloudflare($downloadUrl, $fileId, $metadata);

        if (!$streamResult) {
            return [
                'success' => false,
                'step' => 'cloudflare_upload',
                'message' => 'Failed to upload to Cloudflare Stream',
            ];
        }

        $videoUid = $streamResult['uid'];

        return [
            'success' => true,
            'stream_uid' => $videoUid,
            'stream_status' => $streamResult['status']['state'] ?? 'unknown',
            'cloudflare_result' => $streamResult,
        ];
    }

    /**
     * Download file from Put.io and upload directly to Cloudflare
     *
     * @param string $downloadUrl
     * @param int $fileId
     * @param array $metadata
     * @return array|null
     */
    protected function uploadFromPutioToCloudflare(string $downloadUrl, int $fileId, array $metadata = [])
    {
        try {
            // Get file info from Put.io
            $fileInfo = $this->putioService->getFile($fileId);
            if (!$fileInfo) {
                Log::error('Could not get Put.io file info', ['file_id' => $fileId]);
                return null;
            }

            $fileName = $fileInfo['name'];
            $fileSize = $fileInfo['size'];

            Log::info('Starting direct upload to Cloudflare', [
                'file_name' => $fileName,
                'file_size' => $fileSize,
            ]);

            // Download file from Put.io in chunks and stream to Cloudflare
            $tempFile = tempnam(sys_get_temp_dir(), 'putio_');

            // Download file from Put.io
            $ch = curl_init($downloadUrl);
            $fp = fopen($tempFile, 'w+');

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3600); // 1 hour timeout
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if ($httpCode !== 200) {
                Log::error('Failed to download from Put.io', ['http_code' => $httpCode]);
                unlink($tempFile);
                return null;
            }

            Log::info('File downloaded from Put.io, uploading to Cloudflare', [
                'temp_file' => $tempFile,
                'size' => filesize($tempFile),
            ]);

            // Upload to Cloudflare Stream using multipart form data
            $result = $this->cloudflareService->uploadFromFile($tempFile, $fileName, $metadata);

            // Clean up temp file
            unlink($tempFile);

            return $result;
        } catch (\Exception $e) {
            Log::error('Direct upload exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Extract subtitles from video and upload to Cloudflare
     *
     * This runs asynchronously and won't block the main workflow
     *
     * @param string $videoUrl
     * @param string $videoUid
     * @return void
     */
    protected function extractAndUploadSubtitles(string $videoUrl, string $videoUid): void
    {
        try {
            Log::info('Starting subtitle extraction', ['video_uid' => $videoUid]);

            // Extract subtitles from the video
            $subtitles = $this->subtitleService->extractSubtitlesFromUrl($videoUrl, $videoUid);

            if (empty($subtitles)) {
                Log::info('No subtitles found in video', ['video_uid' => $videoUid]);
                return;
            }

            // Upload each subtitle track to Cloudflare
            foreach ($subtitles as $subtitle) {
                $this->cloudflareService->uploadSubtitle(
                    $videoUid,
                    $subtitle['file'],
                    $subtitle['language'],
                    $subtitle['label']
                );
            }

            Log::info('Subtitles uploaded successfully', [
                'video_uid' => $videoUid,
                'count' => count($subtitles),
            ]);

        } catch (\Exception $e) {
            // Don't fail the main workflow if subtitle extraction fails
            Log::error('Subtitle extraction/upload failed', [
                'video_uid' => $videoUid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get status of the entire workflow
     *
     * @param int $transferId
     * @param string|null $streamUid
     * @return array
     */
    public function getWorkflowStatus(int $transferId, ?string $streamUid = null): array
    {
        $putioStatus = $this->putioService->getTransferStatus($transferId);

        $status = [
            'transfer_id' => $transferId,
            'putio' => [
                'status' => $putioStatus['status'] ?? 'unknown',
                'progress' => $putioStatus['percent_done'] ?? 0,
                'name' => $putioStatus['name'] ?? null,
            ],
        ];

        if ($streamUid) {
            $cloudflareStatus = $this->cloudflareService->getUploadStatus($streamUid);
            $status['cloudflare'] = $cloudflareStatus;
        }

        return $status;
    }
}
