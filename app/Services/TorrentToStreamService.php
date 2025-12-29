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

        // Continue with steps 3-5 from processToStream()
        $downloadUrl = $this->putioService->getFileUrl($fileId);

        if (!$downloadUrl) {
            return [
                'success' => false,
                'step' => 'putio_get_url',
                'message' => 'Could not get Put.io download URL',
            ];
        }

        $streamResult = $this->cloudflareService->uploadFromUrl($downloadUrl, $metadata);

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
