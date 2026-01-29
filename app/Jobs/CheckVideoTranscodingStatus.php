<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\Episode;
use App\Models\UploadJob;
use App\Services\CloudflareStreamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckVideoTranscodingStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 1;

    protected $modelType;
    protected $modelId;
    protected $cloudflareUid;
    protected $attemptNumber;

    /**
     * Create a new job instance.
     *
     * @param string $modelType - 'video' or 'episode'
     * @param int $modelId
     * @param string $cloudflareUid
     * @param int $attemptNumber - Current polling attempt
     */
    public function __construct(string $modelType, int $modelId, string $cloudflareUid, int $attemptNumber = 1)
    {
        $this->modelType = $modelType;
        $this->modelId = $modelId;
        $this->cloudflareUid = $cloudflareUid;
        $this->attemptNumber = $attemptNumber;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(CloudflareStreamService $cloudflare)
    {
        Log::info('Checking video transcoding status', [
            'model' => $this->modelType,
            'model_id' => $this->modelId,
            'cloudflare_uid' => $this->cloudflareUid,
            'attempt' => $this->attemptNumber,
        ]);

        // Get video status from Cloudflare
        $uploadStatus = $cloudflare->getUploadStatus($this->cloudflareUid);

        if (!$uploadStatus) {
            Log::error('Failed to get video status from Cloudflare', [
                'cloudflare_uid' => $this->cloudflareUid,
            ]);
            $this->updateModelStatus('error');
            return;
        }

        Log::info('Video transcoding status', [
            'cloudflare_uid' => $this->cloudflareUid,
            'status' => $uploadStatus['status'],
            'ready' => $uploadStatus['ready'],
        ]);

        // Check if transcoding is complete
        if ($uploadStatus['ready'] === true && $uploadStatus['status'] === 'ready') {
            Log::info('Video transcoding complete', [
                'cloudflare_uid' => $this->cloudflareUid,
                'duration' => $uploadStatus['duration'],
            ]);

            // Update the model to mark as ready and auto-publish
            $this->updateModelStatus('ready', $uploadStatus);
        } else {
            // Still processing - update status and schedule another check
            $this->updateModelStatus('processing');
            $this->scheduleNextCheck($uploadStatus);
        }
    }

    /**
     * Update the model status
     */
    protected function updateModelStatus(string $status, ?array $uploadStatus = null)
    {
        $model = $this->getModel();

        if ($model) {
            $updateData = ['status' => $status];

            // If ready, also update duration and set is_published to true
            if ($status === 'ready' && $uploadStatus) {
                if (isset($uploadStatus['duration']) && $uploadStatus['duration']) {
                    $updateData['duration'] = $uploadStatus['duration'];
                }
                $updateData['is_published'] = true;
            }

            $model->update($updateData);

            Log::info('Updated model status', [
                'model' => $this->modelType,
                'model_id' => $this->modelId,
                'status' => $status,
            ]);

            // Also update the upload job status
            $this->updateUploadJobStatus($status);
        }
    }

    /**
     * Update the upload job status based on transcoding result
     */
    protected function updateUploadJobStatus(string $status): void
    {
        // Find upload job by cloudflare UID
        $uploadJob = UploadJob::where('cloudflare_uid', $this->cloudflareUid)->first();

        if (!$uploadJob) {
            // Try finding by model type and ID
            $uploadJob = UploadJob::where('model_type', $this->modelType)
                ->where('model_id', $this->modelId)
                ->whereIn('status', [
                    UploadJob::STATUS_PROCESSING,
                    UploadJob::STATUS_UPLOADING,
                ])
                ->first();
        }

        if (!$uploadJob) {
            Log::debug('No upload job found to update', [
                'cloudflare_uid' => $this->cloudflareUid,
                'model_type' => $this->modelType,
                'model_id' => $this->modelId,
            ]);
            return;
        }

        if ($status === 'ready') {
            $uploadJob->markAsCompleted();
            Log::info('Upload job marked as completed', [
                'upload_job_id' => $uploadJob->id,
                'cloudflare_uid' => $this->cloudflareUid,
            ]);
        } elseif ($status === 'error') {
            $uploadJob->markAsFailed('Cloudflare transcoding failed or timed out');
            Log::info('Upload job marked as failed', [
                'upload_job_id' => $uploadJob->id,
                'cloudflare_uid' => $this->cloudflareUid,
            ]);
        }
    }

    /**
     * Schedule the next status check
     */
    protected function scheduleNextCheck(array $uploadStatus)
    {
        // Max attempts: 12 (2 hours with 10-minute intervals)
        $maxAttempts = 12;

        if ($this->attemptNumber >= $maxAttempts) {
            Log::warning('Video transcoding check timeout', [
                'cloudflare_uid' => $this->cloudflareUid,
                'attempts' => $this->attemptNumber,
                'status' => $uploadStatus['status'],
            ]);
            $this->updateModelStatus('error');
            return;
        }

        // Schedule next check in 10 minutes
        $nextAttempt = $this->attemptNumber + 1;
        $delay = now()->addMinutes(10);

        Log::info('Scheduling next transcoding check', [
            'cloudflare_uid' => $this->cloudflareUid,
            'next_attempt' => $nextAttempt,
            'delay' => $delay->toIso8601String(),
        ]);

        self::dispatch(
            $this->modelType,
            $this->modelId,
            $this->cloudflareUid,
            $nextAttempt
        )->delay($delay);
    }

    /**
     * Get the model instance
     *
     * @return Video|Episode|null
     */
    protected function getModel(): Video|Episode|null
    {
        if ($this->modelType === 'video') {
            return Video::find($this->modelId);
        } elseif ($this->modelType === 'episode') {
            return Episode::find($this->modelId);
        }

        return null;
    }
}
