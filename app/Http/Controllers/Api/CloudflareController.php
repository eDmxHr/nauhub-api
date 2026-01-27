<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\UploadVideoFromPutio;
use App\Services\CloudflareStreamService;
use App\Services\PutioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CloudflareController extends Controller
{
    protected $cloudflare;
    protected $putio;

    public function __construct(CloudflareStreamService $cloudflare, PutioService $putio)
    {
        $this->cloudflare = $cloudflare;
        $this->putio = $putio;
    }

    /**
     * Test Cloudflare Stream connection.
     */
    public function testConnection()
    {
        if (!$this->cloudflare->isConfigured()) {
            return response()->json([
                'message' => 'Cloudflare Stream is not configured',
                'configured' => false,
                'details' => [
                    'account_id' => config('services.cloudflare.account_id') ? 'Set' : 'Missing',
                    'api_token' => config('services.cloudflare.api_token') ? 'Set' : 'Missing',
                    'customer_subdomain' => config('services.cloudflare.customer_subdomain') ? 'Set' : 'Missing',
                ],
            ], 200);
        }

        return response()->json([
            'message' => 'Cloudflare Stream is configured and ready',
            'configured' => true,
            'details' => [
                'account_id' => 'Set',
                'api_token' => 'Set',
                'customer_subdomain' => config('services.cloudflare.customer_subdomain'),
                'base_url' => "https://api.cloudflare.com/client/v4/accounts/" . config('services.cloudflare.account_id') . "/stream",
            ],
        ]);
    }

    /**
     * Generate a direct upload URL for client-side upload (Admin only).
     * This allows uploading large files directly to Cloudflare without going through your server.
     *
     * Returns a unique upload URL that your frontend can use to upload videos directly to Cloudflare.
     * This bypasses PHP file size limits completely.
     *
     * For files >= 200MB or when use_tus is true, returns a TUS resumable upload endpoint.
     */
    public function createDirectUploadUrl(Request $request)
    {
        $validated = $request->validate([
            'max_duration_seconds' => 'nullable|integer|min:1|max:21600', // Max 6 hours (optional - only set if you know the video duration)
            'use_tus' => 'nullable|boolean',
            'file_size' => 'nullable|integer|min:0', // File size in bytes
            'video_uid' => 'nullable|string', // Optional: existing video UID to reuse
            'idempotency_key' => 'nullable|string|max:255', // Optional: idempotency key to prevent duplicate endpoints
            'title' => 'nullable|string|max:255', // Video title/name to appear in Cloudflare
        ]);

        // Only use maxDurationSeconds if explicitly provided - don't default to 6 hours
        $maxDuration = $validated['max_duration_seconds'] ?? null;
        $useTus = $validated['use_tus'] ?? false;
        $fileSize = $validated['file_size'] ?? 0;
        $existingVideoUid = $validated['video_uid'] ?? null;
        
        // Check if TUS should be used: use_tus is true OR file_size >= 200MB
        $shouldUseTus = $useTus || ($fileSize >= 200 * 1024 * 1024);

        if ($shouldUseTus) {
            // Create idempotency key to prevent duplicate endpoint creation
            // Use provided idempotency_key (frontend should send same key for retries), 
            // or generate from request data (user + file size + session)
            $providedKey = $validated['idempotency_key'] ?? $request->header('X-Idempotency-Key');
            $userId = $request->user()?->id ?? 'guest';
            $sessionId = $request->header('X-Session-ID') ?? session()->getId();
            
            $idempotencyKey = $providedKey 
                ? md5($providedKey) 
                : md5($fileSize . '_' . ($maxDuration ?? 'none') . '_' . $userId . '_' . $sessionId);
            $cacheKey = 'tus_endpoint_' . $idempotencyKey;
            
            // Check if we recently created an endpoint for this request
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                \Log::info('Returning cached TUS upload endpoint', [
                    'file_size' => $fileSize,
                    'cache_key' => $cacheKey,
                ]);
                
                return response()->json([
                    'message' => 'TUS upload endpoint retrieved from cache (duplicate request prevented)',
                    'tus_endpoint' => $cachedResult['tus_endpoint'],
                    'video_uid' => $cachedResult['uid'] ?? null,
                    'upload_type' => 'tus',
                    'file_size' => $fileSize,
                    'cached' => true,
                    'warning' => 'This endpoint was already created. Use the tus_endpoint for your upload.',
                    'instructions' => [
                        'resumability' => [
                            'step_1' => 'Before starting/resuming upload, send HEAD request to tus_endpoint to get current Upload-Offset',
                            'step_2' => 'Use the returned Upload-Offset value for subsequent PATCH requests',
                            'step_3' => 'If upload fails, check offset again and resume from that point',
                        ],
                        'upload_chunks' => [
                            'method' => 'PATCH',
                            'url' => $cachedResult['tus_endpoint'],
                            'headers' => [
                                'Authorization: Bearer {your_token}',
                                'Tus-Resumable: 1.0.0',
                                'Upload-Offset: {current_offset}',
                                'Content-Type: application/offset+octet-stream',
                            ],
                            'chunk_size' => 'Recommended: 10-20MB chunks for better reliability',
                            'note' => 'Always check Upload-Offset before each chunk to ensure resumability',
                        ],
                        'check_progress' => [
                            'method' => 'HEAD',
                            'url' => $cachedResult['tus_endpoint'],
                            'headers' => [
                                'Authorization: Bearer {your_token}',
                                'Tus-Resumable: 1.0.0',
                            ],
                            'response' => 'Returns Upload-Offset and Upload-Length headers',
                        ],
                    ],
                ], 200);
            }

            // Log the request to track duplicate calls
            \Log::info('Creating TUS upload endpoint', [
                'file_size' => $fileSize,
                'use_tus' => $useTus,
                'existing_video_uid' => $existingVideoUid,
                'idempotency_key' => $idempotencyKey,
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]);

            // Use TUS resumable upload for large files
            $title = $validated['title'] ?? null;
            $result = $this->cloudflare->createTusUploadEndpoint($fileSize, $maxDuration, $title);
            
            // Cache the result for 5 minutes to prevent duplicate creation
            if ($result) {
                Cache::put($cacheKey, $result, now()->addMinutes(5));
            }

            if ($result) {
                return response()->json([
                    'message' => 'TUS upload endpoint created successfully',
                    'tus_endpoint' => $result['tus_endpoint'],
                    'video_uid' => $result['uid'] ?? null,
                    'upload_type' => 'tus',
                    'file_size' => $fileSize,
                    'max_duration_seconds' => $maxDuration,
                    'warning' => 'Only call this endpoint once per upload. Reuse the tus_endpoint for all chunks.',
                    'note' => $maxDuration 
                        ? "max_duration_seconds set to {$maxDuration} seconds. Only set this if you know the actual video duration."
                        : 'max_duration_seconds not set. Cloudflare will determine duration from the uploaded file.',
                    'instructions' => [
                        'resumability' => [
                            'step_1' => 'Before starting/resuming upload, send HEAD request to tus_endpoint to get current Upload-Offset',
                            'step_2' => 'Use the returned Upload-Offset value for subsequent PATCH requests',
                            'step_3' => 'If upload fails, check offset again and resume from that point',
                        ],
                        'upload_chunks' => [
                            'method' => 'PATCH',
                            'url' => $result['tus_endpoint'],
                            'headers' => [
                                'Authorization: Bearer {your_token}',
                                'Tus-Resumable: 1.0.0',
                                'Upload-Offset: {current_offset}',
                                'Content-Type: application/offset+octet-stream',
                            ],
                            'chunk_size' => 'Recommended: 10-20MB chunks for better reliability',
                            'note' => 'Always check Upload-Offset before each chunk to ensure resumability',
                        ],
                        'check_progress' => [
                            'method' => 'HEAD',
                            'url' => $result['tus_endpoint'],
                            'headers' => [
                                'Authorization: Bearer {your_token}',
                                'Tus-Resumable: 1.0.0',
                            ],
                            'response' => 'Returns Upload-Offset and Upload-Length headers',
                        ],
                    ],
                ], 201);
            }

            return response()->json([
                'message' => 'Failed to create TUS upload endpoint',
                'error' => 'Check logs for detailed error information. This may be due to rate limiting or API issues.',
                'suggestion' => 'Wait a few seconds and retry, or check if you are creating duplicate endpoints.',
            ], 500);
        }

        // Use regular direct upload for smaller files
        $title = $validated['title'] ?? null;
        $result = $this->cloudflare->createDirectUploadUrl($maxDuration, $title);

        if ($result) {
            return response()->json([
                'message' => 'Direct upload URL created successfully',
                'upload_url' => $result['uploadURL'],
                'video_uid' => $result['uid'],
                'upload_type' => 'direct',
                'instructions' => [
                    'method' => 'POST',
                    'url' => $result['uploadURL'],
                    'body' => 'Send the video file as multipart/form-data with field name "file"',
                    'note' => 'Upload directly from your frontend to this URL to bypass PHP file size limits',
                ],
            ], 201);
        }

        return response()->json([
            'message' => 'Failed to create direct upload URL',
        ], 500);
    }

    /**
     * Upload a video file to Cloudflare Stream (Admin only).
     *
     * Note: This method uploads through your PHP server and has file size limits (40MB by default).
     * For large files, use createDirectUploadUrl instead to upload directly to Cloudflare.
     */
    public function uploadVideo(Request $request)
    {
        $validated = $request->validate([
            'video_file' => 'required|file|mimes:mp4,mov,avi,mkv,webm|max:5242880', // 5GB max
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $metadata = [
            'name' => $validated['title'],
            'requireSignedURLs' => false,
        ];

        if (isset($validated['description'])) {
            $metadata['description'] = $validated['description'];
        }

        $result = $this->cloudflare->uploadVideo($request->file('video_file'), $metadata);

        if ($result) {
            return response()->json([
                'message' => 'Video uploaded successfully',
                'video' => [
                    'uid' => $result['uid'],
                    'status' => $result['status']['state'] ?? 'unknown',
                    'ready' => $result['readyToStream'] ?? false,
                    'stream_url' => $this->cloudflare->getStreamUrl($result['uid']),
                    'thumbnail' => $this->cloudflare->getThumbnailUrl($result['uid']),
                    'embed_url' => $this->cloudflare->getEmbedUrl($result['uid']),
                ],
            ], 201);
        }

        return response()->json([
            'message' => 'Failed to upload video',
        ], 500);
    }

    /**
     * Upload video from URL (e.g., Put.io) (Admin only).
     */
    public function uploadFromUrl(Request $request)
    {
        $validated = $request->validate([
            'video_url' => 'required|url',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $metadata = [
            'name' => $validated['title'],
            'requireSignedURLs' => false,
        ];

        if (isset($validated['description'])) {
            $metadata['description'] = $validated['description'];
        }

        $result = $this->cloudflare->uploadFromUrl($validated['video_url'], $metadata);

        if ($result) {
            return response()->json([
                'message' => 'Video upload initiated from URL',
                'video' => [
                    'uid' => $result['uid'],
                    'status' => $result['status']['state'] ?? 'processing',
                    'ready' => $result['readyToStream'] ?? false,
                    'stream_url' => $this->cloudflare->getStreamUrl($result['uid']),
                    'thumbnail' => $this->cloudflare->getThumbnailUrl($result['uid']),
                ],
            ], 201);
        }

        return response()->json([
            'message' => 'Failed to upload video from URL',
        ], 500);
    }

    /**
     * Upload video from magnet link via Put.io (Admin only) - Fully Automated
     *
     * Workflow (all automated in background):
     * 1. Add magnet link to Put.io
     * 2. Dispatch background job that waits for download
     * 3. Job uploads to Cloudflare when ready
     * 4. Returns immediately with transfer ID
     */
    public function uploadFromMagnet(Request $request)
    {
        $validated = $request->validate([
            'magnet_url' => 'nullable|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // If no magnet URL provided, just return success (useful for creating video entry first)
        if (empty($validated['magnet_url'])) {
            return response()->json([
                'message' => 'Video information saved. You can upload the file later using direct upload or provide a magnet URL.',
                'data' => [
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                ],
            ], 200);
        }

        // Step 1: Add transfer to Put.io
        $transferResult = $this->putio->addTransfer($validated['magnet_url']);

        if (!$transferResult['success']) {
            return response()->json([
                'message' => $transferResult['message'],
            ], 500);
        }

        $transferId = $transferResult['transfer']['id'];

        // Step 2: Dispatch background job to handle the rest automatically
        UploadVideoFromPutio::dispatch(
            $transferId,
            $validated['title'],
            $validated['description'] ?? null
        );

        return response()->json([
            'message' => 'Upload process started in background. The system will automatically download from Put.io and upload to Cloudflare.',
            'transfer' => [
                'id' => $transferId,
                'name' => $transferResult['transfer']['name'],
                'status' => $transferResult['transfer']['status'],
            ],
            'note' => 'Check logs for progress. Video will appear in Cloudflare Stream dashboard when complete.',
        ], 202);
    }

    /**
     * Complete upload from Put.io to Cloudflare after transfer is done
     */
    public function completeUpload(Request $request, $transferId)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // Check transfer status first
        $transfer = $this->putio->getTransferStatus($transferId);

        if (!$transfer) {
            return response()->json([
                'message' => 'Transfer not found',
            ], 404);
        }

        if ($transfer['status'] !== 'COMPLETED' && $transfer['status'] !== 'SEEDING') {
            return response()->json([
                'message' => 'Transfer not yet completed',
                'status' => $transfer['status'],
                'percent_done' => $transfer['percent_done'] ?? 0,
            ], 400);
        }

        // Get file URL
        $fileId = $transfer['file_id'];
        $fileUrl = $this->putio->getFileUrl($fileId);

        if (!$fileUrl) {
            return response()->json([
                'message' => 'Failed to get download URL from Put.io',
            ], 500);
        }

        // Upload to Cloudflare
        $metadata = [
            'name' => $validated['title'],
            'requireSignedURLs' => false,
        ];

        if (isset($validated['description'])) {
            $metadata['description'] = $validated['description'];
        }

        $cloudflareResult = $this->cloudflare->uploadFromUrl($fileUrl, $metadata);

        if ($cloudflareResult) {
            return response()->json([
                'message' => 'Video upload to Cloudflare initiated successfully',
                'putio' => [
                    'transfer_id' => $transferId,
                    'file_id' => $fileId,
                    'file_name' => $transfer['name'],
                ],
                'video' => [
                    'uid' => $cloudflareResult['uid'],
                    'status' => $cloudflareResult['status']['state'] ?? 'processing',
                    'ready' => $cloudflareResult['readyToStream'] ?? false,
                    'stream_url' => $this->cloudflare->getStreamUrl($cloudflareResult['uid']),
                    'thumbnail' => $this->cloudflare->getThumbnailUrl($cloudflareResult['uid']),
                    'embed_url' => $this->cloudflare->getEmbedUrl($cloudflareResult['uid']),
                ],
                'note' => 'Cloudflare is now processing the video.',
            ], 202);
        }

        return response()->json([
            'message' => 'Failed to upload video to Cloudflare Stream',
        ], 500);
    }

    /**
     * Initiate magnet transfer without waiting (async approach)
     */
    public function initiateMagnetTransfer(Request $request)
    {
        $validated = $request->validate([
            'magnet_url' => 'required|string',
        ]);

        $transferResult = $this->putio->addTransfer($validated['magnet_url']);

        if (!$transferResult['success']) {
            return response()->json([
                'message' => $transferResult['message'],
            ], 500);
        }

        return response()->json([
            'message' => 'Transfer initiated successfully',
            'transfer' => [
                'id' => $transferResult['transfer']['id'],
                'name' => $transferResult['transfer']['name'],
                'status' => $transferResult['transfer']['status'],
            ],
        ], 201);
    }

    /**
     * Check Put.io transfer status
     */
    public function checkTransferStatus($transferId)
    {
        $transfer = $this->putio->getTransferStatus($transferId);

        if (!$transfer) {
            return response()->json([
                'message' => 'Transfer not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Transfer status retrieved',
            'transfer' => [
                'id' => $transfer['id'],
                'name' => $transfer['name'],
                'status' => $transfer['status'],
                'percent_done' => $transfer['percent_done'] ?? 0,
                'size' => $transfer['size'] ?? null,
                'downloaded' => $transfer['downloaded'] ?? null,
                'file_id' => $transfer['file_id'] ?? null,
            ],
        ]);
    }

    /**
     * Get video details and streaming URLs.
     */
    public function getVideo($videoUid)
    {
        $video = $this->cloudflare->getVideo($videoUid);

        if ($video) {
            return response()->json([
                'message' => 'Video retrieved successfully',
                'video' => [
                    'uid' => $video['uid'],
                    'status' => $video['status']['state'] ?? 'unknown',
                    'ready' => $video['readyToStream'] ?? false,
                    'duration' => $video['duration'] ?? null,
                    'stream_url' => $this->cloudflare->getStreamUrl($video['uid']),
                    'dash_url' => $this->cloudflare->getDashUrl($video['uid']),
                    'thumbnail' => $this->cloudflare->getThumbnailUrl($video['uid']),
                    'embed_url' => $this->cloudflare->getEmbedUrl($video['uid']),
                ],
            ]);
        }

        return response()->json([
            'message' => 'Video not found',
        ], 404);
    }

    /**
     * Get streaming URL for a video.
     */
    public function getStreamUrl($videoUid)
    {
        return response()->json([
            'message' => 'Stream URL retrieved',
            'video_uid' => $videoUid,
            'stream_url' => $this->cloudflare->getStreamUrl($videoUid),
            'dash_url' => $this->cloudflare->getDashUrl($videoUid),
            'thumbnail' => $this->cloudflare->getThumbnailUrl($videoUid),
            'embed_url' => $this->cloudflare->getEmbedUrl($videoUid),
        ]);
    }

    /**
     * Check TUS upload progress/offset (Admin only).
     * Use this to check current upload offset and resume from the correct position.
     */
    public function checkTusUploadProgress(Request $request)
    {
        $validated = $request->validate([
            'tus_endpoint' => 'required|url',
        ]);

        $result = $this->cloudflare->checkTusUploadOffset($validated['tus_endpoint']);

        if ($result) {
            return response()->json([
                'message' => 'TUS upload progress retrieved',
                'upload_offset' => $result['upload_offset'],
                'upload_length' => $result['upload_length'],
                'progress_percent' => $result['progress_percent'],
                'instructions' => [
                    'note' => 'Use the upload_offset value as the Upload-Offset header in your next PATCH request',
                    'resume_from' => $result['upload_offset'],
                ],
            ]);
        }

        return response()->json([
            'message' => 'Failed to check TUS upload progress',
        ], 500);
    }

    /**
     * Get upload/processing status for a video (Admin only).
     * Use this endpoint to poll for progress after direct upload.
     */
    public function getUploadStatus($videoUid)
    {
        $video = $this->cloudflare->getVideo($videoUid);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found or still uploading',
                'status' => [
                    'state' => 'uploading',
                    'ready' => false,
                    'progress' => 0,
                ],
            ], 404);
        }

        // Parse Cloudflare status
        $state = $video['status']['state'] ?? 'unknown';
        $ready = $video['readyToStream'] ?? false;

        // Map state to progress percentage
        $progressMap = [
            'uploading' => 25,
            'queued' => 30,
            'inprogress' => 50,
            'downloading' => 40,
            'pendingupload' => 20,
            'ready' => 100,
            'error' => 0,
        ];

        $progress = $progressMap[$state] ?? 0;

        return response()->json([
            'message' => 'Upload status retrieved',
            'video_uid' => $videoUid,
            'status' => [
                'state' => $state,
                'ready' => $ready,
                'progress' => $progress,
                'duration' => $video['duration'] ?? null,
                'size' => $video['size'] ?? null,
            ],
            'meta' => $video['meta'] ?? null,
            'note' => $ready ? 'Video is ready to stream' : 'Video is still processing. Poll this endpoint to check progress.',
        ]);
    }

    /**
     * Delete a video from Cloudflare Stream (Admin only).
     */
    public function deleteVideo($videoUid)
    {
        $deleted = $this->cloudflare->deleteVideo($videoUid);

        if ($deleted) {
            return response()->json([
                'message' => 'Video deleted successfully',
            ]);
        }

        return response()->json([
            'message' => 'Failed to delete video',
        ], 500);
    }

    /**
     * Get all captions/subtitles for a video
     */
    public function getCaptions($videoUid)
    {
        $captions = $this->cloudflare->getCaptions($videoUid);

        return response()->json([
            'message' => 'Captions retrieved successfully',
            'video_uid' => $videoUid,
            'captions' => $captions,
        ]);
    }

    /**
     * Upload subtitle file to a video (Admin only)
     */
    public function uploadSubtitle(Request $request, $videoUid)
    {
        $validated = $request->validate([
            'subtitle_file' => 'required|file|mimes:vtt,srt|max:2048', // 2MB max
            'language' => 'required|string|max:10',
            'label' => 'nullable|string|max:255',
        ]);

        $file = $request->file('subtitle_file');
        $language = $validated['language'];
        $label = $validated['label'] ?? ucfirst($language);

        // Save temporarily
        $tempPath = $file->store('temp/subtitles');
        $fullPath = storage_path("app/{$tempPath}");

        // Convert SRT to VTT if needed
        if ($file->getClientOriginalExtension() === 'srt') {
            $vttPath = str_replace('.srt', '.vtt', $fullPath);
            $this->convertSrtToVtt($fullPath, $vttPath);
            $fullPath = $vttPath;
        }

        $result = $this->cloudflare->uploadSubtitle($videoUid, $fullPath, $language, $label);

        // Clean up
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        if (file_exists(storage_path("app/{$tempPath}"))) {
            unlink(storage_path("app/{$tempPath}"));
        }

        if ($result) {
            return response()->json([
                'message' => 'Subtitle uploaded successfully',
                'video_uid' => $videoUid,
                'language' => $language,
            ], 201);
        }

        return response()->json([
            'message' => 'Failed to upload subtitle',
        ], 500);
    }

    /**
     * Delete a subtitle track from a video (Admin only)
     */
    public function deleteSubtitle($videoUid, $language)
    {
        $deleted = $this->cloudflare->deleteCaption($videoUid, $language);

        if ($deleted) {
            return response()->json([
                'message' => 'Subtitle deleted successfully',
                'video_uid' => $videoUid,
                'language' => $language,
            ]);
        }

        return response()->json([
            'message' => 'Failed to delete subtitle',
        ], 500);
    }

    /**
     * Convert SRT to VTT format
     *
     * @param string $srtPath
     * @param string $vttPath
     * @return void
     */
    protected function convertSrtToVtt(string $srtPath, string $vttPath): void
    {
        $srtContent = file_get_contents($srtPath);
        $vttContent = "WEBVTT\n\n" . str_replace(',', '.', $srtContent);
        file_put_contents($vttPath, $vttContent);
    }
}
