<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Storage;

use Doppar\Insight\Storage\FileStorage;
use Doppar\Insight\Tests\TestCase;

class FileStorageTest extends TestCase
{
    private string $testDir;
    private FileStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a temporary directory for tests
        $this->testDir = sys_get_temp_dir() . '/doppar-insight-test-' . uniqid();
        $this->storage = new FileStorage($this->testDir, 1);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testDir)) {
            $entries = scandir($this->testDir) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $file = $this->testDir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testDir);
        }
        
        parent::tearDown();
    }

    public function testConstructorWithCustomBaseDir(): void
    {
        $customDir = sys_get_temp_dir() . '/custom-dir';
        $storage = new FileStorage($customDir);
        
        // Verify by putting data (which will create the directory)
        $storage->put('test-id', ['data' => 'value']);
        
        $this->assertDirectoryExists($customDir);
        
        // Cleanup
        unlink($customDir . '/test-id.json');
        if (is_file($customDir . '/.cleanup-marker')) {
            unlink($customDir . '/.cleanup-marker');
        }
        rmdir($customDir);
    }

    public function testPutCreatesDirectoryIfNotExists(): void
    {
        $this->assertDirectoryDoesNotExist($this->testDir);
        
        $this->storage->put('test-id', ['key' => 'value']);
        
        $this->assertDirectoryExists($this->testDir);
    }

    public function testPutWritesJsonFile(): void
    {
        $data = ['request_id' => '123', 'duration' => 150.5];
        
        $this->storage->put('test-request', $data);
        
        $filePath = $this->testDir . '/test-request.json';
        $this->assertFileExists($filePath);
        
        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);
        
        $this->assertEquals($data, $decoded);
    }

    public function testGetReturnsData(): void
    {
        $data = ['user' => 'john', 'action' => 'login'];
        
        $this->storage->put('session-123', $data);
        $retrieved = $this->storage->get('session-123');
        
        $this->assertEquals($data, $retrieved);
    }

    public function testGetReturnsNullForNonExistentFile(): void
    {
        $result = $this->storage->get('non-existent-id');
        
        $this->assertNull($result);
    }

    public function testGetHandlesInvalidJson(): void
    {
        // Create a file with invalid JSON
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
        
        $filePath = $this->testDir . '/invalid.json';
        file_put_contents($filePath, 'invalid json content {');
        
        $result = $this->storage->get('invalid');
        
        $this->assertNull($result);
    }

    public function testGetHandlesNonArrayJson(): void
    {
        // Create a file with valid JSON but not an array
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
        
        $filePath = $this->testDir . '/string.json';
        file_put_contents($filePath, json_encode('just a string'));
        
        $result = $this->storage->get('string');
        
        $this->assertNull($result);
    }

    public function testPutOverwritesExistingFile(): void
    {
        $data1 = ['version' => 1];
        $data2 = ['version' => 2];
        
        $this->storage->put('config', $data1);
        $this->storage->put('config', $data2);
        
        $retrieved = $this->storage->get('config');
        
        $this->assertEquals($data2, $retrieved);
    }

    public function testMultiplePutAndGet(): void
    {
        $this->storage->put('id1', ['data' => 'first']);
        $this->storage->put('id2', ['data' => 'second']);
        $this->storage->put('id3', ['data' => 'third']);
        
        $this->assertEquals(['data' => 'first'], $this->storage->get('id1'));
        $this->assertEquals(['data' => 'second'], $this->storage->get('id2'));
        $this->assertEquals(['data' => 'third'], $this->storage->get('id3'));
    }

    public function testCleanupDoesNotRunImmediately(): void
    {
        // Put two files
        $this->storage->put('file1', ['data' => 'value1']);
        $this->storage->put('file2', ['data' => 'value2']);
        
        // Both files should still exist
        $this->assertFileExists($this->testDir . '/file1.json');
        $this->assertFileExists($this->testDir . '/file2.json');
    }

    public function testHandlesComplexData(): void
    {
        $complexData = [
            'nested' => [
                'array' => [1, 2, 3],
                'object' => ['key' => 'value'],
            ],
            'boolean' => true,
            'null' => null,
            'float' => 123.456,
        ];
        
        $this->storage->put('complex', $complexData);
        $retrieved = $this->storage->get('complex');
        
        $this->assertEquals($complexData, $retrieved);
    }

    public function testHandlesEmptyArray(): void
    {
        $this->storage->put('empty', []);
        $retrieved = $this->storage->get('empty');
        
        $this->assertEquals([], $retrieved);
    }

    public function testHandlesSpecialCharactersInId(): void
    {
        // Note: Some special characters may not be valid in filenames
        $id = 'request-2024-01-01_12-30-45';
        $data = ['timestamp' => '2024-01-01 12:30:45'];
        
        $this->storage->put($id, $data);
        $retrieved = $this->storage->get($id);
        
        $this->assertEquals($data, $retrieved);
    }

    public function testJsonEncodingPreservesTypes(): void
    {
        $data = [
            'string' => 'text',
            'int' => 42,
            'float' => 3.14,
            'bool_true' => true,
            'bool_false' => false,
            'null' => null,
            'array' => [1, 2, 3],
        ];
        
        $this->storage->put('types', $data);
        $retrieved = $this->storage->get('types');
        
        $this->assertSame('text', $retrieved['string']);
        $this->assertSame(42, $retrieved['int']);
        $this->assertSame(3.14, $retrieved['float']);
        $this->assertSame(true, $retrieved['bool_true']);
        $this->assertSame(false, $retrieved['bool_false']);
        $this->assertNull($retrieved['null']);
        $this->assertSame([1, 2, 3], $retrieved['array']);
    }

    public function testRecentReturnsNewestProfilesFirst(): void
    {
        $this->storage->put('first', [
            'id' => 'first',
            'method' => 'GET',
            'route' => '/alpha',
            'status' => 200,
            'duration_ms' => 11.5,
            'time_start' => 100,
        ]);
        $this->storage->put('second', [
            'id' => 'second',
            'method' => 'POST',
            'route' => '/beta',
            'status' => 201,
            'duration_ms' => 22.5,
            'time_start' => 200,
        ]);

        $recent = $this->storage->recent();

        $this->assertCount(2, $recent);
        $this->assertSame('second', $recent[0]['id']);
        $this->assertSame('/beta', $recent[0]['route']);
        $this->assertSame('first', $recent[1]['id']);
    }

    public function testRecentRespectsLimitAndUsesTotalDurationWhenPresent(): void
    {
        $this->storage->put('one', [
            'id' => 'one',
            'method' => 'GET',
            'route' => '/one',
            'status' => 200,
            'duration_ms' => 15,
            'time_start' => 100,
        ]);
        $this->storage->put('two', [
            'id' => 'two',
            'method' => 'GET',
            'route' => '/two',
            'status' => 302,
            'duration_ms' => 20,
            'total_duration_ms' => 45.25,
            'time_start' => 200,
        ]);

        $recent = $this->storage->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('two', $recent[0]['id']);
        $this->assertSame(45.25, $recent[0]['duration_ms']);
    }

    public function testRecentIncludesExceptionMetadataWhenPresent(): void
    {
        $this->storage->put('failed', [
            'id' => 'failed',
            'method' => 'GET',
            'route' => '/missing',
            'status' => 404,
            'duration_ms' => 18,
            'exception_class' => 'Phaseolies\\Http\\Exceptions\\NotFoundHttpException',
            'exception_message' => 'Route missing',
            'time_start' => 300,
        ]);

        $recent = $this->storage->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('Phaseolies\\Http\\Exceptions\\NotFoundHttpException', $recent[0]['exception_class']);
        $this->assertSame('Route missing', $recent[0]['exception_message']);
    }

    public function testRecentSkipsInvalidJsonFiles(): void
    {
        if (! is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }

        file_put_contents($this->testDir . '/broken.json', '{invalid');
        $this->storage->put('valid', [
            'id' => 'valid',
            'method' => 'GET',
            'route' => '/valid',
            'status' => 200,
            'duration_ms' => 9.9,
            'time_start' => 100,
        ]);

        $recent = $this->storage->recent(5);

        $this->assertCount(1, $recent);
        $this->assertSame('valid', $recent[0]['id']);
    }

    public function testCleanupIntervalIsSharedAcrossStorageInstances(): void
    {
        if (! is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }

        $stalePath = $this->testDir . '/stale.json';
        file_put_contents($stalePath, json_encode(['id' => 'stale']));
        touch($stalePath, time() - (3 * 86400));

        $markerPath = $this->testDir . '/.cleanup-marker';
        touch($markerPath, time());

        $freshInstance = new FileStorage($this->testDir, 1);
        $freshInstance->put('new', ['id' => 'new']);

        $this->assertFileExists($stalePath);
        $this->assertFileExists($markerPath);
    }

    public function testPutTruncatesProfilesThatExceedTheConfiguredSize(): void
    {
        $storage = new FileStorage($this->testDir, 1, 1024, 104857600);
        $storage->put('large', ['response_preview' => str_repeat('x', 10000)]);

        $path = $this->testDir . '/large.json';

        $this->assertFileExists($path);
        $this->assertLessThanOrEqual(1024, filesize($path));
        $this->assertTrue((bool) ($storage->get('large')['_insight_truncated'] ?? false));
    }

    public function testPutEnforcesTheStorageQuotaDuringCleanup(): void
    {
        $storage = new FileStorage($this->testDir, 1, 1048576, 2000);
        $payload = ['response_preview' => str_repeat('x', 900)];

        $storage->put('oldest', $payload);
        touch($this->testDir . '/oldest.json', time() - 3);
        $storage->put('middle', $payload);
        touch($this->testDir . '/middle.json', time() - 2);
        touch($this->testDir . '/.cleanup-marker', time() - 86401);
        $storage->put('newest', $payload);

        $files = glob($this->testDir . '/*.json') ?: [];
        $totalSize = array_sum(array_map(static fn (string $file): int => (int) filesize($file), $files));

        $this->assertLessThanOrEqual(2000, $totalSize);
        $this->assertFileExists($this->testDir . '/newest.json');
    }

    public function testPutFailsOpenWhenTheStorageDirectoryCannotBeCreated(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'insight-storage-');
        $this->assertNotFalse($filePath);

        try {
            $storage = new FileStorage($filePath, 1);
            $storage->put('unwritable', ['data' => 'value']);

            $this->assertFileDoesNotExist($filePath . DIRECTORY_SEPARATOR . 'unwritable.json');
        } finally {
            if (is_string($filePath) && is_file($filePath)) {
                unlink($filePath);
            }
        }
    }
}
