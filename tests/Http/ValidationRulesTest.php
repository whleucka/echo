<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;

class ValidationRulesTest extends TestCase
{
    /**
     * Test the required rule
     */
    public function testRequiredWithValue(): void
    {
        $value = 'test';
        $result = !is_null($value) && $value !== '' && $value !== "NULL";

        $this->assertTrue($result);
    }

    public function testRequiredWithEmptyString(): void
    {
        $value = '';
        $result = !is_null($value) && $value !== '' && $value !== "NULL";

        $this->assertFalse($result);
    }

    public function testRequiredWithNull(): void
    {
        $value = null;
        $result = !is_null($value) && $value !== '' && $value !== "NULL";

        $this->assertFalse($result);
    }

    /**
     * Test the email rule
     */
    public function testEmailWithValidEmail(): void
    {
        $validEmails = [
            'test@example.com',
            'user.name@domain.org',
            'user+tag@example.co.uk',
        ];

        foreach ($validEmails as $email) {
            $this->assertNotFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL),
                "Expected '$email' to be valid"
            );
        }
    }

    public function testEmailWithInvalidEmail(): void
    {
        $invalidEmails = [
            'not-an-email',
            '@missing-local.com',
            'missing-at-sign.com',
            'spaces in@email.com',
        ];

        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL),
                "Expected '$email' to be invalid"
            );
        }
    }

    /**
     * Test the uuid rule
     */
    public function testUuidWithValidUuids(): void
    {
        $validUuids = [
            '550e8400-e29b-41d4-a716-446655440000',
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        ];

        foreach ($validUuids as $uuid) {
            $result = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
            $this->assertEquals(1, $result, "Expected '$uuid' to be valid");
        }
    }

    public function testUuidWithInvalidUuids(): void
    {
        $invalidUuids = [
            'not-a-uuid',
            '550e8400-e29b-41d4-a716',
            '550e8400e29b41d4a716446655440000',
            '550e8400-e29b-41d4-a716-44665544000g',
        ];

        foreach ($invalidUuids as $uuid) {
            $result = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
            $this->assertEquals(0, $result, "Expected '$uuid' to be invalid");
        }
    }

    /**
     * Test the url rule
     */
    public function testUrlWithValidUrls(): void
    {
        $validUrls = [
            'https://example.com',
            'http://www.example.org/path',
            'https://sub.domain.com/path?query=1',
            'ftp://files.example.com',
        ];

        foreach ($validUrls as $url) {
            $this->assertNotFalse(
                filter_var($url, FILTER_VALIDATE_URL),
                "Expected '$url' to be valid"
            );
        }
    }

    public function testUrlWithInvalidUrls(): void
    {
        $invalidUrls = [
            'not-a-url',
            'missing-protocol.com',
            'http:/missing-slash.com',
        ];

        foreach ($invalidUrls as $url) {
            $this->assertFalse(
                filter_var($url, FILTER_VALIDATE_URL),
                "Expected '$url' to be invalid"
            );
        }
    }

    /**
     * Test the ip rule
     */
    public function testIpWithValidIps(): void
    {
        $validIps = [
            '192.168.1.1',
            '10.0.0.1',
            '255.255.255.255',
            '::1',
            '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        ];

        foreach ($validIps as $ip) {
            $this->assertNotFalse(
                filter_var($ip, FILTER_VALIDATE_IP),
                "Expected '$ip' to be valid"
            );
        }
    }

    public function testIpWithInvalidIps(): void
    {
        $invalidIps = [
            'not-an-ip',
            '256.256.256.256',
            '192.168.1',
            '192.168.1.1.1',
        ];

        foreach ($invalidIps as $ip) {
            $this->assertFalse(
                filter_var($ip, FILTER_VALIDATE_IP),
                "Expected '$ip' to be invalid"
            );
        }
    }

    /**
     * Test the ipv4 rule
     */
    public function testIpv4WithValidIps(): void
    {
        $validIpv4 = [
            '192.168.1.1',
            '10.0.0.1',
            '172.16.0.1',
            '255.255.255.255',
            '0.0.0.0',
        ];

        foreach ($validIpv4 as $ip) {
            $this->assertNotFalse(
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4),
                "Expected '$ip' to be valid IPv4"
            );
        }
    }

    public function testIpv4WithIpv6Address(): void
    {
        $ipv6 = '::1';
        $this->assertFalse(
            filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4),
            "Expected IPv6 address to fail IPv4 validation"
        );
    }

    /**
     * Test the ipv6 rule
     */
    public function testIpv6WithValidIps(): void
    {
        $validIpv6 = [
            '::1',
            '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'fe80::1',
            '::ffff:192.168.1.1',
        ];

        foreach ($validIpv6 as $ip) {
            $this->assertNotFalse(
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6),
                "Expected '$ip' to be valid IPv6"
            );
        }
    }

    public function testIpv6WithIpv4Address(): void
    {
        $ipv4 = '192.168.1.1';
        $this->assertFalse(
            filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6),
            "Expected IPv4 address to fail IPv6 validation"
        );
    }

    /**
     * Test the min_length rule
     */
    public function testMinLengthPasses(): void
    {
        $value = 'hello world';
        $minLength = 5;

        $this->assertTrue(strlen($value) >= $minLength);
    }

    public function testMinLengthFails(): void
    {
        $value = 'hi';
        $minLength = 5;

        $this->assertFalse(strlen($value) >= $minLength);
    }

    public function testMinLengthBoundary(): void
    {
        $value = 'hello';
        $minLength = 5;

        $this->assertTrue(strlen($value) >= $minLength);
    }

    /**
     * Test the max_length rule
     */
    public function testMaxLengthPasses(): void
    {
        $value = 'hello';
        $maxLength = 10;

        $this->assertTrue(strlen($value) <= $maxLength);
    }

    public function testMaxLengthFails(): void
    {
        $value = 'hello world';
        $maxLength = 5;

        $this->assertFalse(strlen($value) <= $maxLength);
    }

    public function testMaxLengthBoundary(): void
    {
        $value = 'hello';
        $maxLength = 5;

        $this->assertTrue(strlen($value) <= $maxLength);
    }

    /**
     * Test the numeric rule
     */
    public function testNumericWithValidValues(): void
    {
        $validNumeric = ['123', '45.67', '-123', '0', '1e5'];

        foreach ($validNumeric as $value) {
            $this->assertTrue(
                is_numeric($value),
                "Expected '$value' to be numeric"
            );
        }
    }

    public function testNumericWithInvalidValues(): void
    {
        $invalidNumeric = ['abc', '12abc', '', 'one'];

        foreach ($invalidNumeric as $value) {
            $this->assertFalse(
                is_numeric($value),
                "Expected '$value' to be non-numeric"
            );
        }
    }

    /**
     * Test the integer rule
     */
    public function testIntegerWithValidValues(): void
    {
        $validIntegers = ['123', '-456', '0', '999999'];

        foreach ($validIntegers as $value) {
            $this->assertNotFalse(
                filter_var($value, FILTER_VALIDATE_INT),
                "Expected '$value' to be a valid integer"
            );
        }
    }

    public function testIntegerWithInvalidValues(): void
    {
        $invalidIntegers = ['12.34', 'abc', '12abc', ''];

        foreach ($invalidIntegers as $value) {
            $this->assertFalse(
                filter_var($value, FILTER_VALIDATE_INT),
                "Expected '$value' to be invalid integer"
            );
        }
    }

    /**
     * Test the float rule
     */
    public function testFloatWithValidValues(): void
    {
        $validFloats = ['12.34', '-45.67', '0.0', '1e5', '123'];

        foreach ($validFloats as $value) {
            $this->assertNotFalse(
                filter_var($value, FILTER_VALIDATE_FLOAT),
                "Expected '$value' to be a valid float"
            );
        }
    }

    public function testFloatWithInvalidValues(): void
    {
        $invalidFloats = ['abc', '12abc', ''];

        foreach ($invalidFloats as $value) {
            $this->assertFalse(
                filter_var($value, FILTER_VALIDATE_FLOAT),
                "Expected '$value' to be invalid float"
            );
        }
    }

    /**
     * Test the boolean rule
     */
    public function testBooleanWithValidValues(): void
    {
        $validBooleans = ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'];

        foreach ($validBooleans as $value) {
            $this->assertNotNull(
                filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                "Expected '$value' to be a valid boolean"
            );
        }
    }

    public function testBooleanWithInvalidValues(): void
    {
        $invalidBooleans = ['maybe', '2', 'yep', 'nope'];

        foreach ($invalidBooleans as $value) {
            $this->assertNull(
                filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                "Expected '$value' to be invalid boolean"
            );
        }
    }

    /**
     * Test the regex rule
     */
    public function testRegexMatches(): void
    {
        $value = 'hello123';
        $pattern = '/^[a-z]+[0-9]+$/';

        $this->assertEquals(1, preg_match($pattern, $value));
    }

    public function testRegexDoesNotMatch(): void
    {
        $value = '123hello';
        $pattern = '/^[a-z]+[0-9]+$/';

        $this->assertEquals(0, preg_match($pattern, $value));
    }

    /**
     * Test the mac rule
     */
    public function testMacWithValidAddresses(): void
    {
        $validMacs = [
            '00:1A:2B:3C:4D:5E',
            '00-1A-2B-3C-4D-5E',
            '001A.2B3C.4D5E',
        ];

        foreach ($validMacs as $mac) {
            $this->assertNotFalse(
                filter_var($mac, FILTER_VALIDATE_MAC),
                "Expected '$mac' to be valid MAC"
            );
        }
    }

    public function testMacWithInvalidAddresses(): void
    {
        $invalidMacs = [
            'not-a-mac',
            '00:1A:2B:3C:4D',
            '00:1A:2B:3C:4D:5E:6F',
            'GG:HH:II:JJ:KK:LL',
        ];

        foreach ($invalidMacs as $mac) {
            $this->assertFalse(
                filter_var($mac, FILTER_VALIDATE_MAC),
                "Expected '$mac' to be invalid MAC"
            );
        }
    }

    /**
     * Test the domain rule
     */
    public function testDomainWithValidDomains(): void
    {
        $validDomains = [
            'example.com',
            'sub.example.org',
            'my-site.co.uk',
        ];

        foreach ($validDomains as $domain) {
            $this->assertNotFalse(
                filter_var($domain, FILTER_VALIDATE_DOMAIN),
                "Expected '$domain' to be valid domain"
            );
        }
    }

    /**
     * Test the string rule
     */
    public function testStringWithValidStrings(): void
    {
        $this->assertTrue(is_string('hello'));
        $this->assertTrue(is_string(''));
        $this->assertTrue(is_string('123'));
    }

    public function testStringWithInvalidValues(): void
    {
        $this->assertFalse(is_string(123));
        $this->assertFalse(is_string(12.34));
        $this->assertFalse(is_string(['array']));
        $this->assertFalse(is_string(null));
    }

    /**
     * Test the array rule
     */
    public function testArrayWithValidArrays(): void
    {
        $this->assertTrue(is_array([]));
        $this->assertTrue(is_array([1, 2, 3]));
        $this->assertTrue(is_array(['key' => 'value']));
    }

    public function testArrayWithInvalidValues(): void
    {
        $this->assertFalse(is_array('string'));
        $this->assertFalse(is_array(123));
        $this->assertFalse(is_array(null));
    }

    /**
     * Test the date rule
     */
    public function testDateWithValidDates(): void
    {
        $validDates = [
            '2024-01-15',
            '01/15/2024',
            'January 15, 2024',
            '2024-01-15 10:30:00',
        ];

        foreach ($validDates as $date) {
            $this->assertNotFalse(
                strtotime($date),
                "Expected '$date' to be valid date"
            );
        }
    }

    public function testDateWithInvalidDates(): void
    {
        $invalidDates = [
            'not-a-date',
            '',
        ];

        foreach ($invalidDates as $date) {
            $this->assertFalse(
                strtotime($date),
                "Expected '$date' to be invalid date"
            );
        }
    }

    /**
     * Test the match rule
     */
    public function testMatchWithMatchingValues(): void
    {
        $password = 'secret123';
        $confirmPassword = 'secret123';

        $this->assertTrue($password == $confirmPassword);
    }

    public function testMatchWithNonMatchingValues(): void
    {
        $password = 'secret123';
        $confirmPassword = 'different456';

        $this->assertFalse($password == $confirmPassword);
    }
}
