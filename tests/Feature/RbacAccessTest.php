<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unauthenticated_user_cannot_access_protected_admin_routes(): void
    {
        $response = $this->postJson('/api/v1/admin/shipments', []);
        $response->assertStatus(401);
    }

    public function test_customer_user_is_forbidden_from_admin_operator_routes(): void
    {
        $customer = User::where('role', 'customer')->firstOrFail();

        $response = $this->actingAs($customer)
            ->postJson('/api/v1/admin/shipments', []);

        $response->assertStatus(403);
    }

    public function test_operator_user_can_access_operator_routes(): void
    {
        $operator = User::where('role', 'operator')->firstOrFail();

        $response = $this->actingAs($operator)
            ->postJson('/api/v1/admin/shipments', [
                // empty payload triggers 422 validation, which proves 403 was passed!
            ]);

        $this->assertNotEquals(403, $response->status());
        $this->assertEquals(422, $response->status());
    }
}
