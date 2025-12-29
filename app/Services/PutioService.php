<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Put.io Service
 *
 * Handles interactions with Put.io API for downloading videos via torrents/magnets
 */
class PutioService
{
  protected $apiToken;
  protected $baseUrl = 'https://api.put.io/v2';

  public function __construct()
  {
    $this->apiToken = config('services.putio.api_token');
  }

  /**
   * Check if Put.io API token is configured
   *
   * @return bool
   */
  public function isConfigured(): bool
  {
    return !empty($this->apiToken);
  }

  /**
   * Add a transfer (magnet link or torrent URL) to Put.io
   *
   * @param string $url Magnet link or torrent URL
   * @param int $parentId Parent folder ID (default: 0 for root)
   * @return array|null Transfer details
   */
  public function addTransfer(string $url, int $parentId = 0)
  {
    if (!$this->isConfigured()) {
      return [
        'success' => false,
        'message' => 'Put.io API token not configured',
      ];
    }

    try {
      $response = Http::post("{$this->baseUrl}/transfers/add", [
        'url' => $url,
        'parent_id' => $parentId,
        'oauth_token' => $this->apiToken,
      ]);

      if ($response->successful()) {
        $data = $response->json();

        if ($data['status'] === 'OK' && isset($data['transfer'])) {
          return [
            'success' => true,
            'transfer' => $data['transfer'],
          ];
        }
      }


      return [
        'success' => false,
        'message' => 'Failed to add transfer to Put.io',
      ];
    } catch (\Exception $e) {
      Log::error('Put.io add transfer exception', [
        'message' => $e->getMessage(),
      ]);
      return [
        'success' => false,
        'message' => $e->getMessage(),
      ];
    }
  }

  /**
   * Get transfer status
   *
   * @param int $transferId Transfer ID
   * @return array|null Transfer status
   */
  public function getTransferStatus(int $transferId)
  {
    if (!$this->isConfigured()) {
      return null;
    }

    try {
      $response = Http::get("{$this->baseUrl}/transfers/{$transferId}", [
        'oauth_token' => $this->apiToken,
      ]);

      if ($response->successful()) {
        $data = $response->json();
        return $data['transfer'] ?? null;
      }

      return null;
    } catch (\Exception $e) {
      Log::error('Put.io get transfer status exception', [
        'message' => $e->getMessage(),
      ]);
      return null;
    }
  }

