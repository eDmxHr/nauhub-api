<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CloudflareStreamService;
use App\Services\PutioService;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    /**
     * List all integration services with their status
     *
     * GET /api/admin/integrations
     */
    public function index()
    {
        $integrations = [];

        // Check Cloudflare integration
        $cloudflare = $this->getCloudflareStatus();
        $integrations[] = $cloudflare;

        // Check Put.io integration
        $putio = $this->getPutioStatus();
        $integrations[] = $putio;

        return response()->json([
            'integrations' => $integrations
        ]);
    }

    /**
     * Test Cloudflare API connection
     *
     * POST /api/admin/integrations/cloudflare/test
     */
    public function testCloudflare()
    {
        try {
            $cloudflareService = new CloudflareStreamService();

            if (!$cloudflareService->isConfigured()) {
                return response()->json([
                    'service' => 'cloudflare',
                    'configured' => false,
                    'status' => 'not_configured',
                    'message' => 'Cloudflare Stream is not configured',
                    'error' => 'Missing environment variables: CLOUDFLARE_ACCOUNT_ID, CLOUDFLARE_API_TOKEN, or CLOUDFLARE_CUSTOMER_SUBDOMAIN'
                ]);
            }

            // Try to create a direct upload URL to verify API connection
            $response = $cloudflareService->createDirectUploadUrl();

            // Check if response exists
            if (!$response) {
                return response()->json([
                    'service' => 'cloudflare',
                    'configured' => false,
                    'status' => 'error',
                    'message' => 'Failed to connect to Cloudflare API',
                    'error' => 'No response from API. Your credentials may be incorrect.'
                ]);
            }

            // Check if it's a mock URL (credentials configured but invalid)
            if (isset($response['uploadURL']) && strpos($response['uploadURL'], 'mock') !== false) {
                return response()->json([
                    'service' => 'cloudflare',
                    'configured' => false,
                    'status' => 'not_configured',
                    'message' => 'Cloudflare Stream is not properly configured',
                    'error' => 'Environment variables exist but credentials are invalid. Update CLOUDFLARE_ACCOUNT_ID, CLOUDFLARE_API_TOKEN, and CLOUDFLARE_CUSTOMER_SUBDOMAIN with real values.'
                ]);
            }

            // Check if uploadURL exists (real API response)
            if (!isset($response['uploadURL'])) {
                return response()->json([
                    'service' => 'cloudflare',
                    'configured' => false,
                    'status' => 'error',
                    'message' => 'Failed to connect to Cloudflare API',
                    'error' => 'Invalid API response. Check your credentials.'
                ]);
            }

            // Real successful response
            return response()->json([
                'service' => 'cloudflare',
                'configured' => true,
                'status' => 'connected',
                'message' => 'Successfully connected to Cloudflare Stream API',
                'details' => [
                    'account_id' => $this->maskToken(config('services.cloudflare.account_id')),
                    'customer_subdomain' => config('services.cloudflare.customer_subdomain'),
                    'api_works' => true,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'service' => 'cloudflare',
                'configured' => false,
                'status' => 'error',
                'message' => 'Failed to connect to Cloudflare API',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test Put.io API connection
     *
     * POST /api/admin/integrations/putio/test
     */
    public function testPutio()
    {
        $putioService = new PutioService();

        if (!$putioService->isConfigured()) {
            return response()->json([
                'service' => 'putio',
                'configured' => false,
                'status' => 'not_configured',
                'message' => 'Put.io is not configured',
                'error' => 'Missing environment variable: PUTIO_API_TOKEN'
            ], 400);
        }

        try {
            // Try to list files to verify token
            $response = $putioService->listFiles(0);

            if ($response !== null && is_array($response)) {
                return response()->json([
                    'service' => 'putio',
                    'configured' => true,
                    'status' => 'connected',
                    'message' => 'Successfully connected to Put.io API',
                    'details' => [
                        'api_works' => true,
                        'files_accessible' => true,
                    ]
                ]);
            }

            return response()->json([
                'service' => 'putio',
                'configured' => true,
                'status' => 'error',
                'message' => 'Failed to connect to Put.io API',
                'error' => 'API returned unexpected response'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'service' => 'putio',
                'configured' => true,
                'status' => 'error',
                'message' => 'Failed to connect to Put.io API',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Cloudflare integration status
     */
    protected function getCloudflareStatus(): array
    {
        $cloudflareService = new CloudflareStreamService();
        $configured = $cloudflareService->isConfigured();

        // Always include configuration fields (even if empty) for display
        $status = [
            'service' => 'cloudflare',
            'name' => 'Cloudflare Stream',
            'configured' => $configured,
            'status' => 'not_configured',
            'fields' => [
                'account_id' => config('services.cloudflare.account_id') ? $this->maskToken(config('services.cloudflare.account_id')) : null,
                'api_token' => config('services.cloudflare.api_token') ? $this->maskToken(config('services.cloudflare.api_token')) : null,
                'customer_subdomain' => config('services.cloudflare.customer_subdomain') ?: null,
            ],
        ];

        if ($configured) {
            try {
                // Quick test - try to create direct upload URL
                $response = $cloudflareService->createDirectUploadUrl();

                if ($response && isset($response['uploadURL'])) {
                    // Check if it's a mock response
                    if (strpos($response['uploadURL'], 'mock') !== false) {
                        $status['status'] = 'not_configured';
                        $status['error'] = 'Invalid credentials (mock response)';
                    } else {
                        $status['status'] = 'connected';
                    }
                } else {
                    $status['status'] = 'error';
                    $status['error'] = 'API returned unexpected response';
                }
            } catch (\Exception $e) {
                $status['status'] = 'error';
                $status['error'] = $e->getMessage();
            }
        }

        return $status;
    }

    /**
     * Get Put.io integration status
     */
    protected function getPutioStatus(): array
    {
        $putioService = new PutioService();
        $configured = $putioService->isConfigured();

        // Always include configuration fields (even if empty) for display
        $status = [
            'service' => 'putio',
            'name' => 'Put.io',
            'configured' => $configured,
            'status' => 'not_configured',
            'fields' => [
                'api_token' => config('services.putio.api_token') ? $this->maskToken(config('services.putio.api_token')) : null,
            ],
        ];

        if ($configured) {
            try {
                // Quick test - list files
                $response = $putioService->listFiles(0);

                if ($response !== null && is_array($response)) {
                    $status['status'] = 'connected';
                } else {
                    $status['status'] = 'error';
                    $status['error'] = 'API returned unexpected response';
                }
            } catch (\Exception $e) {
                $status['status'] = 'error';
                $status['error'] = $e->getMessage();
            }
        }

        return $status;
    }

    /**
     * Mask sensitive token/credential
     */
    protected function maskToken(?string $token): string
    {
        if (!$token || strlen($token) <= 6) {
            return '***';
        }

        return substr($token, 0, 3) . '***' . substr($token, -3);
    }
}
