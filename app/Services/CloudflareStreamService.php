<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Stream Service
 *
 * Handles video uploads, streaming, and management via Cloudflare Stream API
 */
class CloudflareStreamService
{
    protected $accountId;
    protected $apiToken;
    protected $customerSubdomain;
    protected $baseUrl;

    public function __construct()
    {
        $this->accountId = config('services.cloudflare.account_id');
        $this->apiToken = config('services.cloudflare.api_token');
        $this->customerSubdomain = config('services.cloudflare.customer_subdomain');
        $this->baseUrl = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/stream";
    }

    /**
     * Check if Cloudflare credentials are configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->accountId) && !empty($this->apiToken) && !empty($this->customerSubdomain);
    }

    /**
     * Upload a video file to Cloudflare Stream
     *
     * @param mixed $videoFile - The uploaded video file
     * @param array $metadata - Video metadata (name, requireSignedURLs, etc.)
     * @return array|null
     */
    public function uploadVideo($videoFile, array $metadata = [])
    {
        if (!$this->isConfigured()) {
            return $this->getMockResponse();
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->attach('file', file_get_contents($videoFile->getRealPath()), $videoFile->getClientOriginalName())
                ->post($this->baseUrl, [
                    'meta' => json_encode($metadata),
                    'requireSignedURLs' => $metadata['requireSignedURLs'] ?? false,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['result'] ?? null;
            }

            Log::error('Cloudflare upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Cloudflare upload exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Upload video from URL - Cloudflare fetches the URL directly
     * This is the recommended approach and has no file size limit
     *
     * @param string $videoUrl
     * @param array $metadata
     * @return array|null
     */
    public function uploadFromUrl(string $videoUrl, array $metadata = [])
    {
        if (!$this->isConfigured()) {
            return $this->getMockResponse();
        }

        try {
            // Extract requireSignedURLs from metadata if present
            $requireSignedURLs = $metadata['requireSignedURLs'] ?? false;

            // Remove requireSignedURLs from metadata as it goes top-level
            $meta = $metadata;
            unset($meta['requireSignedURLs']);

            // Build JSON payload for Cloudflare Stream Copy API
            $payload = [
                'url' => $videoUrl,
            ];

            // Add metadata if provided
            if (!empty($meta)) {
                $payload['meta'] = $meta;
            }

            // Add requireSignedURLs if explicitly set to true
            if ($requireSignedURLs === true) {
                $payload['requireSignedURLs'] = true;
            }

            Log::info('Cloudflare upload request', [
                'url_length' => strlen($videoUrl),
                'url_preview' => substr($videoUrl, 0, 100) . '...',
                'has_meta' => !empty($meta),
            ]);

            // Send as JSON (NOT form data) - this is the Copy API endpoint
            $response = Http::withToken($this->apiToken)
                ->post("{$this->baseUrl}/copy", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $result = $data['result'] ?? null;

                if ($result) {
                    Log::info('Cloudflare upload initiated', [
                        'uid' => $result['uid'] ?? 'unknown',
                        'status' => $result['status']['state'] ?? 'unknown',
                    ]);
                }

                return $result;
            }

            Log::error('Cloudflare URL upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Cloudflare URL upload exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a direct upload URL for one-time upload
     *
     * @param int $maxDurationSeconds Maximum duration in seconds (default: 6 hours)
     * @return array|null
     */
    public function createDirectUploadUrl(int $maxDurationSeconds = 21600)
    {
        if (!$this->isConfigured()) {
            return [
                'uploadURL' => 'https://upload.videodelivery.net/mock',
                'uid' => 'mock_' . uniqid(),
            ];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->baseUrl}/direct_upload", [
                    'maxDurationSeconds' => $maxDurationSeconds,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['result'] ?? null;
            }

            Log::error('Failed to create direct upload URL', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Create direct upload URL exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get video details from Cloudflare Stream
     *
     * @param string $videoUid
     * @return array|null
     */
    public function getVideo(string $videoUid)
    {
        if (!$this->isConfigured()) {
            return [
                'uid' => $videoUid,
                'status' => ['state' => 'ready'],
                'readyToStream' => true,
                'duration' => 3600,
                'playback' => [
                    'hls' => $this->getStreamUrl($videoUid),
                ],
                'thumbnail' => $this->getThumbnailUrl($videoUid),
            ];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->get("{$this->baseUrl}/{$videoUid}");

            if ($response->successful()) {
                $data = $response->json();
                return $data['result'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Cloudflare get video exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Delete a video from Cloudflare Stream
     *
     * @param string $videoUid
     * @return bool
     */
    public function deleteVideo(string $videoUid)
    {
        if (!$this->isConfigured()) {
            return true; // Mock success
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->delete("{$this->baseUrl}/{$videoUid}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Cloudflare delete video exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get the HLS streaming URL for a video
     *
     * @param string $videoUid
     * @return string
     */
    public function getStreamUrl(string $videoUid)
    {
        $subdomain = $this->customerSubdomain ?: 'customer-placeholder';
        return "https://{$subdomain}.cloudflarestream.com/{$videoUid}/manifest/video.m3u8";
    }

    /**
     * Get the HLS manifest URL for a video
     *
     * @param string $videoUid
     * @return string
     */
    public function getManifestUrl(string $videoUid)
    {
        return $this->getStreamUrl($videoUid);
    }

    /**
     * Get the DASH manifest URL for a video
     *
     * @param string $videoUid
     * @return string
     */
    public function getDashUrl(string $videoUid)
    {
        $subdomain = $this->customerSubdomain ?: 'customer-placeholder';
        return "https://{$subdomain}.cloudflarestream.com/{$videoUid}/manifest/video.mpd";
    }

    /**
     * Get the thumbnail URL for a video
     *
     * @param string $videoUid
     * @param string $time - Time in seconds (e.g., '1s', '30s')
     * @return string
     */
    public function getThumbnailUrl(string $videoUid, string $time = '1s')
    {
        $subdomain = $this->customerSubdomain ?: 'customer-placeholder';
        return "https://{$subdomain}.cloudflarestream.com/{$videoUid}/thumbnails/thumbnail.jpg?time={$time}";
    }

    /**
     * Get the embed iframe URL for a video
     *
     * @param string $videoUid
     * @return string
     */
    public function getEmbedUrl(string $videoUid)
    {
        $subdomain = $this->customerSubdomain ?: 'customer-placeholder';
        return "https://{$subdomain}.cloudflarestream.com/{$videoUid}/iframe";
    }

    /**
     * Get video upload status
     *
     * @param string $videoUid
     * @return array|null
     */
    public function getUploadStatus(string $videoUid)
    {
        $video = $this->getVideo($videoUid);

        if ($video) {
            return [
                'uid' => $video['uid'],
                'status' => $video['status']['state'] ?? 'unknown',
                'ready' => $video['readyToStream'] ?? false,
                'duration' => $video['duration'] ?? null,
                'thumbnail' => $this->getThumbnailUrl($videoUid),
            ];
        }

        return null;
    }

    /**
     * Create a signed URL token for private videos
     *
     * NOTE: Cloudflare Stream requires you to generate JWT tokens locally using your Stream signing key.
     * This method is a placeholder. To implement signed URLs:
     * 1. Get your Stream signing key from Cloudflare dashboard
     * 2. Use a JWT library (e.g., firebase/php-jwt) to generate tokens
     * 3. Include claims: kid (key ID), exp (expiration), nbf (not before), sub (video UID)
     *
     * @see https://developers.cloudflare.com/stream/viewing-videos/securing-your-stream/
     *
     * @param string $videoUid
     * @param int $expiresIn - Expiration time in seconds
     * @return string|null
     */
    public function createSignedToken(string $videoUid, int $expiresIn = 3600)
    {
        // TODO: Implement JWT token generation using Stream signing key
        // This requires adding the Stream signing key to .env and using a JWT library

        Log::warning('Signed tokens not yet implemented. Add Stream signing key and JWT library.');

        return null;
    }

    /**
     * Get signed URL for video playback (if using signed URLs)
     *
     * For now, returns regular URL. Implement createSignedToken() first.
     *
     * @param string $videoUid
     * @param int $expiresIn - Expiration time in seconds
     * @return string
     */
    public function getSignedUrl(string $videoUid, int $expiresIn = 3600): string
    {
        $token = $this->createSignedToken($videoUid, $expiresIn);

        if ($token) {
            return $this->getStreamUrl($videoUid) . '?token=' . $token;
        }

        // Return regular URL if token generation not implemented
        return $this->getStreamUrl($videoUid);
    }

    /**
     * Update video metadata
     *
     * @param string $videoUid
     * @param array $metadata
     * @return array
     */
    public function updateMetadata(string $videoUid, array $metadata): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => true,
                'uid' => $videoUid,
                'message' => 'PLACEHOLDER: Metadata update simulated.',
            ];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->baseUrl}/{$videoUid}", [
                    'meta' => $metadata,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'success' => false,
                'message' => 'Failed to update metadata',
            ];
        } catch (\Exception $e) {
            Log::error('Cloudflare update metadata exception', [
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload subtitle/caption file to a video
     *
     * @param string $videoUid Video UID
     * @param string $subtitleFilePath Path to subtitle file (VTT format)
     * @param string $language Language code (e.g., 'en', 'es', 'fr')
     * @param string|null $label Optional label for the caption track
     * @return array|null
     */
    public function uploadSubtitle(string $videoUid, string $subtitleFilePath, string $language, ?string $label = null): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        if (!file_exists($subtitleFilePath)) {
            Log::error('Subtitle file not found', ['path' => $subtitleFilePath]);
            return null;
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->attach('file', file_get_contents($subtitleFilePath), basename($subtitleFilePath))
                ->put("{$this->baseUrl}/{$videoUid}/captions/{$language}", [
                    'label' => $label ?? ucfirst($language),
                ]);

            if ($response->successful()) {
                Log::info('Subtitle uploaded successfully', [
                    'video_uid' => $videoUid,
                    'language' => $language,
                ]);

                return $response->json();
            }

            Log::error('Subtitle upload failed', [
                'video_uid' => $videoUid,
                'language' => $language,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Subtitle upload exception', [
                'video_uid' => $videoUid,
                'language' => $language,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all captions for a video
     *
     * @param string $videoUid
     * @return array
     */
    public function getCaptions(string $videoUid): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->get("{$this->baseUrl}/{$videoUid}/captions");

            if ($response->successful()) {
                $data = $response->json();
                return $data['result'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Get captions exception', [
                'video_uid' => $videoUid,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Delete a caption track
     *
     * @param string $videoUid
     * @param string $language
     * @return bool
     */
    public function deleteCaption(string $videoUid, string $language): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->delete("{$this->baseUrl}/{$videoUid}/captions/{$language}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Delete caption exception', [
                'video_uid' => $videoUid,
                'language' => $language,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get mock response for when credentials are not configured
     *
     * @return array
     */
    protected function getMockResponse(): array
    {
        return [
            'uid' => 'placeholder_' . uniqid(),
            'status' => ['state' => 'ready'],
            'readyToStream' => true,
            'duration' => 3600,
            'playback' => [
                'hls' => 'https://customer-placeholder.cloudflarestream.com/placeholder/manifest/video.m3u8',
                'dash' => 'https://customer-placeholder.cloudflarestream.com/placeholder/manifest/video.mpd',
            ],
            'thumbnail' => 'https://customer-placeholder.cloudflarestream.com/placeholder/thumbnails/thumbnail.jpg',
            'message' => 'PLACEHOLDER: Video upload simulated. Add Cloudflare credentials to enable real uploads.',
        ];
    }
}
