<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'model_type',
        'model_id',
        'upload_method',
        'status',
        'current_phase',
        'progress_percent',
        'bytes_transferred',
        'bytes_total',
        'transfer_id',
        'cloudflare_uid',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'bytes_transferred' => 'integer',
        'bytes_total' => 'integer',
        'transfer_id' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_DOWNLOADING = 'downloading';
    const STATUS_UPLOADING = 'uploading';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    const PHASE_PUTIO_DOWNLOAD = 'putio_download';
    const PHASE_CLOUDFLARE_UPLOAD = 'cloudflare_upload';
    const PHASE_CLOUDFLARE_PROCESSING = 'cloudflare_processing';

    const METHOD_MAGNET = 'magnet';
    const METHOD_DIRECT = 'direct';
    const METHOD_TUS = 'tus';
    const METHOD_URL = 'url';

    /**
     * Get the related model (Video or Episode)
     */
    public function model()
    {
        if ($this->model_type === 'video') {
            return $this->belongsTo(Video::class, 'model_id');
        }
        return $this->belongsTo(Episode::class, 'model_id');
    }

    /**
     * Get video relationship
     */
    public function video()
    {
        return $this->belongsTo(Video::class, 'model_id');
    }

    /**
     * Get episode relationship
     */
    public function episode()
    {
        return $this->belongsTo(Episode::class, 'model_id');
    }

    /**
     * Scope for active uploads
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_DOWNLOADING,
            self::STATUS_UPLOADING,
            self::STATUS_PROCESSING,
        ]);
    }

    /**
     * Scope for completed uploads
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed uploads
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Update progress
     */
    public function updateProgress(int $percent, ?int $bytesTransferred = null, ?int $bytesTotal = null): void
    {
        $data = ['progress_percent' => min(100, max(0, $percent))];

        if ($bytesTransferred !== null) {
            $data['bytes_transferred'] = $bytesTransferred;
        }
        if ($bytesTotal !== null) {
            $data['bytes_total'] = $bytesTotal;
        }

        $this->update($data);
    }

    /**
     * Mark as downloading
     */
    public function markAsDownloading(?int $bytesTotal = null): void
    {
        $this->update([
            'status' => self::STATUS_DOWNLOADING,
            'current_phase' => self::PHASE_PUTIO_DOWNLOAD,
            'started_at' => $this->started_at ?? now(),
            'bytes_total' => $bytesTotal ?? $this->bytes_total,
        ]);
    }

    /**
     * Mark as uploading
     */
    public function markAsUploading(?int $bytesTotal = null): void
    {
        $this->update([
            'status' => self::STATUS_UPLOADING,
            'current_phase' => self::PHASE_CLOUDFLARE_UPLOAD,
            'progress_percent' => 0,
            'bytes_transferred' => 0,
            'bytes_total' => $bytesTotal ?? $this->bytes_total,
        ]);
    }

    /**
     * Mark as processing (Cloudflare transcoding)
     */
    public function markAsProcessing(string $cloudflareUid): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'current_phase' => self::PHASE_CLOUDFLARE_PROCESSING,
            'cloudflare_uid' => $cloudflareUid,
            'progress_percent' => 100,
        ]);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'current_phase' => null,
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if upload is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_DOWNLOADING,
            self::STATUS_UPLOADING,
            self::STATUS_PROCESSING,
        ]);
    }

    /**
     * Get human-readable status
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_DOWNLOADING => 'Downloading from Put.io',
            self::STATUS_UPLOADING => 'Uploading to Cloudflare',
            self::STATUS_PROCESSING => 'Processing on Cloudflare',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown',
        };
    }

    /**
     * Get human-readable phase
     */
    public function getPhaseLabelAttribute(): string
    {
        return match($this->current_phase) {
            self::PHASE_PUTIO_DOWNLOAD => 'Downloading from Put.io',
            self::PHASE_CLOUDFLARE_UPLOAD => 'Uploading to Cloudflare',
            self::PHASE_CLOUDFLARE_PROCESSING => 'Processing on Cloudflare',
            default => $this->status_label,
        };
    }
}
