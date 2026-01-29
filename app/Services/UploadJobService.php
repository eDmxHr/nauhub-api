<?php

namespace App\Services;

use App\Models\UploadJob;
use Illuminate\Support\Facades\Log;

class UploadJobService
{
    /**
     * Create a new upload job for magnet upload
     */
    public function createMagnetUploadJob(
        string $modelType,
        ?int $modelId,
        int $transferId,
        string $title
    ): UploadJob {
        return UploadJob::create([
            'title' => $title,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'upload_method' => UploadJob::METHOD_MAGNET,
            'status' => UploadJob::STATUS_PENDING,
            'transfer_id' => $transferId,
        ]);
    }

    /**
     * Create a new upload job for direct file upload
     */
    public function createDirectUploadJob(
        string $modelType,
        ?int $modelId,
        string $title,
        int $fileSize
    ): UploadJob {
        return UploadJob::create([
            'title' => $title,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'upload_method' => UploadJob::METHOD_DIRECT,
            'status' => UploadJob::STATUS_UPLOADING,
            'current_phase' => UploadJob::PHASE_CLOUDFLARE_UPLOAD,
            'bytes_total' => $fileSize,
            'started_at' => now(),
        ]);
    }

    /**
     * Create a new upload job for TUS upload
     */
    public function createTusUploadJob(
        string $modelType,
        ?int $modelId,
        string $title,
        int $fileSize,
        ?string $cloudflareUid = null
    ): UploadJob {
        return UploadJob::create([
            'title' => $title,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'upload_method' => UploadJob::METHOD_TUS,
            'status' => UploadJob::STATUS_UPLOADING,
            'current_phase' => UploadJob::PHASE_CLOUDFLARE_UPLOAD,
            'bytes_total' => $fileSize,
            'cloudflare_uid' => $cloudflareUid,
            'started_at' => now(),
        ]);
    }

    /**
     * Create a new upload job for URL upload
     */
    public function createUrlUploadJob(
        string $modelType,
        ?int $modelId,
        string $title
    ): UploadJob {
        return UploadJob::create([
            'title' => $title,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'upload_method' => UploadJob::METHOD_URL,
            'status' => UploadJob::STATUS_UPLOADING,
            'current_phase' => UploadJob::PHASE_CLOUDFLARE_UPLOAD,
            'started_at' => now(),
        ]);
    }

    /**
     * Find upload job by transfer ID
     */
    public function findByTransferId(int $transferId): ?UploadJob
    {
        return UploadJob::where('transfer_id', $transferId)->first();
    }

    /**
     * Find upload job by Cloudflare UID
     */
    public function findByCloudflareUid(string $uid): ?UploadJob
    {
        return UploadJob::where('cloudflare_uid', $uid)->first();
    }

    /**
     * Get all active upload jobs
     */
    public function getActiveJobs()
    {
        return UploadJob::active()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get recent upload jobs (last 24 hours)
     */
    public function getRecentJobs(int $hours = 24)
    {
        return UploadJob::where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all upload jobs with pagination
     */
    public function getAllJobs(int $perPage = 20)
    {
        return UploadJob::orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Update download progress (from Put.io)
     */
    public function updateDownloadProgress(
        UploadJob $job,
        int $bytesDownloaded,
        int $bytesTotal
    ): void {
        $percent = $bytesTotal > 0 ? (int)(($bytesDownloaded / $bytesTotal) * 100) : 0;

        $job->update([
            'status' => UploadJob::STATUS_DOWNLOADING,
            'current_phase' => UploadJob::PHASE_PUTIO_DOWNLOAD,
            'progress_percent' => $percent,
            'bytes_transferred' => $bytesDownloaded,
            'bytes_total' => $bytesTotal,
        ]);
    }

    /**
     * Update upload progress (to Cloudflare)
     */
    public function updateUploadProgress(
        UploadJob $job,
        int $bytesUploaded,
        int $bytesTotal
    ): void {
        $percent = $bytesTotal > 0 ? (int)(($bytesUploaded / $bytesTotal) * 100) : 0;

        $job->update([
            'status' => UploadJob::STATUS_UPLOADING,
            'current_phase' => UploadJob::PHASE_CLOUDFLARE_UPLOAD,
            'progress_percent' => $percent,
            'bytes_transferred' => $bytesUploaded,
            'bytes_total' => $bytesTotal,
        ]);
    }

    /**
     * Sync Put.io transfer status to upload job
     */
    public function syncPutioStatus(UploadJob $job, array $transferStatus): void
    {
        $status = $transferStatus['status'] ?? 'UNKNOWN';
        $percentDone = $transferStatus['percent_done'] ?? 0;
        $size = $transferStatus['size'] ?? 0;
        $downloaded = $transferStatus['downloaded'] ?? 0;

        if ($status === 'COMPLETED' || $status === 'SEEDING') {
            // Transfer complete, ready for upload phase
            $job->update([
                'status' => UploadJob::STATUS_UPLOADING,
                'current_phase' => UploadJob::PHASE_CLOUDFLARE_UPLOAD,
                'progress_percent' => 0,
                'bytes_transferred' => 0,
                'bytes_total' => $size,
            ]);
        } elseif ($status === 'DOWNLOADING' || $status === 'IN_QUEUE') {
            $job->update([
                'status' => UploadJob::STATUS_DOWNLOADING,
                'current_phase' => UploadJob::PHASE_PUTIO_DOWNLOAD,
                'progress_percent' => $percentDone,
                'bytes_transferred' => $downloaded,
                'bytes_total' => $size,
            ]);
        } elseif ($status === 'ERROR') {
            $job->markAsFailed($transferStatus['error_message'] ?? 'Put.io transfer failed');
        }
    }

    /**
     * Clean up old completed jobs (older than X days)
     */
    public function cleanupOldJobs(int $days = 7): int
    {
        return UploadJob::where('status', UploadJob::STATUS_COMPLETED)
            ->where('completed_at', '<', now()->subDays($days))
            ->delete();
    }
}
