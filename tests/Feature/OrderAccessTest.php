<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A customer can view their own order but not another customer's.
     *
     * @return void
     */
    public function test_customer_cannot_view_another_customers_order(): void
    {
        $customer = User::factory()->create();
        $other_customer = User::factory()->create();
        $own_order = Order::factory()->for($customer)->create();
        $other_order = Order::factory()->for($other_customer)->create();

        $this->assertTrue($customer->can('view', $own_order));
        $this->assertFalse($customer->can('view', $other_order));

        $visible_order_ids = Order::query()->visibleTo($customer)->pluck('id')->all();
        $this->assertSame([$own_order->id], $visible_order_ids);
    }

    /**
     * Guest orders (no user) are not visible to any customer.
     *
     * @return void
     */
    public function test_customer_cannot_view_guest_orders(): void
    {
        $customer = User::factory()->create();
        $guest_order = Order::factory()->guest()->create();

        $this->assertFalse($customer->can('view', $guest_order));
    }

    /**
     * Staff and admin can view every order; only admin can delete.
     *
     * @return void
     */
    public function test_staff_and_admin_see_all_orders_but_only_admin_deletes(): void
    {
        $staff_user = User::factory()->staff()->create();
        $admin_user = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();

        $this->assertTrue($staff_user->can('view', $order));
        $this->assertTrue($admin_user->can('view', $order));
        $this->assertCount(1, Order::query()->visibleTo($staff_user)->get());

        $this->assertFalse($customer->can('delete', $order));
        $this->assertFalse($staff_user->can('delete', $order));
        $this->assertTrue($admin_user->can('delete', $order));
    }

    /**
     * Document permissions follow the order: owner or staff/admin; admin deletes.
     *
     * @return void
     */
    public function test_order_document_policy(): void
    {
        $owner = User::factory()->create();
        $other_customer = User::factory()->create();
        $staff_user = User::factory()->staff()->create();
        $admin_user = User::factory()->admin()->create();
        $order_document = OrderDocument::factory()
            ->for(Order::factory()->for($owner))
            ->create();

        $this->assertTrue($owner->can('view', $order_document));
        $this->assertFalse($other_customer->can('view', $order_document));
        $this->assertTrue($staff_user->can('view', $order_document));

        $this->assertFalse($owner->can('delete', $order_document));
        $this->assertFalse($staff_user->can('delete', $order_document));
        $this->assertTrue($admin_user->can('delete', $order_document));
    }

    /**
     * Ownership, status and amount cannot be mass-assigned from request data.
     *
     * @return void
     */
    public function test_sensitive_order_attributes_are_not_mass_assignable(): void
    {
        $order = new Order([
            'user_id' => 999,
            'status' => 'paid',
            'total_amount' => 1,
            'guest_name' => 'Ada',
        ]);

        $this->assertNull($order->user_id);
        $this->assertSame('pending_payment', $order->status->value);
        $this->assertNull($order->total_amount);
        $this->assertSame('Ada', $order->guest_name);
    }
}
