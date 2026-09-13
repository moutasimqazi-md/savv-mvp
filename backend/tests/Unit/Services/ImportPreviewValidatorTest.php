<?php

namespace Savv\Tests\Unit\Services;

use InvalidArgumentException;
use Savv\Enums\Provider;
use Savv\Services\ImportPreviewValidator;
use Savv\Tests\TestCase;

class ImportPreviewValidatorTest extends TestCase
{
    private function validOrder(array $overrides = []): array
    {
        return array_merge([
            'provider_order_id' => 'AMZ-SYNTH-0001',
            'order_date' => '2026-03-12',
            'original_status' => 'Delivered',
            'currency' => 'INR',
            'total' => '1499.00',
            'official_order_url' => 'https://www.amazon.in/gp/css/order-details?orderID=AMZ-SYNTH-0001',
            'observed_at' => '2026-03-12T10:00:00Z',
            'items' => [[
                'title' => 'Synthetic Wireless Mouse',
                'quantity' => 1,
                'unit_price' => '1499.00',
                'line_total' => '1499.00',
                'official_product_url' => 'https://www.amazon.in/dp/SYNTH0001',
            ]],
        ], $overrides);
    }

    public function test_accepts_a_well_formed_order(): void
    {
        $result = ImportPreviewValidator::validateScanResult([$this->validOrder()], Provider::AmazonIn);

        $this->assertCount(1, $result['orders']);
        $this->assertSame('AMZ-SYNTH-0001', $result['orders'][0]['provider_order_id']);
        $this->assertSame(149900, $result['orders'][0]['total']);
        $this->assertSame('delivered', $result['orders'][0]['normalized_status']);
    }

    public function test_rejects_more_orders_than_the_configured_maximum(): void
    {
        config(['savv.imports.max_orders_per_scan' => 2]);

        $this->expectException(InvalidArgumentException::class);
        ImportPreviewValidator::validateScanResult([
            $this->validOrder(['provider_order_id' => 'A']),
            $this->validOrder(['provider_order_id' => 'B']),
            $this->validOrder(['provider_order_id' => 'C']),
        ], Provider::AmazonIn);
    }

    public function test_rejects_unknown_top_level_order_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ImportPreviewValidator::validateScanResult([
            $this->validOrder(['session_token' => 'should-never-be-here']),
        ], Provider::AmazonIn);
    }

    public function test_rejects_forbidden_field_names_anywhere_in_the_payload(): void
    {
        $this->expectException(\RuntimeException::class);
        ImportPreviewValidator::validateScanResult([
            array_merge($this->validOrder(), ['cookie' => 'abc']),
        ], Provider::AmazonIn);
    }

    public function test_rejects_order_missing_provider_order_id(): void
    {
        $order = $this->validOrder();
        unset($order['provider_order_id']);

        $this->expectException(InvalidArgumentException::class);
        ImportPreviewValidator::validateScanResult([$order], Provider::AmazonIn);
    }

    public function test_strips_html_from_item_titles(): void
    {
        $order = $this->validOrder();
        $order['items'][0]['title'] = '<img src=x onerror=alert(1)>Synthetic Mouse';

        $result = ImportPreviewValidator::validateScanResult([$order], Provider::AmazonIn);

        $this->assertSame('Synthetic Mouse', $result['orders'][0]['items'][0]['title']);
    }

    public function test_drops_disallowed_urls_and_keeps_the_rest_of_the_order(): void
    {
        $order = $this->validOrder(['official_order_url' => 'javascript:alert(1)']);

        $result = ImportPreviewValidator::validateScanResult([$order], Provider::AmazonIn);

        $this->assertNull($result['orders'][0]['official_order_url']);
        $this->assertSame('AMZ-SYNTH-0001', $result['orders'][0]['provider_order_id']);
    }

    public function test_unparsable_amount_is_warned_and_left_blank_not_rejected(): void
    {
        $order = $this->validOrder(['total' => 'free shipping']);

        $result = ImportPreviewValidator::validateScanResult([$order], Provider::AmazonIn);

        $this->assertNull($result['orders'][0]['total']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_order_with_no_items_produces_a_warning(): void
    {
        $order = $this->validOrder(['items' => []]);

        $result = ImportPreviewValidator::validateScanResult([$order], Provider::AmazonIn);

        $this->assertNotEmpty($result['warnings']);
    }
}
