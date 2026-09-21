<?php

namespace Savv\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Savv\Enums\ImportSessionStatus;
use Savv\Enums\NormalizedStatus;
use Savv\Enums\Provider;
use Savv\Models\ImportPreview;
use Savv\Models\ImportSession;
use Savv\Models\Order;
use Savv\Models\OrderItem;
use Savv\Models\ShipmentEvent;
use Savv\Models\User;
use Savv\Models\UserCorrection;
use Savv\Services\ImportConfirmationService;
use Savv\Tests\TestCase;

class ImportConfirmationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeImportSession(User $user): ImportSession
    {
        return ImportSession::create([
            'user_id' => $user->id,
            'provider' => Provider::AmazonUs,
            'status' => ImportSessionStatus::PreviewReady,
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    private function preview(ImportSession $session, array $orderPayload): ImportPreview
    {
        return $session->previews()->create([
            'provider_order_id' => $orderPayload['provider_order_id'],
            'selected' => true,
            'parser_version' => 'amazon-in@0.1.0-synthetic',
            'observed_at' => now(),
            'normalized_payload' => $orderPayload,
        ]);
    }

    private function baseOrderPayload(array $overrides = []): array
    {
        return array_merge([
            'provider' => 'amazon_us',
            'provider_order_id' => 'AMZ-SYNTH-0001',
            'order_date' => now()->toIso8601String(),
            'original_status' => 'Delivered',
            'normalized_status' => 'delivered',
            'currency' => 'INR',
            'total' => 149900,
            'observed_at' => now()->toIso8601String(),
            'items' => [[
                'title' => 'Synthetic Wireless Mouse',
                'quantity' => 1,
                'unit_price_minor' => 149900,
                'line_total_minor' => 149900,
            ]],
            'shipments' => [],
            'returns' => [],
            'refunds' => [],
            'invoices' => [],
        ], $overrides);
    }

    public function test_confirming_a_preview_creates_an_order_and_its_items(): void
    {
        $user = User::factory()->create();
        $session = $this->makeImportSession($user);
        $preview = $this->preview($session, $this->baseOrderPayload());

        app(ImportConfirmationService::class)->confirm($session, new Collection([$preview]));

        $order = Order::where('user_id', $user->id)->where('provider_order_id', 'AMZ-SYNTH-0001')->first();
        $this->assertNotNull($order);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('Synthetic Wireless Mouse', $order->items()->first()->title);
    }

    public function test_confirming_the_same_order_twice_updates_rather_than_duplicates(): void
    {
        $user = User::factory()->create();

        $session1 = $this->makeImportSession($user);
        app(ImportConfirmationService::class)->confirm($session1, new Collection([
            $this->preview($session1, $this->baseOrderPayload(['original_status' => 'Shipped', 'normalized_status' => 'shipped'])),
        ]));

        $session2 = $this->makeImportSession($user);
        app(ImportConfirmationService::class)->confirm($session2, new Collection([
            $this->preview($session2, $this->baseOrderPayload(['original_status' => 'Delivered', 'normalized_status' => 'delivered'])),
        ]));

        $this->assertSame(1, Order::where('user_id', $user->id)->count());
        $order = Order::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(NormalizedStatus::Delivered, $order->normalized_status);
    }

    public function test_a_user_correction_is_not_overwritten_by_a_later_import(): void
    {
        $user = User::factory()->create();

        $session1 = $this->makeImportSession($user);
        app(ImportConfirmationService::class)->confirm($session1, new Collection([
            $this->preview($session1, $this->baseOrderPayload(['total' => 149900])),
        ]));

        $order = Order::where('user_id', $user->id)->firstOrFail();

        UserCorrection::create([
            'user_id' => $user->id,
            'correctable_type' => Order::class,
            'correctable_id' => $order->id,
            'field' => 'total_minor',
            'previous_value' => '149900',
            'corrected_value' => '99900',
        ]);
        $order->forceFill(['total_minor' => 99900])->save();

        $session2 = $this->makeImportSession($user);
        app(ImportConfirmationService::class)->confirm($session2, new Collection([
            $this->preview($session2, $this->baseOrderPayload(['total' => 149900])),
        ]));

        $this->assertSame(99900, $order->fresh()->total_minor);
    }

    public function test_empty_incoming_values_never_overwrite_populated_ones(): void
    {
        $user = User::factory()->create();

        $session1 = $this->makeImportSession($user);
        app(ImportConfirmationService::class)->confirm($session1, new Collection([
            $this->preview($session1, $this->baseOrderPayload(['original_status' => 'Delivered'])),
        ]));

        $session2 = $this->makeImportSession($user);
        app(ImportConfirmationService::class)->confirm($session2, new Collection([
            $this->preview($session2, $this->baseOrderPayload(['original_status' => null])),
        ]));

        $order = Order::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Delivered', $order->original_status);
    }

    public function test_duplicate_shipment_events_are_ignored_via_event_hash(): void
    {
        $user = User::factory()->create();
        $shipmentPayload = [
            'carrier' => 'Synthetic Logistics Co.',
            'tracking_number' => 'SYNTH-TRACK-0002',
            'original_status' => 'Shipped',
            'normalized_status' => 'shipped',
        ];

        $session1 = $this->makeImportSession($user);
        app(ImportConfirmationService::class)->confirm($session1, new Collection([
            $this->preview($session1, $this->baseOrderPayload(['shipments' => [$shipmentPayload]])),
        ]));

        $session2 = $this->makeImportSession($user);
        app(ImportConfirmationService::class)->confirm($session2, new Collection([
            $this->preview($session2, $this->baseOrderPayload(['shipments' => [$shipmentPayload]])),
        ]));

        $order = Order::where('user_id', $user->id)->firstOrFail();
        $shipment = $order->shipments()->firstOrFail();

        $this->assertSame(1, ShipmentEvent::where('shipment_id', $shipment->id)->count());
    }

    public function test_item_without_provider_item_id_deduplicates_by_fingerprint(): void
    {
        $user = User::factory()->create();

        $session1 = $this->makeImportSession($user);
        app(ImportConfirmationService::class)->confirm($session1, new Collection([
            $this->preview($session1, $this->baseOrderPayload()),
        ]));

        $session2 = $this->makeImportSession($user);
        app(ImportConfirmationService::class)->confirm($session2, new Collection([
            $this->preview($session2, $this->baseOrderPayload()),
        ]));

        $order = Order::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, OrderItem::where('order_id', $order->id)->count());
    }
}
