<?php

namespace App\Jobs;

use App\Services\TorrentToStreamService;
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

    /**
     * Create a new job instance.
     */
    public function __construct(int $transferId, string $title, ?string $description = null)
    {
        $this->transferId = $transferId;
        $this->title = $title;
        $this->description = $description;
    }

    /**
     * Execute the job.
     */
    public function handle(TorrentToStreamService $torrentService): void
    {
        Log::info('Starting Put.io to Cloudflare upload job', [
            'transfer_id' => $this->transferId,
        ]);

        // Prepare metadata
        $metadata = [
            'name' => $this->title,
            'requireSignedURLs' => false,
        ];

        if ($this->description) {
            $metadata['description'] = $this->description;
        }

        // Process completed transfer using the service
        $result = $torrentService->processCompletedTransfer($this->transferId, $metadata);

        if ($result['success']) {
            Log::info('Successfully uploaded to Cloudflare', [
                'transfer_id' => $this->transferId,
                'cloudflare_uid' => $result['stream_uid'],
                'status' => $result['stream_status'],
            ]);

            // Update Video or Episode with actual Cloudflare UID
            $this->updateDatabaseRecord($result['stream_uid']);
        } else {
            Log::error('Failed to process transfer to Cloudflare', [
                'transfer_id' => $this->transferId,
                'step' => $result['step'] ?? 'unknown',
                'message' => $result['message'] ?? 'Unknown error',
            ]);
        }
    }

    /**
     * Update Video or Episode record with actual Cloudflare UID
     */
    protected function updateDatabaseRecord(string $cloudflareUid): void
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

            // Start monitoring transcoding status (first check in 5 minutes)
            \App\Jobs\CheckVideoTranscodingStatus::dispatch('video', $video->id, $cloudflareUid)
                ->delay(now()->addMinutes(5));

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
