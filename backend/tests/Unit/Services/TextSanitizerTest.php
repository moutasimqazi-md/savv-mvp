<?php

namespace Savv\Tests\Unit\Services;

use Savv\Services\TextSanitizer;
use Savv\Tests\TestCase;

class TextSanitizerTest extends TestCase
{
    public function test_strips_script_tags(): void
    {
        $this->assertSame('alert(1)', TextSanitizer::clean('<script>alert(1)</script>'));
    }

    public function test_strips_arbitrary_html_tags(): void
    {
        $this->assertSame('Synthetic Mouse', TextSanitizer::clean('<b onclick="evil()">Synthetic Mouse</b>'));
    }

    public function test_collapses_repeated_whitespace(): void
    {
        $this->assertSame('a b c', TextSanitizer::clean("a\n\n  b\t\tc"));
    }

    public function test_truncates_to_max_length(): void
    {
        $this->assertSame(10, mb_strlen(TextSanitizer::clean(str_repeat('a', 50), 10)));
    }

    public function test_null_stays_null(): void
    {
        $this->assertNull(TextSanitizer::clean(null));
    }

    public function test_is_empty_detects_blank_and_whitespace_only_strings(): void
    {
        $this->assertTrue(TextSanitizer::isEmpty(null));
        $this->assertTrue(TextSanitizer::isEmpty(''));
        $this->assertTrue(TextSanitizer::isEmpty('   '));
        $this->assertFalse(TextSanitizer::isEmpty('a'));
    }
}
