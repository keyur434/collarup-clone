<?php

class FFmpegService
{
    private $binary;
    private $compressionArgs;

    public function __construct()
    {
        $config = config('services')['ffmpeg'];
        $this->binary = $config['binary'];
        $this->compressionArgs = isset($config['compression']) ? $config['compression'] : '';
    }

    public function compressVideo($inputPath, $outputPath)
    {
        if ($this->compressionArgs === '') {
            throw new RuntimeException('Video compression is disabled. Set FFMPEG_COMPRESSION in config to enable.');
        }
        $cmd = sprintf(
            '%s -y -i %s %s %s 2>&1',
            escapeshellarg($this->binary),
            escapeshellarg($inputPath),
            $this->compressionArgs,
            escapeshellarg($outputPath)
        );
        exec($cmd, $output, $code);
        if ($code !== 0 || !file_exists($outputPath)) {
            throw new RuntimeException('FFmpeg compression failed: ' . implode("\n", $output));
        }
        return $outputPath;
    }

    public function extractAudio($inputPath, $outputPath)
    {
        $cmd = sprintf(
            '%s -y -i %s -vn -acodec libmp3lame -q:a 2 %s 2>&1',
            escapeshellarg($this->binary),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );
        exec($cmd, $output, $code);
        if ($code !== 0 || !file_exists($outputPath)) {
            throw new RuntimeException('FFmpeg audio extraction failed: ' . implode("\n", $output));
        }
        return $outputPath;
    }

    public function getDuration($inputPath)
    {
        $cmd = sprintf(
            '%s -i %s 2>&1',
            escapeshellarg($this->binary),
            escapeshellarg($inputPath)
        );
        exec($cmd, $output);
        $text = implode("\n", $output);
        if (preg_match('/Duration: (\d+):(\d+):(\d+)/', $text, $m)) {
            return ((int) $m[1] * 3600) + ((int) $m[2] * 60) + (int) $m[3];
        }
        return 0;
    }
}
