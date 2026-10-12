<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Storage;

/** Where generated files live. Paths are file names relative to the storage root. */
interface FeedStorageInterface
{
    /** Replaces the file atomically with the content of the stream (read from its start). @param resource $stream */
    public function write(string $path, $stream): void;

    /** @return resource|null */
    public function read(string $path);

    public function exists(string $path): bool;

    public function lastModified(string $path): ?\DateTimeImmutable;

    public function size(string $path): ?int;

    public function delete(string $path): void;
}
