<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Subtitle Service
 *
 * Handles subtitle extraction from video files and conversion to WebVTT format
 */
class SubtitleService
{
    /**
     * Check if FFmpeg is available
     *
     * @return bool
     */
    public function isFFmpegAvailable(): bool
    {
        // On Windows, use shell_exec with 'where' command
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $result = shell_exec('where ffmpeg 2>NUL');
            if ($result && trim($result) !== '') {
                Log::info('FFmpeg found on Windows', ['path' => trim($result)]);
                return true;
            }

            Log::warning('FFmpeg not found on Windows PATH');
            return false;
        }

        // On Unix systems, use which command
        $result = shell_exec('which ffmpeg 2>/dev/null');
        if ($result && trim($result) !== '') {
            Log::info('FFmpeg found on Unix', ['path' => trim($result)]);
            return true;
        }

        return false;
    }

    /**
     * Extract subtitles from a video file URL
     *
     * Downloads the video temporarily, extracts subtitles, then cleans up
     *
     * @param string $videoUrl Video URL (e.g., from Put.io)
     * @param string $videoUid Cloudflare video UID (for tracking)
     * @return array Array of extracted subtitle tracks with language info
     */
    public function extractSubtitlesFromUrl(string $videoUrl, string $videoUid): array
    {
        if (!$this->isFFmpegAvailable()) {
            Log::warning('FFmpeg not available for subtitle extraction');
            return [];
        }

        try {
            // Create temporary directory for this extraction
            $tempDir = storage_path("app/temp/subtitles/{$videoUid}");
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $videoFile = "{$tempDir}/video.mkv";

            // Download video file (only if small enough, or stream first few MB)
            Log::info('Downloading video for subtitle extraction', ['url' => $videoUrl]);

            // Use curl to download with progress
            $downloadProcess = new Process([
                'curl',
                '-L',
                '-o', $videoFile,
                '--max-time', '300', // 5 minute timeout
                $videoUrl
            ]);
            $downloadProcess->setTimeout(300);
            $downloadProcess->run();

            if (!$downloadProcess->isSuccessful() || !file_exists($videoFile)) {
                throw new \Exception('Failed to download video file');
            }

            // Get subtitle track info
            $subtitles = $this->extractSubtitleTracks($videoFile, $tempDir);

            // Clean up video file (keep subtitle files for now)
            if (file_exists($videoFile)) {
                unlink($videoFile);
            }

            return $subtitles;

        } catch (\Exception $e) {
            Log::error('Subtitle extraction failed', [
                'video_uid' => $videoUid,
                'error' => $e->getMessage(),
            ]);

            // Clean up on error
            if (isset($tempDir) && file_exists($tempDir)) {
                $this->cleanupDirectory($tempDir);
            }

            return [];
        }
    }

    /**
     * Extract subtitle tracks from a video file
     *
     * @param string $videoFile Path to video file
     * @param string $outputDir Directory to save extracted subtitles
     * @return array Array of subtitle file paths with metadata
     */
    protected function extractSubtitleTracks(string $videoFile, string $outputDir): array
    {
        // First, probe the file to find subtitle streams
        $probeProcess = new Process([
            'ffprobe',
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_streams',
            '-select_streams', 's', // Select only subtitle streams
            $videoFile
        ]);
        $probeProcess->run();

        if (!$probeProcess->isSuccessful()) {
            Log::warning('FFprobe failed', ['error' => $probeProcess->getErrorOutput()]);
            return [];
        }

        $probeData = json_decode($probeProcess->getOutput(), true);
        $streams = $probeData['streams'] ?? [];

        if (empty($streams)) {
            Log::info('No subtitle streams found in video');
            return [];
        }

        $extractedSubtitles = [];

        foreach ($streams as $index => $stream) {
            $streamIndex = $stream['index'];
            $language = $stream['tags']['language'] ?? 'unknown';
            $title = $stream['tags']['title'] ?? '';

            // Determine output format and filename
            $outputFile = "{$outputDir}/subtitle_{$index}_{$language}.srt";

            // Extract subtitle stream
            $extractProcess = new Process([
                'ffmpeg',
                '-i', $videoFile,
                '-map', "0:{$streamIndex}",
                '-c:s', 'srt', // Convert to SRT format first
                $outputFile,
                '-y' // Overwrite if exists
            ]);
            $extractProcess->setTimeout(60);
            $extractProcess->run();

            if ($extractProcess->isSuccessful() && file_exists($outputFile)) {
                // Convert SRT to WebVTT (required by Cloudflare)
                $vttFile = str_replace('.srt', '.vtt', $outputFile);
                if ($this->convertSrtToVtt($outputFile, $vttFile)) {
                    $extractedSubtitles[] = [
                        'file' => $vttFile,
                        'language' => $language,
                        'label' => $title ?: ucfirst($language),
                        'stream_index' => $streamIndex,
                    ];

                    // Remove SRT file, keep only VTT
                    unlink($outputFile);
                }
            }
        }

        Log::info('Subtitle extraction complete', [
            'count' => count($extractedSubtitles),
            'languages' => array_column($extractedSubtitles, 'language'),
        ]);

        return $extractedSubtitles;
    }

    /**
     * Convert SRT subtitle to WebVTT format
     *
     * @param string $srtFile Path to SRT file
     * @param string $vttFile Path to output VTT file
     * @return bool Success status
     */
    protected function convertSrtToVtt(string $srtFile, string $vttFile): bool
    {
        try {
            $srtContent = file_get_contents($srtFile);

            // Convert SRT to WebVTT
            // Add WebVTT header
            $vttContent = "WEBVTT\n\n";

            // SRT format uses comma for milliseconds, VTT uses period
            $vttContent .= str_replace(',', '.', $srtContent);

            file_put_contents($vttFile, $vttContent);

            return file_exists($vttFile);
        } catch (\Exception $e) {
            Log::error('SRT to VTT conversion failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clean up temporary directory
     *
     * @param string $directory
     * @return void
     */
    protected function cleanupDirectory(string $directory): void
    {
        if (!file_exists($directory)) {
            return;
        }

        $files = glob("{$directory}/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($directory);
    }

    /**
     * Get subtitle file path
     *
     * @param string $videoUid
     * @param string $language
     * @return string|null
     */
    public function getSubtitlePath(string $videoUid, string $language): ?string
    {
        $tempDir = storage_path("app/temp/subtitles/{$videoUid}");
        $files = glob("{$tempDir}/*_{$language}.vtt");

        return $files[0] ?? null;
    }
}
