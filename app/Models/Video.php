<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'description',
        'thumbnail',
        'banner',
        'cloudflare_video_uid',
        'status',
        'duration',
        'quality',         // e.g., "1080p", "720p", "4K", "HD", "SD"
        'release_year',
        'age_rating',      // e.g., "PG-13", "R", "TV-MA"
        'imdb_rating',     // e.g., 8.5, 7.2
        'is_published',
        'is_featured',
        'is_trending',
        'view_count',
    ];

    protected $casts = [
        'duration' => 'integer',
        'release_year' => 'integer',
        'imdb_rating' => 'decimal:1',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'is_trending' => 'boolean',
        'view_count' => 'integer',
    ];

    // Video has many seasons (for series/anime)
    public function seasons()
    {
        return $this->hasMany(Season::class);
    }

    // Video belongs to many categories
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'video_category')
            ->withTimestamps();
    }

    // Video belongs to many genres
    public function genres()
    {
        return $this->belongsToMany(Genre::class, 'video_genre')
            ->withTimestamps();
    }

    // Video has watch history
    public function watchHistory()
    {
        return $this->hasMany(WatchHistory::class);
    }

    // Video is in many playlists
    public function playlistItems()
    {
        return $this->hasMany(PlaylistItem::class);
    }

    // Helper: Check if video is a movie
    public function isMovie(): bool
    {
        return $this->type === 'movie';
    }

    // Helper: Check if video is a series
    public function isSeries(): bool
    {
        return in_array($this->type, ['series', 'anime']);
    }
}
