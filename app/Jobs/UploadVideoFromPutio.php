<?php

namespace App\Jobs;

use App\Services\TorrentToStreamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UploadVideoFromPutio implements ShouldQueue
{
    use Queueable;

    public $timeout = 1800; // 30 minutes timeout
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

            // TODO: Store video UID in database, send notification to admin, etc.
        } else {
            Log::error('Failed to process transfer to Cloudflare', [
                'transfer_id' => $this->transferId,
                'step' => $result['step'] ?? 'unknown',
                'message' => $result['message'] ?? 'Unknown error',
            ]);
        }
    }
}
