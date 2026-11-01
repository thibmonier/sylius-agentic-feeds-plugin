<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\LocalFeedStorage;

final class LocalFeedStorageTest extends TestCase
{
    #[Test]
    #[DataProvider('invalidPathsProvider')]
    public function pathValidationRejectsInvalidNames(string $path): void
    {
        $storage = new LocalFeedStorage(sys_get_temp_dir());

        $this->expectException(\InvalidArgumentException::class);
        $storage->exists($path);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPathsProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'slash' => ['dir/file.xml'];
        yield 'backslash' => ['dir\\file.xml'];
        yield 'dot-prefix' => ['.hidden'];
        yield 'dot-dot' => ['..'];
        yield 'traversal' => ['../etc/passwd'];
    }

    #[Test]
    public function sizeReturnsNullForMissingFile(): void
    {
        $storage = new LocalFeedStorage(sys_get_temp_dir() . '/nonexistent_' . bin2hex(random_bytes(4)));

        self::assertNull($storage->size('missing.xml'));
    }

    #[Test]
    public function deleteNonexistentFileDoesNotThrow(): void
    {
        $storage = new LocalFeedStorage(sys_get_temp_dir() . '/nonexistent_' . bin2hex(random_bytes(4)));

        $storage->delete('nonexistent.xml');

        self::assertFalse($storage->exists('nonexistent.xml'));
    }
}
