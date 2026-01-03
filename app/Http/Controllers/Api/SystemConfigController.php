<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    /**
     * Get all configuration settings grouped by category
     */
    public function index(Request $request)
    {
        $query = SystemConfig::query();

        // Filter by group if specified
        if ($request->has('group')) {
            $query->byGroup($request->group);
        }

        // Get only editable configs for UI
        if ($request->has('editable') && $request->editable) {
            $query->editable();
        }

        $configs = $query->orderBy('group')->orderBy('key')->get();

        // Group by category
        $grouped = $configs->groupBy('group')->map(function ($items, $group) {
            return [
                'group' => $group,
                'configs' => $items->map(function ($config) {
                    return [
                        'id' => $config->id,
                        'key' => $config->key,
                        'value' => $config->getTypedValue(),
                        'type' => $config->type,
                        'description' => $config->description,
                        'is_editable' => $config->is_editable,
                        'is_public' => $config->is_public,
                    ];
                }),
            ];
        })->values();

        return response()->json([
            'message' => 'System configuration retrieved successfully',
            'data' => $grouped
        ]);
    }

    /**
     * Get a single configuration value
     */
    public function show($key)
    {
        $config = SystemConfig::where('key', $key)->firstOrFail();

        return response()->json([
            'message' => 'Configuration retrieved successfully',
            'data' => [
                'id' => $config->id,
                'key' => $config->key,
                'value' => $config->getTypedValue(),
                'type' => $config->type,
                'description' => $config->description,
                'group' => $config->group,
                'is_editable' => $config->is_editable,
                'is_public' => $config->is_public,
            ]
        ]);
    }

    /**
     * Update a configuration value
     */
    public function update(Request $request, $key)
    {
        $config = SystemConfig::where('key', $key)->firstOrFail();

        // Check if editable
        if (!$config->is_editable) {
            return response()->json([
                'message' => 'This configuration cannot be edited'
            ], 403);
        }

        $request->validate([
            'value' => 'required',
        ]);

        $oldValue = $config->getTypedValue();
        $config->setTypedValue($request->value);
        $config->save();

        // Log the change
        ActivityLog::log(
            'config_updated',
            "System configuration '{$config->key}' updated",
            'SystemConfig',
            $config->id,
            [
                'key' => $config->key,
                'old_value' => $oldValue,
                'new_value' => $config->getTypedValue(),
                'group' => $config->group,
            ],
            'info'
        );

        return response()->json([
            'message' => 'Configuration updated successfully',
            'data' => [
                'key' => $config->key,
                'value' => $config->getTypedValue(),
            ]
        ]);
    }

    /**
     * Bulk update multiple configurations
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'configs' => 'required|array',
            'configs.*.key' => 'required|string|exists:system_configs,key',
            'configs.*.value' => 'required',
        ]);

        $updated = [];

        foreach ($request->configs as $configData) {
            $config = SystemConfig::where('key', $configData['key'])->first();

            if ($config && $config->is_editable) {
                $oldValue = $config->getTypedValue();
                $config->setTypedValue($configData['value']);
                $config->save();

                $updated[] = $config->key;

                // Log each change
                ActivityLog::log(
                    'config_updated',
                    "System configuration '{$config->key}' updated",
                    'SystemConfig',
                    $config->id,
                    [
                        'key' => $config->key,
                        'old_value' => $oldValue,
                        'new_value' => $config->getTypedValue(),
                    ],
                    'info'
                );
            }
        }

        return response()->json([
            'message' => 'Configurations updated successfully',
            'updated' => $updated,
            'count' => count($updated)
        ]);
    }

    /**
     * Get available configuration groups
     */
    public function groups()
    {
        $groups = SystemConfig::select('group')
            ->distinct()
            ->pluck('group')
            ->map(function ($group) {
                return [
                    'group' => $group,
                    'count' => SystemConfig::where('group', $group)->count(),
                ];
            });

        return response()->json([
            'message' => 'Configuration groups retrieved successfully',
            'data' => $groups
        ]);
    }

    /**
     * Clear configuration cache
     */
    public function clearCache()
    {
        SystemConfig::clearCache();

        ActivityLog::log(
            'config_cache_cleared',
            'System configuration cache cleared',
            null,
            null,
            null,
            'info'
        );

        return response()->json([
            'message' => 'Configuration cache cleared successfully'
        ]);
    }
}
