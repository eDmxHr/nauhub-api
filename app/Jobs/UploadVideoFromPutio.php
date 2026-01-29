<?php

namespace App\Jobs;

use App\Models\UploadJob;
use App\Services\TorrentToStreamService;
use App\Services\UploadJobService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UploadVideoFromPutio implements ShouldQueue
{
  use Queueable;

  public $timeout = 7200; // 2 hours timeout (for large file downloads + TUS upload)
  public $tries = 1; // Only try once

  protected $transferId;
  protected $title;
  protected $description;
  protected $modelType;
  protected $modelId;

  /**
   * Create a new job instance.
   */
  public function __construct(int $transferId, string $title, ?string $description = null, ?string $modelType = null, ?int $modelId = null)
  {
    $this->transferId = $transferId;
    $this->title = $title;
    $this->description = $description;
    $this->modelType = $modelType;
    $this->modelId = $modelId;
  }

  /**
   * Execute the job.
   */
  public function handle(TorrentToStreamService $torrentService, UploadJobService $uploadJobService): void
  {
    Log::info('🚀 [MAGNET UPLOAD] Starting Put.io to Cloudflare upload job', [
      'transfer_id' => $this->transferId,
      'title' => $this->title,
    ]);

    // Find or create upload job for progress tracking
    $uploadJob = $uploadJobService->findByTransferId($this->transferId);

    if (!$uploadJob) {
      // Create upload job if not exists (for backwards compatibility)
      $uploadJob = $uploadJobService->createMagnetUploadJob(
        $this->modelType ?? 'video',
        $this->modelId,
        $this->transferId,
        $this->title
      );
    }

    // Mark job as started
    $uploadJob->update(['started_at' => now()]);

    // Check initial transfer status
    $putioService = app(\App\Services\PutioService::class);
    $transferStatus = $putioService->getTransferStatus($this->transferId);

    if ($transferStatus) {
      Log::info('📊 [MAGNET UPLOAD] Initial Put.io transfer status', [
        'transfer_id' => $this->transferId,
        'status' => $transferStatus['status'],
        'percent_done' => $transferStatus['percent_done'] ?? 0,
        'name' => $transferStatus['name'] ?? 'Unknown',
      ]);

      // Sync initial status to upload job
      $uploadJobService->syncPutioStatus($uploadJob, $transferStatus);
    }

    // Prepare metadata
    $metadata = [
      'name' => $this->title,
      'requireSignedURLs' => false,
    ];

    if ($this->description) {
      $metadata['description'] = $this->description;
    }

    Log::info('⏳ [MAGNET UPLOAD] Waiting for Put.io transfer to complete', [
      'transfer_id' => $this->transferId,
    ]);

    // Process completed transfer using the service (with upload job for progress tracking)
    $result = $torrentService->processCompletedTransfer($this->transferId, $metadata, $uploadJob);

    if ($result['success']) {
      Log::info('✅ [MAGNET UPLOAD] Successfully uploaded to Cloudflare', [
        'transfer_id' => $this->transferId,
        'cloudflare_uid' => $result['stream_uid'],
        'status' => $result['stream_status'],
        'step' => 'completed',
      ]);

      // Mark upload job as processing (Cloudflare is now transcoding)
      $uploadJob->markAsProcessing($result['stream_uid']);

      // Update Video or Episode with actual Cloudflare UID
      $this->updateDatabaseRecord($result['stream_uid'], $uploadJob);
    } else {
      Log::error('❌ [MAGNET UPLOAD] Failed to process transfer to Cloudflare', [
        'transfer_id' => $this->transferId,
        'step' => $result['step'] ?? 'unknown',
        'message' => $result['message'] ?? 'Unknown error',
        'status' => $result['status'] ?? 'unknown',
      ]);

      // Mark upload job as failed
      $uploadJob->markAsFailed($result['message'] ?? 'Unknown error');
    }
  }

  /**
   * Update Video or Episode record with actual Cloudflare UID
   */
  protected function updateDatabaseRecord(string $cloudflareUid, ?UploadJob $uploadJob = null): void
  {
    $tempUid = 'processing_transfer_' . $this->transferId;

    // Try to find in Videos table
    $video = \App\Models\Video::where('cloudflare_video_uid', $tempUid)->first();

    if ($video) {
      $video->update([
        'cloudflare_video_uid' => $cloudflareUid,
        'status' => 'processing',
        'is_published' => false, // Don't publish until transcoding is complete
      ]);
      Log::info('Updated video with Cloudflare UID', [
        'video_id' => $video->id,
        'cloudflare_uid' => $cloudflareUid,
      ]);

      // Update upload job with model reference
      if ($uploadJob) {
        $uploadJob->update([
          'model_type' => 'video',
          'model_id' => $video->id,
        ]);
      }

      // Start monitoring transcoding status (first check in two minutes)
      \App\Jobs\CheckVideoTranscodingStatus::dispatch('video', $video->id, $cloudflareUid)
        ->delay(now()->addMinutes(2));

      return;
    }

    // Try to find in Episodes table
    $episode = \App\Models\Episode::where('cloudflare_video_uid', $tempUid)->first();

    if ($episode) {
      $episode->update([
        'cloudflare_video_uid' => $cloudflareUid,
        'status' => 'processing',
        'is_published' => false, // Don't publish until transcoding is complete
      ]);
      Log::info('Updated episode with Cloudflare UID', [
        'episode_id' => $episode->id,
        'cloudflare_uid' => $cloudflareUid,
      ]);

      // Update upload job with model reference
      if ($uploadJob) {
        $uploadJob->update([
          'model_type' => 'episode',
          'model_id' => $episode->id,
        ]);
      }

      // Start monitoring transcoding status (first check in 5 minutes)
      \App\Jobs\CheckVideoTranscodingStatus::dispatch('episode', $episode->id, $cloudflareUid)
        ->delay(now()->addMinutes(5));

      return;
    }

    Log::warning('No video or episode found with transfer ID', [
      'transfer_id' => $this->transferId,
      'temp_uid' => $tempUid,
    ]);
  }
}
