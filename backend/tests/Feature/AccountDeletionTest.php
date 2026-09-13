<?php

namespace Savv\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Savv\Enums\NormalizedStatus;
use Savv\Enums\Provider;
use Savv\Models\Order;
use Savv\Models\User;
use Savv\Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_deletion_requires_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'wrong-password']);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_account_deletion_removes_the_user_and_cascades_to_orders(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        Order::create([
            'user_id' => $user->id,
            'provider' => Provider::AmazonIn,
            'provider_order_id' => 'AMZ-SYNTH-0001',
            'normalized_status' => NormalizedStatus::Delivered,
            'currency' => 'INR',
        ]);

        $response = $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'correct-password']);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_data_export_includes_the_users_orders(): void
    {
        $user = User::factory()->create();
        Order::create([
            'user_id' => $user->id,
            'provider' => Provider::AmazonIn,
            'provider_order_id' => 'AMZ-SYNTH-0001',
            'normalized_status' => NormalizedStatus::Delivered,
            'currency' => 'INR',
        ]);

        $response = $this->actingAs($user)->get(route('account.export'));

        $response->assertOk();
        $response->assertJsonPath('orders.0.provider_order_id', 'AMZ-SYNTH-0001');
    }

    public function test_a_guest_cannot_export_or_delete_an_account(): void
    {
        $this->get(route('account.export'))->assertRedirect(route('login'));
        $this->delete(route('account.destroy'))->assertRedirect(route('login'));
    }
}
