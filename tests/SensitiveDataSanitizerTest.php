<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests;

use Doppar\Insight\Support\SensitiveDataSanitizer;

class SensitiveDataSanitizerTest extends TestCase
{
    public function testSanitizesNestedSensitiveKeys(): void
    {
        $sanitizer = new SensitiveDataSanitizer(['internal_code']);

        $result = $sanitizer->sanitize([
            'user' => [
                'email' => 'user@example.test',
                'password' => 'secret-value',
                'internal_code' => 'private-value',
            ],
            'access_token' => 'token-value',
        ]);

        $this->assertSame('user@example.test', $result['user']['email']);
        $this->assertSame(SensitiveDataSanitizer::REDACTED, $result['user']['password']);
        $this->assertSame(SensitiveDataSanitizer::REDACTED, $result['user']['internal_code']);
        $this->assertSame(SensitiveDataSanitizer::REDACTED, $result['access_token']);
    }

    public function testRedactsSqlBindingsByDefault(): void
    {
        $sanitizer = new SensitiveDataSanitizer();

        $result = $sanitizer->sanitizeSqlBindings(['email', 'password']);

        $this->assertSame([
            SensitiveDataSanitizer::REDACTED,
            SensitiveDataSanitizer::REDACTED,
        ], $result);
    }

    public function testSanitizesJsonAndRedactsRawBodies(): void
    {
        $sanitizer = new SensitiveDataSanitizer();

        $json = $sanitizer->sanitizeRawBody('{"email":"user@example.test","password":"secret"}');
        $raw = $sanitizer->sanitizeRawBody('password=secret&email=user@example.test');

        $this->assertSame(SensitiveDataSanitizer::REDACTED, $json['password']);
        $this->assertSame('user@example.test', $json['email']);
        $this->assertSame(SensitiveDataSanitizer::REDACTED, $raw);
    }

    public function testSanitizesSensitiveValuesInText(): void
    {
        $sanitizer = new SensitiveDataSanitizer();

        $result = $sanitizer->sanitizeText('authorization: Bearer secret-token');

        $this->assertSame('authorization: [REDACTED]', $result);
    }
}
