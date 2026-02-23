<?php
declare(strict_types=1);

namespace PraiseFrank;

use JsonException;
use RuntimeException;

final class AnsiAnimationPlayer
{
    public function loadAnimation(string $path): array
    {
        $resolved = $this->resolvePath($path);
        if (!is_file($resolved)) {
            throw new RuntimeException("Animation file not found: {$path}");
        }

        $raw = file_get_contents($resolved);
        if ($raw === false) {
            throw new RuntimeException("Unable to read animation file: {$resolved}");
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid animation JSON: ' . $exception->getMessage(), 0, $exception);
        }
        $frames = $decoded['frames'] ?? [];
        $metadata = $decoded['metadata'] ?? [];
        $canvas = $decoded['canvas'] ?? [];

        if (!is_array($frames)) {
            throw new RuntimeException('Invalid animation JSON: frames array missing.');
        }

        return [$frames, $metadata, $canvas];
    }

    /**
     * @param array<int, array<string, mixed>> $frames
     * @param array<string, mixed> $options
     */
    public function play(array $frames, array $options = []): void
    {
        if ($frames === []) {
            fwrite(STDERR, "No frames found in animation.\n");
            return;
        }

        $loop = $options['loop'] ?? true;
        $fps = $options['fps'] ?? null;
        $canvasWidth = $options['canvasWidth'] ?? null;
        $enableColor = (bool)($options['enableColor'] ?? true);
        $forcedColorMode = $options['forcedColorMode'] ?? null;
        $preferFlicker = (bool)($options['preferFlicker'] ?? false);

        $colorMode = 'none';
        if ($enableColor) {
            if ($forcedColorMode === 'truecolor' || $forcedColorMode === '256') {
                $colorMode = $forcedColorMode;
            } elseif ($this->supportsTruecolor()) {
                $colorMode = 'truecolor';
            } elseif ($this->supports256color()) {
                $colorMode = '256';
            }
        }

        $renderedFrames = [];
        foreach ($frames as $frame) {
            if ($colorMode === 'none') {
                $renderedFrames[] = $this->frameToText($frame);
            } else {
                $renderedFrames[] = $this->renderFrameWithColors($frame, $canvasWidth, $colorMode);
            }
        }

        $durations = $this->computeFrameDurations($frames, $fps);

        $frameCount = count($renderedFrames);
        $idx = 0;
        $usingAltBuffer = false;

        $this->hideCursor();

        try {
            if (!$preferFlicker) {
                $this->enableAlternateBuffer();
                $usingAltBuffer = true;
                $this->clearScreen();
            }

            while (true) {
                $frameText = $renderedFrames[$idx];
                if ($preferFlicker) {
                    fwrite(STDOUT, "\x1b[2J\x1b[H" . $frameText);
                } else {
                    fwrite(STDOUT, "\x1b[H" . $frameText);
                }
                fflush(STDOUT);

                $wait = $durations[$idx];
                $this->preciseSleep($wait);

                $idx++;
                if ($idx >= $frameCount) {
                    if ($loop) {
                        $idx = 0;
                    } else {
                        break;
                    }
                }
            }
        } finally {
            $this->showCursor();
            if ($usingAltBuffer) {
                $this->disableAlternateBuffer();
            }
        }
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return $path;
        }
        if ($path[0] === '/' || preg_match('#^[A-Za-z]:#', $path) === 1) {
            return $path;
        }
        $candidate = getcwd();
        if ($candidate === false) {
            return $path;
        }
        return $candidate . DIRECTORY_SEPARATOR . $path;
    }

    private function frameToText(array $frame): string
    {
        if ($frame === []) {
            return '';
        }

        if (isset($frame['contentString']) && is_string($frame['contentString'])) {
            return rtrim($frame['contentString'], "\n");
        }

        if (isset($frame['content']) && is_array($frame['content'])) {
            $lines = [];
            foreach ($frame['content'] as $line) {
                if (is_string($line)) {
                    $lines[] = rtrim($line, "\n");
                }
            }
            return implode("\n", $lines);
        }

        return '';
    }

    private function renderFrameWithColors(array $frame, ?int $width, string $colorMode): string
    {
        $text = $this->frameToText($frame);
        $lines = $text === '' ? [] : preg_split('/\r?\n/', $text);
        if ($lines === false) {
            $lines = [];
        }

        if ($width === null) {
            $width = 0;
            foreach ($lines as $line) {
                $width = max($width, strlen($line));
            }
        }

        $colorMap = $this->buildColorMap($frame);
        $rendered = [];

        $lineCount = count($lines);
        for ($row = 0; $row < $lineCount; $row++) {
            $line = $lines[$row];
            if ($width > 0 && strlen($line) < $width) {
                $line = str_pad($line, $width);
            }

            $out = '';
            $prevColor = null;
            $length = strlen($line);
            for ($col = 0; $col < $length; $col++) {
                $ch = $line[$col];
                $color = $colorMap[$row][$col] ?? null;

                if ($colorMode === 'truecolor') {
                    $rgb = $color ? $this->hexToRgb($color) : null;
                    if ($rgb !== $prevColor) {
                        if ($prevColor !== null) {
                            $out .= "\x1b[0m";
                        }
                        if ($rgb !== null) {
                            [$r, $g, $b] = $rgb;
                            $out .= sprintf("\x1b[38;2;%d;%d;%dm", $r, $g, $b);
                        }
                        $prevColor = $rgb;
                    }
                } elseif ($colorMode === '256') {
                    $rgb = $color ? $this->hexToRgb($color) : null;
                    $code = $rgb ? $this->rgbToAnsi256(...$rgb) : null;
                    if ($code !== $prevColor) {
                        if ($prevColor !== null) {
                            $out .= "\x1b[0m";
                        }
                        if ($code !== null) {
                            $out .= sprintf("\x1b[38;5;%dm", $code);
                        }
                        $prevColor = $code;
                    }
                }

                $out .= $ch;
            }

            if ($prevColor !== null) {
                $out .= "\x1b[0m";
            }
            $rendered[] = $out;
        }

        return implode("\n", $rendered);
    }

    private function buildColorMap(array $frame): array
    {
        $map = [];
        if (!isset($frame['colors']) || !is_array($frame['colors'])) {
            return $map;
        }
        $colors = $frame['colors'];
        $foreground = $colors['foreground'] ?? null;
        if (!is_string($foreground) || $foreground === '') {
            return $map;
        }

        $decoded = json_decode($foreground, true);
        if (!is_array($decoded)) {
            return $map;
        }

        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            $parts = explode(',', $key);
            if (count($parts) !== 2) {
                continue;
            }
            [$col, $row] = $parts;
            if (!is_numeric($col) || !is_numeric($row)) {
                continue;
            }
            $colIdx = (int)$col;
            $rowIdx = (int)$row;
            $map[$rowIdx][$colIdx] = $value;
        }

        return $map;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function rgbToAnsi256(int $r, int $g, int $b): int
    {
        if ($r === $g && $g === $b) {
            if ($r < 8) {
                return 16;
            }
            if ($r > 248) {
                return 231;
            }
            return 232 + (int)round(($r - 8) / 247 * 24);
        }

        $ri = (int)round($r / 255 * 5);
        $gi = (int)round($g / 255 * 5);
        $bi = (int)round($b / 255 * 5);

        return 16 + 36 * $ri + 6 * $gi + $bi;
    }

    private function supportsTruecolor(): bool
    {
        $value = strtolower((string)($_SERVER['COLORTERM'] ?? getenv('COLORTERM') ?? ''));
        return $value === 'truecolor' || $value === '24bit';
    }

    private function supports256color(): bool
    {
        $term = strtolower((string)($_SERVER['TERM'] ?? getenv('TERM') ?? ''));
        return str_contains($term, '256color');
    }

    private function computeFrameDurations(array $frames, ?float $fpsOverride): array
    {
        $defaultsPerSecond = $fpsOverride ?? 24.0;
        $durations = [];
        foreach ($frames as $frame) {
            $durationMs = $frame['duration'] ?? null;
            if (!is_numeric($durationMs) || $durationMs <= 0) {
                $durationMs = 1000.0 / $defaultsPerSecond;
            }
            $durations[] = max(0.001, ((float)$durationMs) / 1000.0);
        }

        return $durations;
    }

    private function preciseSleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }
        $end = microtime(true) + $seconds;
        while (true) {
            $remaining = $end - microtime(true);
            if ($remaining <= 0) {
                break;
            }
            usleep((int)min(10000, $remaining * 1_000_000));
        }
    }

    private function hideCursor(): void
    {
        fwrite(STDOUT, "\x1b[?25l");
    }

    private function showCursor(): void
    {
        fwrite(STDOUT, "\x1b[?25h");
        fflush(STDOUT);
    }

    private function clearScreen(): void
    {
        fwrite(STDOUT, "\x1b[2J\x1b[H");
    }

    private function enableAlternateBuffer(): void
    {
        fwrite(STDOUT, "\x1b[?1049h");
    }

    private function disableAlternateBuffer(): void
    {
        fwrite(STDOUT, "\x1b[?1049l");
    }
}
