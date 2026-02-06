<?php

declare(strict_types=1);

namespace Tests\Audit;

use Echo\Framework\Audit\AuditLogger;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AuditLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset static state before each test
        $this->resetAuditLogger();
    }

    protected function tearDown(): void
    {
        $this->resetAuditLogger();
        parent::tearDown();
    }

    /**
     * Reset AuditLogger static properties using reflection
     */
    private function resetAuditLogger(): void
    {
        $reflection = new \ReflectionClass(AuditLogger::class);

        $userId = $reflection->getProperty('userId');
        $userId->setAccessible(true);
        $userId->setValue(null, null);

        $ipAddress = $reflection->getProperty('ipAddress');
        $ipAddress->setAccessible(true);
        $ipAddress->setValue(null, null);

        $userAgent = $reflection->getProperty('userAgent');
        $userAgent->setAccessible(true);
        $userAgent->setValue(null, null);

        // Reset sensitive fields to default
        $sensitiveFields = $reflection->getProperty('sensitiveFields');
        $sensitiveFields->setAccessible(true);
        $sensitiveFields->setValue(null, [
            'password',
            'password_hash',
            'password_match',
            'token',
            'secret',
            'api_key',
            'api_secret',
            'access_token',
            'refresh_token',
            'private_key',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
        ]);
    }

    /**
     * Test setContext stores user ID
     */
    public function testSetContextStoresUserId(): void
    {
        AuditLogger::setContext(123, '192.168.1.1', 'Mozilla/5.0');

        $this->assertEquals(123, AuditLogger::getUserId());
    }

    /**
     * Test setContext stores IP address
     */
    public function testSetContextStoresIpAddress(): void
    {
        AuditLogger::setContext(123, '192.168.1.1', 'Mozilla/5.0');

        $this->assertEquals('192.168.1.1', AuditLogger::getIpAddress());
    }

    /**
     * Test setContext stores user agent
     */
    public function testSetContextStoresUserAgent(): void
    {
        AuditLogger::setContext(123, '192.168.1.1', 'Mozilla/5.0');

        $this->assertEquals('Mozilla/5.0', AuditLogger::getUserAgent());
    }

    /**
     * Test setContext accepts null values
     */
    public function testSetContextAcceptsNullValues(): void
    {
        AuditLogger::setContext(null, null, null);

        $this->assertNull(AuditLogger::getUserId());
        $this->assertNull(AuditLogger::getIpAddress());
        $this->assertNull(AuditLogger::getUserAgent());
    }

    /**
     * Test getSensitiveFields returns default fields
     */
    public function testGetSensitiveFieldsReturnsDefaults(): void
    {
        $fields = AuditLogger::getSensitiveFields();

        $this->assertContains('password', $fields);
        $this->assertContains('token', $fields);
        $this->assertContains('api_key', $fields);
        $this->assertContains('secret', $fields);
        $this->assertContains('credit_card', $fields);
        $this->assertContains('ssn', $fields);
    }

    /**
     * Test addSensitiveField adds a new field
     */
    public function testAddSensitiveFieldAddsNewField(): void
    {
        AuditLogger::addSensitiveField('custom_secret');

        $this->assertContains('custom_secret', AuditLogger::getSensitiveFields());
    }

    /**
     * Test addSensitiveField does not add duplicates
     */
    public function testAddSensitiveFieldNoDuplicates(): void
    {
        $initialCount = count(AuditLogger::getSensitiveFields());

        AuditLogger::addSensitiveField('password'); // Already exists

        $this->assertCount($initialCount, AuditLogger::getSensitiveFields());
    }

    /**
     * Test filterSensitiveData filters password field
     */
    public function testFilterSensitiveDataFiltersPassword(): void
    {
        $filtered = $this->invokeFilterSensitiveData([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $this->assertArrayHasKey('name', $filtered);
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayNotHasKey('password', $filtered);
    }

    /**
     * Test filterSensitiveData filters token field
     */
    public function testFilterSensitiveDataFiltersToken(): void
    {
        $filtered = $this->invokeFilterSensitiveData([
            'id' => 1,
            'token' => 'abc123xyz',
        ]);

        $this->assertArrayHasKey('id', $filtered);
        $this->assertArrayNotHasKey('token', $filtered);
    }

    /**
     * Test filterSensitiveData is case insensitive
     */
    public function testFilterSensitiveDataCaseInsensitive(): void
    {
        $filtered = $this->invokeFilterSensitiveData([
            'name' => 'John',
            'PASSWORD' => 'secret',
            'Password' => 'secret',
            'TOKEN' => 'abc',
        ]);

        $this->assertArrayHasKey('name', $filtered);
        $this->assertArrayNotHasKey('PASSWORD', $filtered);
        $this->assertArrayNotHasKey('Password', $filtered);
        $this->assertArrayNotHasKey('TOKEN', $filtered);
    }

    /**
     * Test filterSensitiveData partial match
     */
    public function testFilterSensitiveDataPartialMatch(): void
    {
        $filtered = $this->invokeFilterSensitiveData([
            'name' => 'John',
            'user_password' => 'secret',
            'password_hash' => 'hashed',
            'api_token' => 'token123',
            'access_token_id' => 'id123',
        ]);

        $this->assertArrayHasKey('name', $filtered);
        $this->assertArrayNotHasKey('user_password', $filtered);
        $this->assertArrayNotHasKey('password_hash', $filtered);
        $this->assertArrayNotHasKey('api_token', $filtered);
        $this->assertArrayNotHasKey('access_token_id', $filtered);
    }

    /**
     * Test filterSensitiveData filters multiple sensitive fields
     */
    public function testFilterSensitiveDataFiltersMultipleFields(): void
    {
        $filtered = $this->invokeFilterSensitiveData([
            'name' => 'John',
            'password' => 'secret',
            'api_key' => 'key123',
            'credit_card' => '4111111111111111',
            'ssn' => '123-45-6789',
        ]);

        $this->assertEquals(['name' => 'John'], $filtered);
    }

    /**
     * Test filterSensitiveData preserves non-sensitive fields
     */
    public function testFilterSensitiveDataPreservesNonSensitive(): void
    {
        $data = [
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
            'status' => 'active',
            'created_at' => '2024-01-01',
        ];

        $filtered = $this->invokeFilterSensitiveData($data);

        $this->assertEquals($data, $filtered);
    }

    /**
     * Test filterSensitiveData handles empty array
     */
    public function testFilterSensitiveDataHandlesEmptyArray(): void
    {
        $filtered = $this->invokeFilterSensitiveData([]);

        $this->assertEquals([], $filtered);
    }

    /**
     * Test filterSensitiveData filters custom added fields
     */
    public function testFilterSensitiveDataFiltersCustomFields(): void
    {
        AuditLogger::addSensitiveField('custom_field');

        $filtered = $this->invokeFilterSensitiveData([
            'name' => 'John',
            'custom_field' => 'sensitive_value',
        ]);

        $this->assertArrayHasKey('name', $filtered);
        $this->assertArrayNotHasKey('custom_field', $filtered);
    }

    /**
     * Helper to invoke the private filterSensitiveData method
     */
    private function invokeFilterSensitiveData(array $data): array
    {
        $reflection = new \ReflectionClass(AuditLogger::class);
        $method = $reflection->getMethod('filterSensitiveData');
        $method->setAccessible(true);
        return $method->invoke(null, $data);
    }
}
