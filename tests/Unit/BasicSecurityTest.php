<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BasicSecurityTest extends TestCase
{
    public function test_url_sanitizer_rules(): void
    {
        $testUrl = "https://example.com/login?ref=test";
        $parsed = parse_url($testUrl);
        
        $this->assertEquals("https", $parsed['scheme']);
        $this->assertEquals("example.com", $parsed['host']);
    }

    public function test_threat_rules_json_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../../python_scripts/rules.json');
    }
}