  /**
   * List all transfers
   *
   * @return array Transfers list
   */
  public function listTransfers()
  {
    if (!$this->isConfigured()) {
      return [];
    }

    try {
      $response = Http::get("{$this->baseUrl}/transfers/list", [
        'oauth_token' => $this->apiToken,
      ]);

      if ($response->successful()) {
        $data = $response->json();
        return $data['transfers'] ?? [];
      }

      return [];
    } catch (\Exception $e) {
      Log::error('Put.io list transfers exception', [
        'message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Get file details
   *
   * @param int $fileId Put.io file ID
   * @return array|null File details
   */
  public function getFile(int $fileId)
  {
    if (!$this->isConfigured()) {
      return null;
    }

    try {
      $response = Http::get("{$this->baseUrl}/files/{$fileId}", [
        'oauth_token' => $this->apiToken,
      ]);

      if ($response->successful()) {
        $data = $response->json();
        return $data['file'] ?? null;
      }

      return null;
    } catch (\Exception $e) {
      Log::error('Put.io get file exception', [
        'message' => $e->getMessage(),
      ]);
      return null;
    }
  }

  /**
   * Get direct download URL for a file
   *
   * This constructs the download URL in the format:
   * https://api.put.io/v2/files/{fileId}/download/{encodedFilename}?oauth_token={token}
   *
   * This format is compatible with Cloudflare Stream's upload-from-URL feature.
   *
   * @param int $fileId Put.io file ID
   * @return string|null Download URL
   */
  public function getFileUrl(int $fileId)
  {
    if (!$this->isConfigured()) {
      return null;
    }

    try {
      // First, get the file details to retrieve the filename
      $file = $this->getFile($fileId);

      if (!$file) {
        Log::error('Put.io file not found', ['file_id' => $fileId]);
        return null;
      }

      $filename = $file['name'];
      $encodedFilename = rawurlencode($filename);

      // Construct the download URL with filename (compatible with Cloudflare)
      $downloadUrl = "{$this->baseUrl}/files/{$fileId}/download/{$encodedFilename}?oauth_token={$this->apiToken}";

      return $downloadUrl;
    } catch (\Exception $e) {
      Log::error('Put.io get file URL exception', [
        'file_id' => $fileId,
        'message' => $e->getMessage(),
      ]);
      return null;
    }
  }

  /**
   * Delete a file from Put.io
   *
   * @param int $fileId Put.io file ID
   * @return bool Success status
   */
  public function deleteFile(int $fileId): bool
  {
    if (!$this->isConfigured()) {
      return false;
    }

    try {
      $response = Http::post("{$this->baseUrl}/files/delete", [
        'file_ids' => $fileId,
        'oauth_token' => $this->apiToken,
      ]);

      return $response->successful();
    } catch (\Exception $e) {
      Log::error('Put.io delete file exception', [
        'message' => $e->getMessage(),
      ]);
      return false;
    }
  }

  /**
   * Cancel a transfer
   *
   * @param int $transferId Transfer ID
   * @return bool Success status
   */
  public function cancelTransfer(int $transferId): bool
  {
    if (!$this->isConfigured()) {
      return false;
    }

    try {
      $response = Http::post("{$this->baseUrl}/transfers/cancel", [
        'transfer_ids' => $transferId,
        'oauth_token' => $this->apiToken,
      ]);

      return $response->successful();
    } catch (\Exception $e) {
      Log::error('Put.io cancel transfer exception', [
        'message' => $e->getMessage(),
      ]);
      return false;
    }
  }

  /**
   * List files in a folder
   *
   * @param int $parentId Folder ID (default: 0 for root)
   * @return array Files list
   */
  public function listFiles(int $parentId = 0): array
  {
    if (!$this->isConfigured()) {
      return [];
    }

    try {
      $response = Http::get("{$this->baseUrl}/files/list", [
        'parent_id' => $parentId,
        'oauth_token' => $this->apiToken,
      ]);

      if ($response->successful()) {
        $data = $response->json();
        return $data['files'] ?? [];
      }

      return [];
    } catch (\Exception $e) {
      Log::error('Put.io list files exception', [
        'message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Wait for transfer to complete and return file ID
   *
   * @param int $transferId Transfer ID
   * @param int $maxAttempts Maximum polling attempts (default: 60 = 10 minutes with 10s interval)
   * @param int $sleepSeconds Sleep between attempts (default: 10)
   * @return array Result with file_id or error
   */
  public function waitForTransferCompletion(int $transferId, int $maxAttempts = 60, int $sleepSeconds = 10): array
  {
    $attempts = 0;

    while ($attempts < $maxAttempts) {
      $transfer = $this->getTransferStatus($transferId);

      if (!$transfer) {
        return [
          'success' => false,
          'message' => 'Transfer not found',
        ];
      }

      // Check if transfer is completed
      if ($transfer['status'] === 'COMPLETED' || $transfer['status'] === 'SEEDING') {
        return [
          'success' => true,
          'file_id' => $transfer['file_id'],
          'name' => $transfer['name'],
          'size' => $transfer['size'],
        ];
      }

      // Check if transfer failed
      if ($transfer['status'] === 'ERROR') {
        return [
          'success' => false,
          'message' => 'Transfer failed: ' . ($transfer['error_message'] ?? 'Unknown error'),
        ];
      }

      $attempts++;
      sleep($sleepSeconds);
    }

    return [
      'success' => false,
      'message' => 'Transfer timeout - still downloading after ' . ($maxAttempts * $sleepSeconds / 60) . ' minutes',
    ];
  }
}
