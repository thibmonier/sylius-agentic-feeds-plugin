<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\FeedStorageInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\FlysystemFeedStorage;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\LocalFeedStorage;

final class FlysystemFeedStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/tmonier_feeds_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testFlysystem(): void
    {
        $this->assertStorage(new FlysystemFeedStorage(new Filesystem(new LocalFilesystemAdapter($this->directory))));
    }

    public function testLocal(): void
    {
        $storage = new LocalFeedStorage($this->directory);
        $this->assertStorage($storage);

        $this->expectException(\InvalidArgumentException::class);
        $storage->exists('../etc/passwd');
    }

    private function assertStorage(FeedStorageInterface $storage): void
    {
        self::assertFalse($storage->exists('feed.xml'));
        self::assertNull($storage->read('feed.xml'));
        self::assertNull($storage->lastModified('feed.xml'));

        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, '<rss/>');
        $storage->write('feed.xml', $stream);

        self::assertTrue($storage->exists('feed.xml'));
        self::assertSame(6, $storage->size('feed.xml'));
        self::assertNotNull($storage->lastModified('feed.xml'));
        $read = $storage->read('feed.xml');
        self::assertIsResource($read);
        self::assertSame('<rss/>', stream_get_contents($read));

        $storage->delete('feed.xml');
        self::assertFalse($storage->exists('feed.xml'));
    }
}
