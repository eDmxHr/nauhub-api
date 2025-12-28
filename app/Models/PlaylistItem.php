<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlaylistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'playlist_id',
        'video_id',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // Playlist item belongs to a playlist
    public function playlist()
    {
        return $this->belongsTo(Playlist::class);
    }

    // Playlist item belongs to a video
    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
