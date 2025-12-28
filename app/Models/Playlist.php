<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Playlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'profile_id',
        'name',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // Playlist belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Playlist belongs to a profile
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    // Playlist has many items (videos)
    public function items()
    {
        return $this->hasMany(PlaylistItem::class);
    }

    // Get videos through playlist items
    public function videos()
    {
        return $this->belongsToMany(Video::class, 'playlist_items')
            ->withTimestamps()
            ->orderBy('sort_order');
    }
}
