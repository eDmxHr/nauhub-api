<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WatchHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'profile_id',
        'video_id',
        'episode_id',
        'progress_seconds',
        'duration_seconds',
        'completed',
        'last_watched_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'last_watched_at' => 'datetime',
    ];

    // Watch history belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Watch history belongs to a profile
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    // Watch history belongs to a video
    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    // Watch history might belong to an episode (for series/anime)
    public function episode()
    {
        return $this->belongsTo(Episode::class);
    }

    // Calculate watch progress percentage
    public function getProgressPercentageAttribute(): int
    {
        if (!$this->duration_seconds || $this->duration_seconds == 0) {
            return 0;
        }
        return (int) (($this->progress_seconds / $this->duration_seconds) * 100);
    }
}
