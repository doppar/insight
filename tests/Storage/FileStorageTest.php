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
            $files = glob($this->testDir . '/*.json') ?: [];
            foreach ($files as $file) {
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
}
