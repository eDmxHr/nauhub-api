<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Episode extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'episode_number',
        'title',
        'description',
        'thumbnail',
        'cloudflare_video_uid',
        'status',
        'duration',
        'quality',         // e.g., "1080p", "720p", "4K", "HD", "SD"
        'release_date',
        'is_published',
        'view_count',
    ];

    protected $casts = [
        'episode_number' => 'integer',
        'duration' => 'integer',
        'release_date' => 'date',
        'is_published' => 'boolean',
        'view_count' => 'integer',
    ];

    // Episode belongs to a season
    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    // Episode has watch history
    public function watchHistory()
    {
        return $this->hasMany(WatchHistory::class);
    }
}
