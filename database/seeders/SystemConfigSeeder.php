<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemConfig;

class SystemConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configs = [
            // General Settings
            [
                'key' => 'site.name',
                'value' => 'Nauhub',
                'type' => 'string',
                'description' => 'Website name displayed across the platform',
                'group' => 'general',
                'is_public' => true,
                'is_editable' => true,
            ],
            [
                'key' => 'site.tagline',
                'value' => 'Your Streaming Platform',
                'type' => 'string',
                'description' => 'Website tagline or slogan',
                'group' => 'general',
                'is_public' => true,
                'is_editable' => true,
            ],
            [
                'key' => 'site.maintenance_mode',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Enable maintenance mode to prevent user access',
                'group' => 'general',
                'is_public' => false,
                'is_editable' => true,
            ],

            // Features
            [
                'key' => 'features.max_profiles_per_user',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Maximum number of profiles a user can create',
                'group' => 'features',
                'is_public' => true,
                'is_editable' => true,
            ],
            [
                'key' => 'features.max_upload_size_mb',
                'value' => '5000',
                'type' => 'integer',
                'description' => 'Maximum video upload size in MB',
                'group' => 'features',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'features.enable_download',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Allow users to download videos for offline viewing',
                'group' => 'features',
                'is_public' => true,
                'is_editable' => true,
            ],

            // Payment Settings
            [
                'key' => 'payment.currency',
                'value' => 'MVR',
                'type' => 'string',
                'description' => 'Default currency for payments',
                'group' => 'payment',
                'is_public' => true,
                'is_editable' => true,
            ],
            [
                'key' => 'payment.manual_verification',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Require manual verification of payment proofs',
                'group' => 'payment',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'payment.auto_expire_days',
                'value' => '3',
                'type' => 'integer',
                'description' => 'Days to wait before auto-expiring pending payments',
                'group' => 'payment',
                'is_public' => false,
                'is_editable' => true,
            ],

            // API Settings
            [
                'key' => 'api.rate_limit_per_minute',
                'value' => '60',
                'type' => 'integer',
                'description' => 'Maximum API requests per minute per user',
                'group' => 'api',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'api.cloudflare_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable Cloudflare Stream integration',
                'group' => 'api',
                'is_public' => false,
                'is_editable' => false, // Should be configured via env
            ],
            [
                'key' => 'api.putio_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable Put.io integration for magnet links',
                'group' => 'api',
                'is_public' => false,
                'is_editable' => false, // Should be configured via env
            ],

            // Content Settings
            [
                'key' => 'content.auto_publish',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Automatically publish uploaded videos',
                'group' => 'content',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'content.featured_videos_count',
                'value' => '10',
                'type' => 'integer',
                'description' => 'Number of featured videos to display on homepage',
                'group' => 'content',
                'is_public' => true,
                'is_editable' => true,
            ],

            // Security Settings
            [
                'key' => 'security.max_login_attempts',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Maximum failed login attempts before temporary lockout',
                'group' => 'security',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'security.session_lifetime_hours',
                'value' => '24',
                'type' => 'integer',
                'description' => 'User session lifetime in hours',
                'group' => 'security',
                'is_public' => false,
                'is_editable' => true,
            ],
        ];

        foreach ($configs as $config) {
            SystemConfig::updateOrCreate(
                ['key' => $config['key']],
                $config
            );
        }
    }
}
