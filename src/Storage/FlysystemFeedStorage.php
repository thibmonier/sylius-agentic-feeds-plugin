<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Storage;

use League\Flysystem\FilesystemOperator;

/** Any Flysystem storage (S3, SFTP...), e.g. a league/flysystem-bundle storage service. */
final class FlysystemFeedStorage implements FeedStorageInterface
{
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function write(string $path, $stream): void
    {
        rewind($stream);
        $this->filesystem->writeStream($path, $stream);
    }

    public function read(string $path)
    {
        return $this->filesystem->fileExists($path) ? $this->filesystem->readStream($path) : null;
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }

    public function lastModified(string $path): ?\DateTimeImmutable
    {
        return $this->filesystem->fileExists($path) ? (new \DateTimeImmutable())->setTimestamp($this->filesystem->lastModified($path)) : null;
    }

    public function size(string $path): ?int
    {
        return $this->filesystem->fileExists($path) ? $this->filesystem->fileSize($path) : null;
    }

    public function delete(string $path): void
    {
        if ($this->filesystem->fileExists($path)) {
            $this->filesystem->delete($path);
        }
    }
}
