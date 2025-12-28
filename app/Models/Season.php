<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'season_number',
        'title',
        'description',
        'thumbnail',
        'release_year',
        'is_published',
    ];

    protected $casts = [
        'season_number' => 'integer',
        'release_year' => 'integer',
        'is_published' => 'boolean',
    ];

    // Season belongs to a video
    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    // Season has many episodes
    public function episodes()
    {
        return $this->hasMany(Episode::class);
    }
}
