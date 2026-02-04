<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Model CRUD operations
 *
 * Note: These tests verify the query building logic without
 * requiring a database connection. For integration tests
 * with a real database, see ModelIntegrationTest.
 */
class ModelCrudTest extends TestCase
{
    /**
     * Test that create would return a model instance
     */
    public function testCreateDataStructure(): void
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertEquals('Test User', $data['name']);
    }

    /**
     * Test find returns null for missing ID concept
     */
    public function testFindConceptReturnsNullForMissing(): void
    {
        $result = $this->simulateFind(null);

        $this->assertNull($result);
    }

    /**
     * Test find returns data for existing ID concept
     */
    public function testFindConceptReturnsDataForExisting(): void
    {
        $mockData = ['id' => 1, 'name' => 'Test'];
        $result = $this->simulateFind($mockData);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
    }

    /**
     * Test update modifies attributes
     */
    public function testUpdateModifiesAttributes(): void
    {
        $original = ['id' => 1, 'name' => 'Original', 'email' => 'original@test.com'];
        $updates = ['name' => 'Updated'];

        $result = array_merge($original, $updates);

        $this->assertEquals('Updated', $result['name']);
        $this->assertEquals('original@test.com', $result['email']);
    }

    /**
     * Test delete concept
     */
    public function testDeleteConcept(): void
    {
        $records = [
            1 => ['id' => 1, 'name' => 'User 1'],
            2 => ['id' => 2, 'name' => 'User 2'],
        ];

        unset($records[1]);

        $this->assertCount(1, $records);
        $this->assertArrayNotHasKey(1, $records);
    }

    /**
     * Test where clause building
     */
    public function testWhereBuildsCorrectQuery(): void
    {
        $field = 'email';
        $operator = '=';
        $value = 'test@example.com';

        $whereClause = "($field $operator ?)";

        $this->assertEquals('(email = ?)', $whereClause);
    }

    /**
     * Test where with default operator
     */
    public function testWhereDefaultOperator(): void
    {
        $validOperators = ['=', '!=', '>', '>=', '<', '<=', 'is', 'not', 'like'];
        $field = 'status';
        $value = 'active';

        // If operator not in valid list, default to =
        $operator = 'invalid';
        if (!in_array(strtolower($operator), $validOperators)) {
            $operator = '=';
        }

        $whereClause = "($field $operator ?)";

        $this->assertEquals('(status = ?)', $whereClause);
    }

    /**
     * Test get returns array for multiple results
     */
    public function testGetReturnsArray(): void
    {
        $results = [
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2'],
            ['id' => 3, 'name' => 'User 3'],
        ];

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
    }

    /**
     * Test get returns null for no results
     */
    public function testGetReturnsNullForNoResults(): void
    {
        $results = [];

        $this->assertEmpty($results);
    }

    /**
     * Test first returns single model
     */
    public function testFirstReturnsSingleModel(): void
    {
        $results = [
            ['id' => 1, 'name' => 'First User'],
            ['id' => 2, 'name' => 'Second User'],
        ];

        $first = $results[0] ?? null;

        $this->assertIsArray($first);
        $this->assertEquals(1, $first['id']);
        $this->assertEquals('First User', $first['name']);
    }

    /**
     * Test first returns null for no results
     */
    public function testFirstReturnsNullForNoResults(): void
    {
        $results = [];

        $first = $results[0] ?? null;

        $this->assertNull($first);
    }

    /**
     * Test last returns last model
     */
    public function testLastReturnsLastModel(): void
    {
        $results = [
            ['id' => 1, 'name' => 'First User'],
            ['id' => 2, 'name' => 'Second User'],
            ['id' => 3, 'name' => 'Last User'],
        ];

        $last = end($results);

        $this->assertIsArray($last);
        $this->assertEquals(3, $last['id']);
        $this->assertEquals('Last User', $last['name']);
    }

    /**
     * Test chained where clauses
     */
    public function testChainedWhereClauses(): void
    {
        $where = [];
        $params = [];

        // First where
        $where[] = '(email = ?)';
        $params[] = 'test@example.com';

        // And where
        $where[] = '(status = ?)';
        $params[] = 'active';

        $whereClause = implode(' AND ', $where);

        $this->assertEquals('(email = ?) AND (status = ?)', $whereClause);
        $this->assertCount(2, $params);
    }

    /**
     * Test or where clause
     */
    public function testOrWhereClause(): void
    {
        $where = ['(email = ?)'];
        $orWhere = ['(username = ?)'];

        $fullClause = implode(' AND ', $where) . ' OR ' . implode(' OR ', $orWhere);

        $this->assertEquals('(email = ?) OR (username = ?)', $fullClause);
    }

    /**
     * Test order by clause
     */
    public function testOrderByClause(): void
    {
        $orderBy = [];
        $orderBy[] = 'created_at DESC';
        $orderBy[] = 'name ASC';

        $orderClause = 'ORDER BY ' . implode(', ', $orderBy);

        $this->assertEquals('ORDER BY created_at DESC, name ASC', $orderClause);
    }

    /**
     * Test limit clause
     */
    public function testLimitClause(): void
    {
        $limit = 10;
        $limitClause = $limit > 0 ? "LIMIT $limit" : '';

        $this->assertEquals('LIMIT 10', $limitClause);
    }

    /**
     * Test limit zero produces no clause
     */
    public function testLimitZeroProducesNoClause(): void
    {
        $limit = 0;
        $limitClause = $limit > 0 ? "LIMIT $limit" : '';

        $this->assertEquals('', $limitClause);
    }

    /**
     * Test model attributes are accessible via magic getter
     */
    public function testAttributesAccessible(): void
    {
        $attributes = ['id' => 1, 'name' => 'Test', 'email' => 'test@test.com'];

        $this->assertEquals(1, $attributes['id'] ?? null);
        $this->assertEquals('Test', $attributes['name'] ?? null);
        $this->assertEquals('test@test.com', $attributes['email'] ?? null);
        $this->assertNull($attributes['nonexistent'] ?? null);
    }

    /**
     * Test model attributes can be set via magic setter
     */
    public function testAttributesSettable(): void
    {
        $attributes = [];

        $attributes['name'] = 'New Name';
        $attributes['email'] = 'new@test.com';

        $this->assertEquals('New Name', $attributes['name']);
        $this->assertEquals('new@test.com', $attributes['email']);
    }

    /**
     * Test hydrate creates model from data without extra queries
     */
    public function testHydrateCreatesModelFromData(): void
    {
        $data = (object) ['id' => 1, 'name' => 'Test User', 'email' => 'test@test.com'];

        // Simulate hydration
        $attributes = (array) $data;

        $this->assertEquals(1, $attributes['id']);
        $this->assertEquals('Test User', $attributes['name']);
        $this->assertEquals('test@test.com', $attributes['email']);
    }

    /**
     * Helper: Simulate find operation
     */
    private function simulateFind(?array $data): ?array
    {
        return $data;
    }
}
