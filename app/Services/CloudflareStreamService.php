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
     * Upload a file from local filesystem to Cloudflare Stream using TUS protocol
     *
     * @param string $filePath - Path to the video file
     * @param string $fileName - Original filename
     * @param array $metadata - Video metadata
     * @return array|null
     */
    public function uploadFromFile(string $filePath, string $fileName, array $metadata = [])
    {
        if (!$this->isConfigured()) {
            return $this->getMockResponse();
        }

        try {
            $fileSize = filesize($filePath);

            // For files larger than 200MB, use TUS resumable upload
            if ($fileSize > 200 * 1024 * 1024) {
                Log::info('Using TUS resumable upload for large file', [
                    'size' => $fileSize,
                    'file' => $fileName,
                ]);
                return $this->uploadUsingTus($filePath, $fileName, $metadata);
            }

            // For smaller files, use direct upload
            Log::info('Using direct upload for small file', [
                'size' => $fileSize,
                'file' => $fileName,
            ]);

            // Extract requireSignedURLs from metadata
            $requireSignedURLs = $metadata['requireSignedURLs'] ?? false;
            $meta = $metadata;
            unset($meta['requireSignedURLs']);

            // Use cURL for file uploads
            $ch = curl_init();

            $postFields = [
                'file' => new \CURLFile($filePath, mime_content_type($filePath), $fileName),
            ];

            if (!empty($meta)) {
                foreach ($meta as $key => $value) {
                    $postFields["meta[{$key}]"] = $value;
                }
            }

            if ($requireSignedURLs) {
                $postFields['requireSignedURLs'] = 'true';
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiToken,
                ],
                CURLOPT_TIMEOUT => 7200, // 2 hour timeout
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Log::error('Cloudflare upload cURL error', ['error' => $error]);
                return null;
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true);
                Log::info('Cloudflare upload successful', ['uid' => $data['result']['uid'] ?? 'unknown']);
                return $data['result'] ?? null;
            }

            Log::error('Cloudflare upload failed', [
                'status' => $httpCode,
                'body' => $response,
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
     * Upload large file using TUS resumable upload protocol
     *
     * @param string $filePath
     * @param string $fileName
     * @param array $metadata
     * @return array|null
     */
    protected function uploadUsingTus(string $filePath, string $fileName, array $metadata = [])
    {
        try {
            $fileSize = filesize($filePath);

            // Step 1: Create TUS upload session
            // Separate TUS metadata (headers) from POST body metadata
            $tusMetadata = [];
            $bodyMetadata = [
                'maxDurationSeconds' => 21600,
            ];

            // Handle requireSignedURLs in POST body
            if (isset($metadata['requireSignedURLs'])) {
                $bodyMetadata['requireSignedURLs'] = (bool) $metadata['requireSignedURLs'];
            }

            // Add other metadata to TUS headers (excluding requireSignedURLs)
            foreach ($metadata as $key => $value) {
                if ($key !== 'requireSignedURLs' && $key !== 'name') {
                    $tusMetadata[] = $key . ' ' . base64_encode($value);
                }
            }
            $tusMetadata[] = 'name ' . base64_encode($fileName);

            // Capture response headers
            $responseHeaders = [];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($bodyMetadata),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiToken,
                    'Tus-Resumable: 1.0.0',
                    'Upload-Length: ' . $fileSize,
                    'Upload-Metadata: ' . implode(',', $tusMetadata),
                    'Content-Type: application/json',
                ],
                CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$responseHeaders) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) return $len;
                    $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
                    return $len;
                }
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 201) {
                Log::error('TUS session creation failed', ['status' => $httpCode, 'body' => $response]);
                return null;
            }

            // TUS returns upload URL in Location header and stream UID in stream-media-id header
            $uploadUrl = $responseHeaders['location'] ?? null;
            $videoUid = $responseHeaders['stream-media-id'] ?? null;

            // Log the full response for debugging
            Log::info('TUS session response', [
                'status' => $httpCode,
                'headers' => $responseHeaders,
                'upload_url' => $uploadUrl,
                'video_uid' => $videoUid,
            ]);

            if (!$uploadUrl || !$videoUid) {
                Log::error('TUS response missing upload URL or UID', [
                    'headers' => $responseHeaders,
                ]);
                return null;
            }

            Log::info('TUS session created', ['uid' => $videoUid, 'upload_url' => $uploadUrl]);

            // Step 2: Upload file in chunks using TUS protocol
            $fp = fopen($filePath, 'rb');
            $offset = 0;
            $chunkSize = 50 * 1024 * 1024; // 50MB chunks

            while (!feof($fp)) {
                $chunk = fread($fp, $chunkSize);
                $chunkLength = strlen($chunk);

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $uploadUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => 'PATCH',
                    CURLOPT_POSTFIELDS => $chunk,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $this->apiToken,
                        'Tus-Resumable: 1.0.0',
                        'Upload-Offset: ' . $offset,
                        'Content-Type: application/offset+octet-stream',
                        'Content-Length: ' . $chunkLength,
                    ],
                    CURLOPT_TIMEOUT => 3600,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 204) {
                    Log::error('TUS chunk upload failed', [
                        'status' => $httpCode,
                        'offset' => $offset,
                        'body' => $response,
                    ]);
                    fclose($fp);
                    return null;
                }

                $offset += $chunkLength;
                Log::info('TUS chunk uploaded', [
                    'offset' => $offset,
                    'total' => $fileSize,
                    'progress' => round(($offset / $fileSize) * 100, 2) . '%',
                ]);
            }

            fclose($fp);

            Log::info('TUS upload completed', ['uid' => $videoUid]);

            // Return video details
            return [
                'uid' => $videoUid,
                'status' => ['state' => 'pending'],
                'readyToStream' => false,
            ];

        } catch (\Exception $e) {
            Log::error('TUS upload exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
     * Create a TUS resumable upload endpoint for client-side uploads
     *
     * @param int $fileSize File size in bytes
     * @param int|null $maxDurationSeconds Maximum duration in seconds (optional - only set if known)
     * @return array|null Returns array with 'tus_endpoint' (Location header) and optionally 'uid'
     */
    public function createTusUploadEndpoint(int $fileSize, ?int $maxDurationSeconds = null)
    {
        if (!$this->isConfigured()) {
            return [
                'tus_endpoint' => 'https://upload.videodelivery.net/mock-tus',
                'uid' => 'mock_' . uniqid(),
            ];
        }

        try {
            // Prepare POST body metadata (sent as JSON)
            // Only include maxDurationSeconds if explicitly provided
            $bodyMetadata = [];
            if ($maxDurationSeconds !== null) {
                $bodyMetadata['maxDurationSeconds'] = $maxDurationSeconds;
            }

            // Prepare TUS Upload-Metadata header (base64 encoded)
            // Format: key base64value,key2 base64value2
            // Only include maxDurationSeconds in metadata if provided
            $uploadMetadata = [];
            if ($maxDurationSeconds !== null) {
                $uploadMetadata[] = 'maxDurationSeconds ' . base64_encode((string)$maxDurationSeconds);
            }
            $uploadMetadataHeader = !empty($uploadMetadata) ? implode(',', $uploadMetadata) : '';

            // Call Cloudflare TUS endpoint with direct_user=true
            $url = "{$this->baseUrl}?direct_user=true";

            // Use cURL to capture response headers
            $ch = curl_init();
            $responseHeaders = [];

            // Build headers array
            $headers = [
                'Authorization: Bearer ' . $this->apiToken,
                'Tus-Resumable: 1.0.0',
                'Upload-Length: ' . $fileSize,
                'Content-Type: application/json',
            ];
            
            // Only add Upload-Metadata header if we have metadata
            if (!empty($uploadMetadataHeader)) {
                $headers[] = 'Upload-Metadata: ' . $uploadMetadataHeader;
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($bodyMetadata),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$responseHeaders) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) return $len;
                    $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
                    return $len;
                },
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Log::error('TUS endpoint creation cURL error', ['error' => $error]);
                return null;
            }

            if ($httpCode === 201) {
                // TUS endpoint returns 201 Created with Location header
                $tusEndpoint = $responseHeaders['location'] ?? null;
                $videoUid = $responseHeaders['stream-media-id'] ?? null;

                if (!$tusEndpoint) {
                    Log::error('TUS endpoint creation missing Location header', [
                        'status' => $httpCode,
                        'headers' => $responseHeaders,
                        'body' => $response,
                    ]);
                    return null;
                }

                Log::info('TUS upload endpoint created successfully', [
                    'tus_endpoint' => $tusEndpoint,
                    'video_uid' => $videoUid,
                    'file_size' => $fileSize,
                ]);

                return [
                    'tus_endpoint' => $tusEndpoint,
                    'uid' => $videoUid,
                ];
            }

            // Handle specific error cases
            $errorDetails = [
                'status' => $httpCode,
                'headers' => $responseHeaders,
                'body' => $response,
                'file_size' => $fileSize,
            ];

            // Parse error response if available
            if ($response) {
                $errorData = json_decode($response, true);
                if ($errorData && isset($errorData['errors'])) {
                    $errorDetails['cloudflare_errors'] = $errorData['errors'];
                }
            }

            // Check for common error scenarios
            if ($httpCode === 400) {
                Log::error('TUS endpoint creation failed: Bad Request', $errorDetails);
            } elseif ($httpCode === 401) {
                Log::error('TUS endpoint creation failed: Unauthorized - Check API token', $errorDetails);
            } elseif ($httpCode === 403) {
                Log::error('TUS endpoint creation failed: Forbidden - Check account permissions', $errorDetails);
            } elseif ($httpCode === 429) {
                Log::error('TUS endpoint creation failed: Rate Limited - Too many requests', $errorDetails);
            } elseif ($httpCode === 409) {
                Log::error('TUS endpoint creation failed: Conflict - Endpoint may already exist', $errorDetails);
            } else {
                Log::error('Failed to create TUS upload endpoint', $errorDetails);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Create TUS upload endpoint exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Check the current upload offset for a TUS upload (for resumability)
     *
     * @param string $tusEndpoint The TUS upload endpoint URL
     * @return array|null Returns array with 'upload_offset' and 'upload_length', or null on error
     */
    public function checkTusUploadOffset(string $tusEndpoint)
    {
        if (!$this->isConfigured()) {
            return [
                'upload_offset' => 0,
                'upload_length' => 0,
            ];
        }

        try {
            // Use HEAD request to check current upload status
            $ch = curl_init();
            $responseHeaders = [];

            curl_setopt_array($ch, [
                CURLOPT_URL => $tusEndpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'HEAD',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiToken,
                    'Tus-Resumable: 1.0.0',
                ],
                CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$responseHeaders) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) return $len;
                    $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
                    return $len;
                },
            ]);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Log::error('TUS offset check cURL error', ['error' => $error]);
                return null;
            }

            if ($httpCode === 200 || $httpCode === 204) {
                $uploadOffset = isset($responseHeaders['upload-offset']) ? (int)$responseHeaders['upload-offset'] : 0;
                $uploadLength = isset($responseHeaders['upload-length']) ? (int)$responseHeaders['upload-length'] : 0;

                Log::info('TUS upload offset checked', [
                    'tus_endpoint' => $tusEndpoint,
                    'upload_offset' => $uploadOffset,
                    'upload_length' => $uploadLength,
                    'progress' => $uploadLength > 0 ? round(($uploadOffset / $uploadLength) * 100, 2) . '%' : '0%',
                ]);

                return [
                    'upload_offset' => $uploadOffset,
                    'upload_length' => $uploadLength,
                    'progress_percent' => $uploadLength > 0 ? round(($uploadOffset / $uploadLength) * 100, 2) : 0,
                ];
            }

            Log::error('Failed to check TUS upload offset', [
                'status' => $httpCode,
                'headers' => $responseHeaders,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Check TUS upload offset exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
     * Get available video qualities for a video
     *
     * @param string $videoUid
     * @return array - Returns array of available qualities with processing status
     */
    public function getAvailableQualities(string $videoUid): array
    {
        $video = $this->getVideo($videoUid);

        if (!$video) {
            return [
                'ready' => false,
                'qualities' => [],
                'status' => 'error',
            ];
        }

        $result = [
            'ready' => $video['readyToStream'] ?? false,
            'status' => $video['status']['state'] ?? 'unknown',
            'qualities' => [],
            'input' => [
                'width' => $video['input']['width'] ?? null,
                'height' => $video['input']['height'] ?? null,
            ],
        ];

        // Check playback variants for available qualities
        if (isset($video['playback']['hls'])) {
            // HLS is available - fetch the manifest to detect qualities
            $result['hls_available'] = true;
        }

        // Cloudflare doesn't directly expose quality list in API
        // But we can infer based on input resolution and status
        if ($result['ready'] && isset($video['input']['height'])) {
            $inputHeight = $video['input']['height'];

            // Cloudflare generates these qualities based on input
            $possibleQualities = [
                ['height' => 1080, 'label' => '1080p'],
                ['height' => 720, 'label' => '720p'],
                ['height' => 480, 'label' => '480p'],
                ['height' => 360, 'label' => '360p'],
            ];

            foreach ($possibleQualities as $quality) {
                if ($quality['height'] <= $inputHeight) {
                    $result['qualities'][] = $quality['label'];
                }
            }
        }

        return $result;
    }

    /**
     * Check if video transcoding is complete with all qualities
     *
     * @param string $videoUid
     * @return bool
     */
    public function isTranscodingComplete(string $videoUid): bool
    {
        $qualities = $this->getAvailableQualities($videoUid);

        // Video is considered fully transcoded when:
        // 1. Status is ready
        // 2. readyToStream is true
        return $qualities['ready'] === true && $qualities['status'] === 'ready';
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
