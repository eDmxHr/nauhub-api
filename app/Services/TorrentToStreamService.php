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
    protected $uploadJobService;

    public function __construct(
        PutioService $putioService,
        CloudflareStreamService $cloudflareService,
        SubtitleService $subtitleService,
        UploadJobService $uploadJobService
    ) {
        $this->putioService = $putioService;
        $this->cloudflareService = $cloudflareService;
        $this->subtitleService = $subtitleService;
        $this->uploadJobService = $uploadJobService;
    }

    /**
     * Upload video from magnet URL (background processing)
     *
     * @param string $magnetUrl
     * @param array $metadata Video metadata
     * @param string $modelType 'video' or 'episode'
     * @param int|null $modelId The model ID (set after creation if not known yet)
     * @return array
     */
    public function uploadFromMagnet(string $magnetUrl, array $metadata = [], string $modelType = 'video', ?int $modelId = null): array
    {
        // Add transfer to Put.io (without waiting for completion)
        $result = $this->processToStream($magnetUrl, $metadata, false);

        if ($result['success']) {
            $transferId = $result['transfer_id'];
            $title = $metadata['name'] ?? 'Untitled';

            // Create upload job for progress tracking
            $uploadJob = $this->uploadJobService->createMagnetUploadJob(
                $modelType,
                $modelId,
                $transferId,
                $title
            );

            // Return the upload job ID so it can be linked to the model after creation
            $result['upload_job_id'] = $uploadJob->id;

            // Dispatch background job with 30 second delay to allow transfer to start
            \App\Jobs\UploadVideoFromPutio::dispatch(
                $transferId,
                $title,
                $metadata['description'] ?? null,
                $modelType,
                $modelId
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
     * @param \App\Models\UploadJob|null $uploadJob
     * @return array
     */
    public function processCompletedTransfer(int $transferId, array $metadata = [], ?\App\Models\UploadJob $uploadJob = null): array
    {
        // Find or create upload job for progress tracking
        if (!$uploadJob) {
            $uploadJob = $this->uploadJobService->findByTransferId($transferId);
        }
        Log::info('📥 [MAGNET UPLOAD] Step 1: Checking Put.io transfer status', [
            'transfer_id' => $transferId,
        ]);

        // Get transfer details
        $transfer = $this->putioService->getTransferStatus($transferId);

        if (!$transfer) {
            Log::error('❌ [MAGNET UPLOAD] Transfer not found', ['transfer_id' => $transferId]);
            return [
                'success' => false,
                'message' => 'Transfer not found',
                'status' => 'unknown',
            ];
        }

        Log::info('📊 [MAGNET UPLOAD] Put.io transfer status', [
            'transfer_id' => $transferId,
            'status' => $transfer['status'],
            'percent_done' => $transfer['percent_done'] ?? 0,
            'name' => $transfer['name'] ?? 'Unknown',
        ]);

        // Wait for Put.io transfer to complete with progress tracking
        if ($transfer['status'] !== 'COMPLETED' && $transfer['status'] !== 'SEEDING') {
            Log::info('⏳ [MAGNET UPLOAD] Waiting for Put.io transfer to complete...', [
                'transfer_id' => $transferId,
            ]);

            $waitResult = $this->waitForTransferWithProgress($transferId, $uploadJob);

            if (!$waitResult['success']) {
                if ($uploadJob) {
                    $uploadJob->markAsFailed($waitResult['message']);
                }
                return [
                    'success' => false,
                    'message' => $waitResult['message'],
                    'status' => $waitResult['status'] ?? 'unknown',
                ];
            }

            // Update transfer with completed data
            $transfer = $this->putioService->getTransferStatus($transferId);
        }

        Log::info('✅ [MAGNET UPLOAD] Step 2: Put.io transfer completed', [
            'transfer_id' => $transferId,
            'file_id' => $transfer['file_id'],
        ]);

        $fileId = $transfer['file_id'];

        Log::info('📁 [MAGNET UPLOAD] Step 3: Getting file information', [
            'file_id' => $fileId,
        ]);

        // Check if it's a folder and find the actual video file
        $fileInfo = $this->putioService->getFile($fileId);

        if ($fileInfo['content_type'] === 'application/x-directory') {
            Log::info('📂 [MAGNET UPLOAD] Transfer is a folder, finding video file inside', [
                'folder_id' => $fileId,
            ]);

            $files = $this->putioService->listFiles($fileId);

            // Pick the main video file (largest .mp4/.mkv/.mov/.avi)
            $videoFile = collect($files)
                ->filter(fn($f) => in_array(pathinfo($f['name'], PATHINFO_EXTENSION), ['mp4', 'mkv', 'mov', 'avi']))
                ->sortByDesc('size')
                ->first();

            if (!$videoFile) {
                Log::error('❌ [MAGNET UPLOAD] No video file found in folder', ['folder_id' => $fileId]);
                return [
                    'success' => false,
                    'step' => 'find_video_file',
                    'message' => 'No video file found in folder',
                ];
            }

            $fileId = $videoFile['id'];
            Log::info('✅ [MAGNET UPLOAD] Found video file in folder', [
                'video_file_id' => $fileId,
                'name' => $videoFile['name'],
                'size' => $videoFile['size'],
            ]);
        }

        Log::info('🔗 [MAGNET UPLOAD] Step 4: Getting Put.io download URL', [
            'file_id' => $fileId,
        ]);

        // Continue with steps 3-5 from processToStream()
        $downloadUrl = $this->putioService->getFileUrl($fileId);

        if (!$downloadUrl) {
            Log::error('❌ [MAGNET UPLOAD] Could not get Put.io download URL', ['file_id' => $fileId]);
            return [
                'success' => false,
                'step' => 'putio_get_url',
                'message' => 'Could not get Put.io download URL',
            ];
        }

        Log::info('✅ [MAGNET UPLOAD] Got download URL, starting upload to Cloudflare', [
            'file_id' => $fileId,
            'method' => 'direct_upload',
        ]);

        // Use direct upload instead of URL upload to avoid Put.io rate limiting
        $streamResult = $this->uploadFromPutioToCloudflare($downloadUrl, $fileId, $metadata, $uploadJob);

        if (!$streamResult) {
            Log::error('❌ [MAGNET UPLOAD] Failed to upload to Cloudflare Stream', ['file_id' => $fileId]);
            if ($uploadJob) {
                $uploadJob->markAsFailed('Failed to upload to Cloudflare Stream');
            }
            return [
                'success' => false,
                'step' => 'cloudflare_upload',
                'message' => 'Failed to upload to Cloudflare Stream',
            ];
        }

        $videoUid = $streamResult['uid'];

        // Mark upload job as processing (Cloudflare transcoding)
        if ($uploadJob) {
            $uploadJob->markAsProcessing($videoUid);
        }

        Log::info('🎉 [MAGNET UPLOAD] Successfully uploaded to Cloudflare', [
            'transfer_id' => $transferId,
            'file_id' => $fileId,
            'cloudflare_uid' => $videoUid,
            'status' => $streamResult['status']['state'] ?? 'unknown',
        ]);

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
     * @param \App\Models\UploadJob|null $uploadJob
     * @return array|null
     */
    protected function uploadFromPutioToCloudflare(string $downloadUrl, int $fileId, array $metadata = [], ?\App\Models\UploadJob $uploadJob = null)
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

            Log::info('⬇️ [MAGNET UPLOAD] Step 5: Starting download from Put.io', [
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'file_id' => $fileId,
            ]);

            // Mark as downloading from Put.io to server
            if ($uploadJob) {
                $uploadJob->markAsDownloading($fileSize);
            }

            // Download file from Put.io in chunks and stream to Cloudflare
            $tempFile = tempnam(sys_get_temp_dir(), 'putio_');

            // Download file from Put.io with progress tracking
            $ch = curl_init($downloadUrl);
            $fp = fopen($tempFile, 'w+');

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3600); // 1 hour timeout

            // Track download progress
            if ($uploadJob) {
                $lastUpdate = 0;
                curl_setopt($ch, CURLOPT_NOPROGRESS, false);
                curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($resource, $downloadSize, $downloaded, $uploadSize, $uploaded) use ($uploadJob, &$lastUpdate, $fileSize) {
                    $total = $downloadSize > 0 ? $downloadSize : $fileSize;
                    if ($total > 0 && $downloaded > 0) {
                        // Only update every 5% to avoid too many DB writes
                        $percent = (int)(($downloaded / $total) * 100);
                        if ($percent >= $lastUpdate + 5 || $percent === 100) {
                            $uploadJob->updateProgress($percent, (int)$downloaded, (int)$total);
                            $lastUpdate = $percent;
                        }
                    }
                    return 0; // Return 0 to continue download
                });
            }

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if ($httpCode !== 200) {
                Log::error('Failed to download from Put.io', ['http_code' => $httpCode]);
                unlink($tempFile);
                return null;
            }

            $actualFileSize = filesize($tempFile);

            Log::info('✅ [MAGNET UPLOAD] Step 6: File downloaded from Put.io, uploading to Cloudflare', [
                'temp_file' => $tempFile,
                'size' => $actualFileSize,
                'file_name' => $fileName,
            ]);

            // Mark as uploading to Cloudflare
            if ($uploadJob) {
                $uploadJob->markAsUploading($actualFileSize);
            }

            // Upload to Cloudflare Stream using multipart form data (with progress tracking)
            $result = $this->cloudflareService->uploadFromFile($tempFile, $fileName, $metadata, $uploadJob);

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

    /**
     * Wait for Put.io transfer to complete with progress tracking
     *
     * @param int $transferId
     * @param \App\Models\UploadJob|null $uploadJob
     * @param int $maxAttempts Maximum polling attempts (default 360 = 1 hour with 10 sec intervals)
     * @param int $sleepSeconds Seconds between polling (default 10)
     * @return array
     */
    protected function waitForTransferWithProgress(int $transferId, ?\App\Models\UploadJob $uploadJob = null, int $maxAttempts = 360, int $sleepSeconds = 10): array
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $transfer = $this->putioService->getTransferStatus($transferId);

            if (!$transfer) {
                return [
                    'success' => false,
                    'message' => 'Transfer not found',
                    'status' => 'unknown',
                ];
            }

            // Update progress in upload job
            if ($uploadJob) {
                $this->uploadJobService->syncPutioStatus($uploadJob, $transfer);
            }

            // Check if transfer is completed
            if ($transfer['status'] === 'COMPLETED' || $transfer['status'] === 'SEEDING') {
                Log::info('✅ [MAGNET UPLOAD] Put.io transfer completed', [
                    'transfer_id' => $transferId,
                    'file_id' => $transfer['file_id'],
                    'attempts' => $attempts,
                ]);

                return [
                    'success' => true,
                    'file_id' => $transfer['file_id'],
                    'name' => $transfer['name'],
                    'size' => $transfer['size'],
                ];
            }

            // Check if transfer failed
            if ($transfer['status'] === 'ERROR') {
                $errorMessage = $transfer['error_message'] ?? 'Unknown error';
                Log::error('❌ [MAGNET UPLOAD] Put.io transfer failed', [
                    'transfer_id' => $transferId,
                    'error' => $errorMessage,
                ]);

                return [
                    'success' => false,
                    'message' => 'Transfer failed: ' . $errorMessage,
                    'status' => 'ERROR',
                ];
            }

            // Log progress every 6 attempts (1 minute)
            if ($attempts % 6 === 0) {
                Log::info('⏳ [MAGNET UPLOAD] Waiting for Put.io transfer...', [
                    'transfer_id' => $transferId,
                    'status' => $transfer['status'],
                    'percent_done' => $transfer['percent_done'] ?? 0,
                    'attempts' => $attempts,
                ]);
            }

            $attempts++;
            sleep($sleepSeconds);
        }

        return [
            'success' => false,
            'message' => 'Transfer timeout - still downloading after ' . ($maxAttempts * $sleepSeconds / 60) . ' minutes',
            'status' => 'TIMEOUT',
        ];
    }
}
