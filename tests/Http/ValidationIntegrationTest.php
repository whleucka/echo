<?php

declare(strict_types=1);

namespace Tests\Http;

use Echo\Framework\Http\Controller;
use Echo\Framework\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Controller validation
 */
class ValidationIntegrationTest extends TestCase
{
    private ValidationTestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ValidationTestController();
    }

    private function validateWithData(array $data, array $rules): mixed
    {
        $request = new Request(request: $data);
        $this->controller->setRequest($request);
        return $this->controller->validate($rules);
    }

    // Required rule tests
    public function testRequiredWithValue(): void
    {
        $result = $this->validateWithData(
            ['name' => 'John'],
            ['name' => ['required']]
        );

        $this->assertNotNull($result);
        $this->assertEquals('John', $result->name);
    }

    public function testRequiredWithEmptyStringFails(): void
    {
        $result = $this->validateWithData(
            ['name' => ''],
            ['name' => ['required']]
        );

        $this->assertNull($result);
        $errors = $this->controller->getValidationErrors();
        $this->assertArrayHasKey('name', $errors);
    }

    public function testRequiredWithNullFails(): void
    {
        $result = $this->validateWithData(
            [],
            ['name' => ['required']]
        );

        $this->assertNull($result);
    }

    // Email rule tests
    public function testEmailWithValidEmail(): void
    {
        $result = $this->validateWithData(
            ['email' => 'test@example.com'],
            ['email' => ['email']]
        );

        $this->assertNotNull($result);
        $this->assertEquals('test@example.com', $result->email);
    }

    public function testEmailWithInvalidEmailFails(): void
    {
        $result = $this->validateWithData(
            ['email' => 'not-an-email'],
            ['email' => ['email']]
        );

        $this->assertNull($result);
    }

    // UUID rule tests
    public function testUuidWithValidUuid(): void
    {
        $result = $this->validateWithData(
            ['id' => '550e8400-e29b-41d4-a716-446655440000'],
            ['id' => ['uuid']]
        );

        $this->assertNotNull($result);
    }

    public function testUuidWithInvalidUuidFails(): void
    {
        $result = $this->validateWithData(
            ['id' => 'not-a-uuid'],
            ['id' => ['uuid']]
        );

        $this->assertNull($result);
    }

    // URL rule tests
    public function testUrlWithValidUrl(): void
    {
        $result = $this->validateWithData(
            ['website' => 'https://example.com'],
            ['website' => ['url']]
        );

        $this->assertNotNull($result);
    }

    public function testUrlWithInvalidUrlFails(): void
    {
        $result = $this->validateWithData(
            ['website' => 'not-a-url'],
            ['website' => ['url']]
        );

        $this->assertNull($result);
    }

    // IP rule tests
    public function testIpWithValidIpv4(): void
    {
        $result = $this->validateWithData(
            ['ip' => '192.168.1.1'],
            ['ip' => ['ip']]
        );

        $this->assertNotNull($result);
    }

    public function testIpWithValidIpv6(): void
    {
        $result = $this->validateWithData(
            ['ip' => '::1'],
            ['ip' => ['ip']]
        );

        $this->assertNotNull($result);
    }

    public function testIpv4WithValidAddress(): void
    {
        $result = $this->validateWithData(
            ['ip' => '10.0.0.1'],
            ['ip' => ['ipv4']]
        );

        $this->assertNotNull($result);
    }

    public function testIpv4WithIpv6Fails(): void
    {
        $result = $this->validateWithData(
            ['ip' => '::1'],
            ['ip' => ['ipv4']]
        );

        $this->assertNull($result);
    }

    public function testIpv6WithValidAddress(): void
    {
        $result = $this->validateWithData(
            ['ip' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334'],
            ['ip' => ['ipv6']]
        );

        $this->assertNotNull($result);
    }

    // Min/Max length tests
    public function testMinLengthPasses(): void
    {
        $result = $this->validateWithData(
            ['password' => 'password123'],
            ['password' => ['min_length:8']]
        );

        $this->assertNotNull($result);
    }

    public function testMinLengthFails(): void
    {
        $result = $this->validateWithData(
            ['password' => 'short'],
            ['password' => ['min_length:8']]
        );

        $this->assertNull($result);
    }

    public function testMaxLengthPasses(): void
    {
        $result = $this->validateWithData(
            ['username' => 'john'],
            ['username' => ['max_length:20']]
        );

        $this->assertNotNull($result);
    }

    public function testMaxLengthFails(): void
    {
        $result = $this->validateWithData(
            ['username' => 'this_username_is_way_too_long'],
            ['username' => ['max_length:20']]
        );

        $this->assertNull($result);
    }

    // Numeric type tests
    public function testNumericWithValidValue(): void
    {
        $result = $this->validateWithData(
            ['amount' => '123.45'],
            ['amount' => ['numeric']]
        );

        $this->assertNotNull($result);
    }

    public function testNumericWithInvalidValueFails(): void
    {
        $result = $this->validateWithData(
            ['amount' => 'abc'],
            ['amount' => ['numeric']]
        );

        $this->assertNull($result);
    }

    public function testIntegerWithValidValue(): void
    {
        $result = $this->validateWithData(
            ['count' => '42'],
            ['count' => ['integer']]
        );

        $this->assertNotNull($result);
    }

    public function testIntegerWithFloatFails(): void
    {
        $result = $this->validateWithData(
            ['count' => '42.5'],
            ['count' => ['integer']]
        );

        $this->assertNull($result);
    }

    public function testFloatWithValidValue(): void
    {
        $result = $this->validateWithData(
            ['price' => '19.99'],
            ['price' => ['float']]
        );

        $this->assertNotNull($result);
    }

    // Boolean tests
    public function testBooleanWithTrue(): void
    {
        $result = $this->validateWithData(
            ['active' => 'true'],
            ['active' => ['boolean']]
        );

        $this->assertNotNull($result);
    }

    public function testBooleanWithFalse(): void
    {
        $result = $this->validateWithData(
            ['active' => 'false'],
            ['active' => ['boolean']]
        );

        $this->assertNotNull($result);
    }

    public function testBooleanWithOne(): void
    {
        $result = $this->validateWithData(
            ['active' => '1'],
            ['active' => ['boolean']]
        );

        $this->assertNotNull($result);
    }

    public function testBooleanWithInvalidValueFails(): void
    {
        $result = $this->validateWithData(
            ['active' => 'maybe'],
            ['active' => ['boolean']]
        );

        $this->assertNull($result);
    }

    // String and array tests
    public function testStringWithValidValue(): void
    {
        $result = $this->validateWithData(
            ['name' => 'John Doe'],
            ['name' => ['string']]
        );

        $this->assertNotNull($result);
    }

    public function testArrayWithValidValue(): void
    {
        $request = new Request(request: ['tags' => ['php', 'laravel', 'testing']]);
        $this->controller->setRequest($request);
        $result = $this->controller->validate(['tags' => ['array']]);

        $this->assertNotNull($result);
    }

    // Date tests
    public function testDateWithValidDate(): void
    {
        $result = $this->validateWithData(
            ['birthday' => '2024-01-15'],
            ['birthday' => ['date']]
        );

        $this->assertNotNull($result);
    }

    public function testDateWithInvalidDateFails(): void
    {
        $result = $this->validateWithData(
            ['birthday' => 'not-a-date'],
            ['birthday' => ['date']]
        );

        $this->assertNull($result);
    }

    // MAC address tests
    public function testMacWithValidAddress(): void
    {
        $result = $this->validateWithData(
            ['mac' => '00:1A:2B:3C:4D:5E'],
            ['mac' => ['mac']]
        );

        $this->assertNotNull($result);
    }

    public function testMacWithInvalidAddressFails(): void
    {
        $result = $this->validateWithData(
            ['mac' => 'invalid-mac'],
            ['mac' => ['mac']]
        );

        $this->assertNull($result);
    }

    // Domain tests
    public function testDomainWithValidDomain(): void
    {
        $result = $this->validateWithData(
            ['domain' => 'example.com'],
            ['domain' => ['domain']]
        );

        $this->assertNotNull($result);
    }

    // Regex tests
    public function testRegexMatches(): void
    {
        $result = $this->validateWithData(
            ['code' => 'ABC123'],
            ['code' => ['regex:^[A-Z]+[0-9]+$']]
        );

        $this->assertNotNull($result);
    }

    public function testRegexDoesNotMatch(): void
    {
        $result = $this->validateWithData(
            ['code' => '123ABC'],
            ['code' => ['regex:^[A-Z]+[0-9]+$']]
        );

        $this->assertNull($result);
    }

    // Match rule tests
    public function testMatchWithMatchingFields(): void
    {
        $result = $this->validateWithData(
            ['password' => 'secret123', 'confirm_password' => 'secret123'],
            ['password' => ['required'], 'confirm_password' => ['match:password']]
        );

        $this->assertNotNull($result);
    }

    public function testMatchWithNonMatchingFieldsFails(): void
    {
        $result = $this->validateWithData(
            ['password' => 'secret123', 'confirm_password' => 'different'],
            ['password' => ['required'], 'confirm_password' => ['match:password']]
        );

        $this->assertNull($result);
    }

    // Multiple rules tests
    public function testMultipleRulesAllPass(): void
    {
        $result = $this->validateWithData(
            ['email' => 'test@example.com'],
            ['email' => ['required', 'email']]
        );

        $this->assertNotNull($result);
    }

    public function testMultipleRulesOneFails(): void
    {
        $result = $this->validateWithData(
            ['email' => 'not-an-email'],
            ['email' => ['required', 'email']]
        );

        $this->assertNull($result);
    }

    // Multiple fields tests
    public function testMultipleFieldsValidation(): void
    {
        $result = $this->validateWithData(
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => '25'
            ],
            [
                'name' => ['required', 'string'],
                'email' => ['required', 'email'],
                'age' => ['required', 'integer']
            ]
        );

        $this->assertNotNull($result);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
        $this->assertEquals('25', $result->age);
    }

    // Empty rules returns value
    public function testEmptyRulesReturnsValue(): void
    {
        $result = $this->validateWithData(
            ['field' => 'value'],
            ['field' => []]
        );

        $this->assertNotNull($result);
        $this->assertEquals('value', $result->field);
    }

    // Validation errors tests
    public function testValidationErrorsAreCollected(): void
    {
        $this->validateWithData(
            ['email' => 'invalid', 'password' => ''],
            ['email' => ['email'], 'password' => ['required', 'min_length:8']]
        );

        $errors = $this->controller->getValidationErrors();

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }
}

/**
 * Test controller for validation tests
 */
class ValidationTestController extends Controller
{
    public function validate(array $ruleset = [], mixed $id = null): mixed
    {
        return parent::validate($ruleset, $id);
    }

    public function getValidationErrors(): array
    {
        return parent::getValidationErrors();
    }
}
