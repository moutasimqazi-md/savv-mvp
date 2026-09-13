<?php

namespace Savv\Tests\Unit\Support;

use Savv\Support\MarketplaceUrlValidator;
use Savv\Tests\TestCase;

class MarketplaceUrlValidatorTest extends TestCase
{
    public function test_allows_https_amazon_and_claude_links(): void
    {
        $this->assertTrue(MarketplaceUrlValidator::isAllowed('https://www.amazon.in/dp/SYNTH1'));
        $this->assertTrue(MarketplaceUrlValidator::isAllowed('https://claude.ai/settings/billing'));
    }

    public function test_rejects_http_scheme(): void
    {
        $this->assertFalse(MarketplaceUrlValidator::isAllowed('http://www.amazon.in/dp/SYNTH1'));
    }

    public function test_rejects_javascript_and_data_urls(): void
    {
        $this->assertFalse(MarketplaceUrlValidator::isAllowed('javascript:alert(1)'));
        $this->assertFalse(MarketplaceUrlValidator::isAllowed('data:text/html,<script>alert(1)</script>'));
    }

    public function test_rejects_unapproved_hosts(): void
    {
        $this->assertFalse(MarketplaceUrlValidator::isAllowed('https://evil.example.com/dp/SYNTH1'));
    }

    public function test_rejects_lookalike_hosts(): void
    {
        $this->assertFalse(MarketplaceUrlValidator::isAllowed('https://www.amazon.in.evil.com/dp/SYNTH1'));
    }

    public function test_rejects_null_and_empty(): void
    {
        $this->assertFalse(MarketplaceUrlValidator::isAllowed(null));
        $this->assertFalse(MarketplaceUrlValidator::isAllowed(''));
    }

    public function test_sanitize_or_null_returns_null_for_disallowed_url(): void
    {
        $this->assertNull(MarketplaceUrlValidator::sanitizeOrNull('https://evil.example.com/'));
        $this->assertSame(
            'https://www.amazon.in/dp/SYNTH1',
            MarketplaceUrlValidator::sanitizeOrNull('https://www.amazon.in/dp/SYNTH1'),
        );
    }

    public function test_image_cdn_hosts_are_rejected_as_a_link_but_allowed_as_an_image(): void
    {
        $url = 'https://m.media-amazon.com/images/I/example.jpg';

        $this->assertFalse(MarketplaceUrlValidator::isAllowed($url));
        $this->assertTrue(MarketplaceUrlValidator::isAllowed($url, isImage: true));
    }

    public function test_unapproved_host_is_still_rejected_even_as_an_image(): void
    {
        $this->assertFalse(MarketplaceUrlValidator::isAllowed('https://evil.example.com/tracker.jpg', isImage: true));
    }
}
