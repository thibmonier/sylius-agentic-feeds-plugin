<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Storage;

/** Files in a local directory (default var/agentic_feeds), written to a temporary file then renamed. */
final class LocalFeedStorage implements FeedStorageInterface
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @param resource $stream */
    public function write(string $path, $stream): void
    {
        $target = $this->path($path);
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(sprintf('Cannot create the feed directory "%s".', $this->directory));
        }
        $temporary = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $out = fopen($temporary, 'wb');
        if (false === $out) {
            throw new \RuntimeException(sprintf('Cannot write "%s".', $temporary));
        }
        rewind($stream);
        stream_copy_to_stream($stream, $out);
        fclose($out);
        if (!rename($temporary, $target)) {
            @unlink($temporary);

            throw new \RuntimeException(sprintf('Cannot replace "%s".', $target));
        }
    }

    public function read(string $path)
    {
        $file = $this->path($path);
        if (!is_file($file)) {
            return null;
        }
        $stream = fopen($file, 'rb');

        return false === $stream ? null : $stream;
    }

    public function exists(string $path): bool
    {
        return is_file($this->path($path));
    }

    public function lastModified(string $path): ?\DateTimeImmutable
    {
        $time = is_file($this->path($path)) ? filemtime($this->path($path)) : false;

        return false === $time ? null : (new \DateTimeImmutable())->setTimestamp($time);
    }

    public function size(string $path): ?int
    {
        $size = is_file($this->path($path)) ? filesize($this->path($path)) : false;

        return false === $size ? null : $size;
    }

    public function delete(string $path): void
    {
        if (is_file($this->path($path))) {
            unlink($this->path($path));
        }
    }

    private function path(string $path): string
    {
        if ('' === $path || str_contains($path, '/') || str_contains($path, '\\') || str_starts_with($path, '.')) {
            throw new \InvalidArgumentException(sprintf('Invalid feed file name "%s".', $path));
        }

        return rtrim($this->directory, '/') . '/' . $path;
    }
}
