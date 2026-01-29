<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\UploadVideoFromPutio;
use App\Models\UploadJob;
use App\Services\CloudflareStreamService;
use App\Services\PutioService;
use App\Services\UploadJobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CloudflareController extends Controller
{
    protected $cloudflare;
    protected $putio;
    protected $uploadJobService;

    public function __construct(CloudflareStreamService $cloudflare, PutioService $putio, UploadJobService $uploadJobService)
    {
        $this->cloudflare = $cloudflare;
        $this->putio = $putio;
        $this->uploadJobService = $uploadJobService;
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
            'model_type' => 'nullable|string|in:video,episode', // Optional: model type for upload job tracking
            'model_id' => 'nullable|integer', // Optional: model ID for upload job tracking
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
                // Create upload job for tracking
                $uploadJobId = null;
                $videoUid = $result['uid'] ?? null;
                if ($videoUid) {
                    $uploadJob = $this->uploadJobService->createTusUploadJob(
                        $validated['model_type'] ?? 'video',
                        $validated['model_id'] ?? null,
                        $validated['title'] ?? 'Untitled',
                        $fileSize,
                        $videoUid
                    );
                    $uploadJobId = $uploadJob->id;
                }

                return response()->json([
                    'message' => 'TUS upload endpoint created successfully',
                    'tus_endpoint' => $result['tus_endpoint'],
                    'video_uid' => $videoUid,
                    'upload_job_id' => $uploadJobId,
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
                        'after_upload' => [
                            'note' => 'After upload completes, call POST /api/admin/cloudflare/upload-complete with video_uid to trigger processing status tracking',
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
            // Create upload job for tracking
            $uploadJobId = null;
            $videoUid = $result['uid'];
            if ($videoUid) {
                $uploadJob = $this->uploadJobService->createDirectUploadJob(
                    $validated['model_type'] ?? 'video',
                    $validated['model_id'] ?? null,
                    $validated['title'] ?? 'Untitled',
                    $fileSize
                );
                $uploadJob->update(['cloudflare_uid' => $videoUid]);
                $uploadJobId = $uploadJob->id;
            }

            return response()->json([
                'message' => 'Direct upload URL created successfully',
                'upload_url' => $result['uploadURL'],
                'video_uid' => $videoUid,
                'upload_job_id' => $uploadJobId,
                'upload_type' => 'direct',
                'instructions' => [
                    'method' => 'POST',
                    'url' => $result['uploadURL'],
                    'body' => 'Send the video file as multipart/form-data with field name "file"',
                    'note' => 'Upload directly from your frontend to this URL to bypass PHP file size limits',
                    'after_upload' => 'After upload completes, call POST /api/admin/cloudflare/upload-complete with video_uid to trigger processing status tracking',
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

        // Step 2: Create upload job for progress tracking
        $uploadJob = $this->uploadJobService->createMagnetUploadJob(
            'video', // Default to video, will be updated when we know which model
            null,
            $transferId,
            $validated['title']
        );

        // Step 3: Dispatch background job to handle the rest automatically
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
            'upload_job_id' => $uploadJob->id,
            'note' => 'Use /api/admin/cloudflare/upload-jobs to track all upload progress.',
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

        // Check if there's a video/episode with this transfer ID
        $tempUid = 'processing_transfer_' . $transferId;
        $video = \App\Models\Video::where('cloudflare_video_uid', $tempUid)->first();
        $episode = \App\Models\Episode::where('cloudflare_video_uid', $tempUid)->first();

        $cloudflareStatus = null;
        if ($video && $video->cloudflare_video_uid !== $tempUid) {
            // Video has been updated with actual Cloudflare UID
            $cloudflareVideo = $this->cloudflare->getVideo($video->cloudflare_video_uid);
            if ($cloudflareVideo) {
                $cloudflareStatus = [
                    'uid' => $video->cloudflare_video_uid,
                    'status' => $cloudflareVideo['status']['state'] ?? 'unknown',
                    'ready' => $cloudflareVideo['readyToStream'] ?? false,
                    'progress' => $this->calculateCloudflareProgress($cloudflareVideo),
                ];
            }
        } elseif ($episode && $episode->cloudflare_video_uid !== $tempUid) {
            // Episode has been updated with actual Cloudflare UID
            $cloudflareVideo = $this->cloudflare->getVideo($episode->cloudflare_video_uid);
            if ($cloudflareVideo) {
                $cloudflareStatus = [
                    'uid' => $episode->cloudflare_video_uid,
                    'status' => $cloudflareVideo['status']['state'] ?? 'unknown',
                    'ready' => $cloudflareVideo['readyToStream'] ?? false,
                    'progress' => $this->calculateCloudflareProgress($cloudflareVideo),
                ];
            }
        }

        // Determine overall workflow status
        $workflowStatus = $this->determineWorkflowStatus($transfer, $cloudflareStatus);

        return response()->json([
            'message' => 'Transfer status retrieved',
            'workflow_status' => $workflowStatus,
            'putio' => [
                'id' => $transfer['id'],
                'name' => $transfer['name'],
                'status' => $transfer['status'],
                'percent_done' => $transfer['percent_done'] ?? 0,
                'size' => $transfer['size'] ?? null,
                'downloaded' => $transfer['downloaded'] ?? null,
                'file_id' => $transfer['file_id'] ?? null,
            ],
            'cloudflare' => $cloudflareStatus,
            'progress' => [
                'putio_progress' => $transfer['percent_done'] ?? 0,
                'cloudflare_progress' => $cloudflareStatus ? $cloudflareStatus['progress'] : 0,
                'overall_progress' => $this->calculateOverallProgress($transfer, $cloudflareStatus),
            ],
        ]);
    }

    /**
     * Calculate Cloudflare processing progress based on status
     */
    protected function calculateCloudflareProgress($cloudflareVideo): int
    {
        $state = $cloudflareVideo['status']['state'] ?? 'unknown';

        $progressMap = [
            'uploading' => 10,
            'pendingupload' => 5,
            'queued' => 20,
            'downloading' => 15,
            'inprogress' => 50,
            'ready' => 100,
            'error' => 0,
        ];

        return $progressMap[$state] ?? 0;
    }

    /**
     * Calculate overall workflow progress
     */
    protected function calculateOverallProgress($transfer, $cloudflareStatus): int
    {
        $putioProgress = $transfer['percent_done'] ?? 0;

        // Put.io download is 50% of the workflow
        $putioWeight = 50;

        // Cloudflare upload/processing is 50% of the workflow
        $cloudflareWeight = 50;
        $cloudflareProgress = $cloudflareStatus ? $cloudflareStatus['progress'] : 0;

        // If Put.io is not completed, only count Put.io progress
        if ($transfer['status'] !== 'COMPLETED') {
            return (int)($putioProgress * ($putioWeight / 100));
        }

        // If Put.io is completed, count both
        return (int)(($putioWeight) + ($cloudflareProgress * ($cloudflareWeight / 100)));
    }

    /**
     * Determine overall workflow status
     */
    protected function determineWorkflowStatus($transfer, $cloudflareStatus): string
    {
        if ($transfer['status'] === 'ERROR' || ($cloudflareStatus && $cloudflareStatus['status'] === 'error')) {
            return 'error';
        }

        if ($cloudflareStatus && $cloudflareStatus['ready']) {
            return 'completed';
        }

        if ($cloudflareStatus) {
            return 'cloudflare_processing';
        }

        if ($transfer['status'] === 'COMPLETED') {
            return 'uploading_to_cloudflare';
        }

        return 'downloading_from_putio';
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
     * Also updates the upload job progress if upload_job_id is provided.
     */
    public function checkTusUploadProgress(Request $request)
    {
        $validated = $request->validate([
            'tus_endpoint' => 'required|url',
            'upload_job_id' => 'nullable|integer',
        ]);

        $result = $this->cloudflare->checkTusUploadOffset($validated['tus_endpoint']);

        if ($result) {
            // Update upload job progress if provided
            if (isset($validated['upload_job_id'])) {
                $uploadJob = \App\Models\UploadJob::find($validated['upload_job_id']);
                if ($uploadJob && $uploadJob->isInProgress()) {
                    $uploadJob->update([
                        'bytes_transferred' => $result['upload_offset'],
                        'bytes_total' => $result['upload_length'],
                        'progress_percent' => $result['progress_percent'],
                    ]);
                }
            }

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
     * Notify that a direct/TUS upload has completed (Admin only).
     * Call this endpoint after frontend finishes uploading to Cloudflare.
     * This will update the upload job status and start monitoring Cloudflare processing.
     */
    public function notifyUploadComplete(Request $request)
    {
        $validated = $request->validate([
            'video_uid' => 'required|string',
            'model_type' => 'nullable|string|in:video,episode',
            'model_id' => 'nullable|integer',
        ]);

        $videoUid = $validated['video_uid'];
        $modelType = $validated['model_type'] ?? null;
        $modelId = $validated['model_id'] ?? null;

        // Find the upload job by cloudflare UID
        $uploadJob = $this->uploadJobService->findByCloudflareUid($videoUid);

        if (!$uploadJob) {
            return response()->json([
                'message' => 'Upload job not found for this video UID',
                'video_uid' => $videoUid,
            ], 404);
        }

        // Update model association if provided
        if ($modelType && $modelId) {
            $uploadJob->update([
                'model_type' => $modelType,
                'model_id' => $modelId,
            ]);

            // Also update the Video/Episode model with cloudflare_video_uid
            if ($modelType === 'video') {
                $video = \App\Models\Video::find($modelId);
                if ($video && (!$video->cloudflare_video_uid || str_starts_with($video->cloudflare_video_uid, 'processing_'))) {
                    $video->update([
                        'cloudflare_video_uid' => $videoUid,
                        'status' => 'processing',
                    ]);
                }
            } elseif ($modelType === 'episode') {
                $episode = \App\Models\Episode::find($modelId);
                if ($episode && (!$episode->cloudflare_video_uid || str_starts_with($episode->cloudflare_video_uid, 'processing_'))) {
                    $episode->update([
                        'cloudflare_video_uid' => $videoUid,
                        'status' => 'processing',
                    ]);
                }
            }
        }

        // Check current video status from Cloudflare
        $video = $this->cloudflare->getVideo($videoUid);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found on Cloudflare. Upload may still be in progress.',
                'video_uid' => $videoUid,
                'upload_job_id' => $uploadJob->id,
                'status' => 'uploading',
            ], 202);
        }

        $state = $video['status']['state'] ?? 'unknown';
        $ready = $video['readyToStream'] ?? false;

        if ($ready && $state === 'ready') {
            // Video is already ready
            $uploadJob->markAsCompleted();

            // Update Video/Episode model to ready status
            if ($modelType && $modelId) {
                $duration = $video['duration'] ?? null;
                if ($modelType === 'video') {
                    $videoModel = \App\Models\Video::find($modelId);
                    if ($videoModel) {
                        $videoModel->update([
                            'status' => 'ready',
                            'is_published' => true,
                            'duration' => $duration ? (int)$duration : $videoModel->duration,
                        ]);
                    }
                } elseif ($modelType === 'episode') {
                    $episode = \App\Models\Episode::find($modelId);
                    if ($episode) {
                        $episode->update([
                            'status' => 'ready',
                            'is_published' => true,
                            'duration' => $duration ? (int)$duration : $episode->duration,
                        ]);
                    }
                }
            }

            return response()->json([
                'message' => 'Video is ready to stream',
                'video_uid' => $videoUid,
                'upload_job_id' => $uploadJob->id,
                'status' => 'completed',
                'duration' => $video['duration'] ?? null,
            ]);
        }

        // Mark as processing and start monitoring
        $uploadJob->markAsProcessing($videoUid);

        // Start transcoding status monitoring
        if ($modelType && $modelId) {
            \App\Jobs\CheckVideoTranscodingStatus::dispatch($modelType, $modelId, $videoUid)
                ->delay(now()->addMinutes(2));
        }

        return response()->json([
            'message' => 'Upload complete, video is now processing on Cloudflare',
            'video_uid' => $videoUid,
            'upload_job_id' => $uploadJob->id,
            'status' => 'processing',
            'cloudflare_state' => $state,
            'note' => $modelType && $modelId
                ? 'Transcoding status will be monitored automatically.'
                : 'Provide model_type and model_id to enable automatic transcoding monitoring.',
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

    /**
     * List all upload jobs with progress tracking
     */
    public function listUploadJobs(Request $request)
    {
        $status = $request->query('status'); // active, completed, failed, all
        $perPage = $request->query('per_page', 20);

        $query = UploadJob::query()->orderBy('created_at', 'desc');

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'completed') {
            $query->completed();
        } elseif ($status === 'failed') {
            $query->failed();
        }

        $jobs = $query->paginate($perPage);

        return response()->json([
            'message' => 'Upload jobs retrieved',
            'data' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'model_type' => $job->model_type,
                    'model_id' => $job->model_id,
                    'upload_method' => $job->upload_method,
                    'status' => $job->status,
                    'status_label' => $job->status_label,
                    'current_phase' => $job->current_phase,
                    'phase_label' => $job->phase_label,
                    'progress_percent' => $job->progress_percent,
                    'bytes_transferred' => $job->bytes_transferred,
                    'bytes_total' => $job->bytes_total,
                    'transfer_id' => $job->transfer_id,
                    'cloudflare_uid' => $job->cloudflare_uid,
                    'error_message' => $job->error_message,
                    'started_at' => $job->started_at?->toIso8601String(),
                    'completed_at' => $job->completed_at?->toIso8601String(),
                    'created_at' => $job->created_at->toIso8601String(),
                ];
            }),
            'pagination' => [
                'total' => $jobs->total(),
                'per_page' => $jobs->perPage(),
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
            ],
        ]);
    }

    /**
     * Get a specific upload job by ID
     */
    public function getUploadJob($id)
    {
        $job = UploadJob::find($id);

        if (!$job) {
            return response()->json([
                'message' => 'Upload job not found',
            ], 404);
        }

        // If job is for a magnet upload, also fetch Put.io status
        $putioStatus = null;
        if ($job->transfer_id && $job->isInProgress()) {
            $putioStatus = $this->putio->getTransferStatus($job->transfer_id);
        }

        // If job has a Cloudflare UID, fetch Cloudflare status
        $cloudflareStatus = null;
        if ($job->cloudflare_uid) {
            $cloudflareVideo = $this->cloudflare->getVideo($job->cloudflare_uid);
            if ($cloudflareVideo) {
                $cloudflareStatus = [
                    'state' => $cloudflareVideo['status']['state'] ?? 'unknown',
                    'ready' => $cloudflareVideo['readyToStream'] ?? false,
                    'duration' => $cloudflareVideo['duration'] ?? null,
                ];
            }
        }

        return response()->json([
            'message' => 'Upload job retrieved',
            'data' => [
                'id' => $job->id,
                'title' => $job->title,
                'model_type' => $job->model_type,
                'model_id' => $job->model_id,
                'upload_method' => $job->upload_method,
                'status' => $job->status,
                'status_label' => $job->status_label,
                'current_phase' => $job->current_phase,
                'phase_label' => $job->phase_label,
                'progress_percent' => $job->progress_percent,
                'bytes_transferred' => $job->bytes_transferred,
                'bytes_total' => $job->bytes_total,
                'transfer_id' => $job->transfer_id,
                'cloudflare_uid' => $job->cloudflare_uid,
                'error_message' => $job->error_message,
                'started_at' => $job->started_at?->toIso8601String(),
                'completed_at' => $job->completed_at?->toIso8601String(),
                'created_at' => $job->created_at->toIso8601String(),
            ],
            'putio' => $putioStatus ? [
                'status' => $putioStatus['status'],
                'percent_done' => $putioStatus['percent_done'] ?? 0,
                'size' => $putioStatus['size'] ?? null,
                'downloaded' => $putioStatus['downloaded'] ?? null,
            ] : null,
            'cloudflare' => $cloudflareStatus,
        ]);
    }

    /**
     * Get active upload jobs only (for dashboard widgets)
     */
    public function getActiveUploadJobs()
    {
        $jobs = $this->uploadJobService->getActiveJobs();

        return response()->json([
            'message' => 'Active upload jobs retrieved',
            'count' => $jobs->count(),
            'data' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'upload_method' => $job->upload_method,
                    'status' => $job->status,
                    'status_label' => $job->status_label,
                    'phase_label' => $job->phase_label,
                    'progress_percent' => $job->progress_percent,
                    'bytes_transferred' => $job->bytes_transferred,
                    'bytes_total' => $job->bytes_total,
                    'created_at' => $job->created_at->toIso8601String(),
                ];
            }),
        ]);
    }
}
