<?php

namespace Savv\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Savv\Enums\NormalizedStatus;
use Savv\Enums\Provider;
use Savv\Models\Order;
use Savv\Models\User;
use Savv\Tests\TestCase;

class OrderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $owner): Order
    {
        return Order::create([
            'user_id' => $owner->id,
            'provider' => Provider::AmazonIn,
            'provider_order_id' => 'AMZ-SYNTH-0001',
            'normalized_status' => NormalizedStatus::Delivered,
            'currency' => 'INR',
            'total_minor' => 149900,
        ]);
    }

    public function test_a_user_cannot_view_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = $this->makeOrder($owner);

        $response = $this->actingAs($intruder)->get(route('orders.show', $order));

        $response->assertForbidden();
    }

    public function test_a_user_can_view_their_own_order(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);

        $response = $this->actingAs($owner)->get(route('orders.show', $order));

        $response->assertOk();
    }

    public function test_a_user_cannot_delete_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = $this->makeOrder($owner);

        $response = $this->actingAs($intruder)->delete(route('orders.destroy', $order));

        $response->assertForbidden();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'deleted_at' => null]);
    }

    public function test_a_user_can_delete_their_own_order(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);

        $response = $this->actingAs($owner)->delete(route('orders.destroy', $order));

        $response->assertRedirect(route('orders.index'));
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_orders_index_only_shows_the_current_users_orders(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->makeOrder($owner);
        $this->makeOrder($other);

        $response = $this->actingAs($owner)->get(route('orders.index'));

        $response->assertOk();
        $response->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);

        $this->get(route('orders.show', $order))->assertRedirect(route('login'));
    }
}
