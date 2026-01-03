<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfig extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'group',
        'is_public',
        'is_editable',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_editable' => 'boolean',
    ];

    /**
     * Get the typed value
     */
    public function getTypedValue()
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json', 'array' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Set value with automatic type conversion
     */
    public function setTypedValue($value): void
    {
        $this->value = match ($this->type) {
            'boolean' => $value ? 'true' : 'false',
            'json', 'array' => json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Static helper to get a config value
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("config.{$key}", 3600, function () use ($key, $default) {
            $config = self::where('key', $key)->first();
            return $config ? $config->getTypedValue() : $default;
        });
    }

    /**
     * Static helper to set a config value
     */
    public static function set(string $key, $value, ?string $type = null, ?string $description = null, string $group = 'general'): self
    {
        $config = self::updateOrCreate(
            ['key' => $key],
            [
                'type' => $type ?? self::guessType($value),
                'description' => $description,
                'group' => $group,
            ]
        );

        $config->setTypedValue($value);
        $config->save();

        // Clear cache
        Cache::forget("config.{$key}");

        return $config;
    }

    /**
     * Guess the type from the value
     */
    protected static function guessType($value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'array',
            default => 'string',
        };
    }

    /**
     * Clear all config cache
     */
    public static function clearCache(): void
    {
        Cache::flush();
    }

    /**
     * Scope for public configs
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope by group
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope for editable configs
     */
    public function scopeEditable($query)
    {
        return $query->where('is_editable', true);
    }
}
