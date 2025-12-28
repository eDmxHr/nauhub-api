<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'avatar',
        'pin',
        'is_kids_profile',
    ];

    protected $hidden = [
        'pin', // Hide PIN from JSON responses
    ];

    protected $casts = [
        'is_kids_profile' => 'boolean',
    ];

    // Profile belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Profile has watch history
    public function watchHistory()
    {
        return $this->hasMany(WatchHistory::class);
    }

    // Profile has playlists
    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }
}
