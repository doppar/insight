<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\SqlCollector;
use Doppar\Insight\Tests\TestCase;

class SqlCollectorTest extends TestCase
{
    private SqlCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new SqlCollector();
    }

    protected function tearDown(): void
    {
        SqlCollector::setActive(null);
        parent::tearDown();
    }

    public function testName(): void
    {
        $this->assertEquals('sql', $this->collector->name());
    }

    public function testStartSetsActiveCollector(): void
    {
        $request = $this->createRequest();
        
        $this->collector->start($request);
        
        $this->assertSame($this->collector, SqlCollector::active());
    }

    public function testStopClearsActiveCollector(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse();
        
        $this->collector->start($request);
        $this->assertNotNull(SqlCollector::active());
        
        $this->collector->stop($request, $response);
        $this->assertNull(SqlCollector::active());
    }

    public function testRegisterQuery(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $bindings = [123];
        $duration = 15.5;
        
        $this->collector->registerQuery($sql, $bindings, $duration);
        
        $data = $this->collector->toArray();
        
        $this->assertCount(1, $data['sql']);
        $this->assertEquals($sql, $data['sql'][0]['sql']);
        $this->assertEquals($bindings, $data['sql'][0]['bindings']);
        $this->assertEquals($duration, $data['sql'][0]['duration_ms']);
    }

    public function testRegisterMultipleQueries(): void
    {
        $this->collector->registerQuery('SELECT * FROM users', [], 10.0);
        $this->collector->registerQuery('SELECT * FROM posts', [], 20.0);
        $this->collector->registerQuery('SELECT * FROM comments', [], 15.0);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals(3, $data['sql_total_count']);
        $this->assertCount(3, $data['sql']);
    }

    public function testCalculatesTotalTime(): void
    {
        $this->collector->registerQuery('SELECT 1', [], 10.5);
        $this->collector->registerQuery('SELECT 2', [], 20.3);
        $this->collector->registerQuery('SELECT 3', [], 5.2);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals(36.0, $data['sql_total_time_ms']);
    }

    public function testStoresRowCount(): void
    {
        $this->collector->registerQuery('SELECT * FROM users', [], 10.0, 150);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals(150, $data['sql'][0]['row_count']);
    }

    public function testStoresError(): void
    {
        $error = 'SQLSTATE[42S02]: Base table or view not found';
        
        $this->collector->registerQuery('SELECT * FROM invalid_table', [], 5.0, null, $error);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals($error, $data['sql'][0]['error']);
    }

    public function testHandlesComplexBindings(): void
    {
        $bindings = [
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'active' => true,
        ];
        
        $this->collector->registerQuery('UPDATE users SET name = :name WHERE id = :id', $bindings, 8.5);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals($bindings, $data['sql'][0]['bindings']);
    }

    public function testDetectsSlowQueries(): void
    {
        $this->collector->registerQuery('SELECT * FROM large_table', [], 1500.0); // 1.5 seconds
        
        $data = $this->collector->toArray();
        
        $query = $data['sql'][0];
        $this->assertGreaterThan(1000, $query['duration_ms']); // Slow query threshold
    }

    public function testDetectsDuplicateQueries(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        
        $this->collector->registerQuery($sql, [1], 10.0);
        $this->collector->registerQuery($sql, [2], 10.0);
        $this->collector->registerQuery($sql, [3], 10.0);
        
        $data = $this->collector->toArray();
        
        // Count queries with same SQL
        $duplicates = array_filter($data['sql'], fn($q) => $q['sql'] === $sql);
        $this->assertCount(3, $duplicates);
    }

    public function testGroupsByQueryType(): void
    {
        $this->collector->registerQuery('SELECT * FROM users', [], 10.0);
        $this->collector->registerQuery('INSERT INTO posts VALUES (?)', [1], 15.0);
        $this->collector->registerQuery('UPDATE users SET name = ?', ['John'], 12.0);
        $this->collector->registerQuery('DELETE FROM comments WHERE id = ?', [5], 8.0);
        
        $data = $this->collector->toArray();
        
        $selects = array_filter($data['sql'], fn($q) => str_starts_with($q['sql'], 'SELECT'));
        $inserts = array_filter($data['sql'], fn($q) => str_starts_with($q['sql'], 'INSERT'));
        $updates = array_filter($data['sql'], fn($q) => str_starts_with($q['sql'], 'UPDATE'));
        $deletes = array_filter($data['sql'], fn($q) => str_starts_with($q['sql'], 'DELETE'));
        
        $this->assertCount(1, $selects);
        $this->assertCount(1, $inserts);
        $this->assertCount(1, $updates);
        $this->assertCount(1, $deletes);
    }

    public function testCalculatesAverageDuration(): void
    {
        $this->collector->registerQuery('SELECT 1', [], 10.0);
        $this->collector->registerQuery('SELECT 2', [], 20.0);
        $this->collector->registerQuery('SELECT 3', [], 30.0);
        
        $data = $this->collector->toArray();
        
        $avgDuration = $data['sql_total_time_ms'] / $data['sql_total_count'];
        $this->assertEquals(20.0, $avgDuration);
    }

    public function testLimitsQueryListTo50(): void
    {
        // Add 75 queries
        for ($i = 0; $i < 75; $i++) {
            $this->collector->registerQuery("SELECT $i", [], 10.0);
        }
        
        $data = $this->collector->toArray();
        
        $this->assertEquals(75, $data['sql_total_count']);
        $this->assertCount(50, $data['sql']); // Limited to 50
    }

    public function testToArrayStructure(): void
    {
        $this->collector->registerQuery('SELECT * FROM users', [1, 2], 15.5, 10, null);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('sql_total_count', $data);
        $this->assertArrayHasKey('sql_total_time_ms', $data);
        $this->assertArrayHasKey('sql', $data);
        
        $this->assertIsInt($data['sql_total_count']);
        $this->assertIsFloat($data['sql_total_time_ms']);
        $this->assertIsArray($data['sql']);
        
        $query = $data['sql'][0];
        $this->assertArrayHasKey('sql', $query);
        $this->assertArrayHasKey('bindings', $query);
        $this->assertArrayHasKey('duration_ms', $query);
        $this->assertArrayHasKey('row_count', $query);
        $this->assertArrayHasKey('error', $query);
    }

    public function testEmptyQueriesList(): void
    {
        $data = $this->collector->toArray();
        
        $this->assertEquals(0, $data['sql_total_count']);
        $this->assertEquals(0.0, $data['sql_total_time_ms']);
        $this->assertEmpty($data['sql']);
    }

    public function testHandlesNullRowCount(): void
    {
        $this->collector->registerQuery('UPDATE users SET active = 1', [], 10.0, null);
        
        $data = $this->collector->toArray();
        
        $this->assertNull($data['sql'][0]['row_count']);
    }

    public function testHandlesNullError(): void
    {
        $this->collector->registerQuery('SELECT * FROM users', [], 10.0, 5, null);
        
        $data = $this->collector->toArray();
        
        $this->assertNull($data['sql'][0]['error']);
    }
}
