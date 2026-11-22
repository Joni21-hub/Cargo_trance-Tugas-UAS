<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndBootstrapApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_login_fetch_profile_and_logout(): void
    {
        $login = $this->postJson('/api/v1/login', [
            'email' => 'operator@cargotrace.com',
            'password' => 'password',
        ]);

        $login->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'operator@cargotrace.com',
                        'role' => 'operator',
                    ],
                ],
            ]);

        $profile = $this->getJson('/api/v1/user');
        $profile->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'operator@cargotrace.com',
                    'role' => 'operator',
                ],
            ]);

        $logout = $this->postJson('/api/v1/logout');
        $logout->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_invalid_login_returns_401(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'operator@cargotrace.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_customer_can_register_and_bootstrap_returns_master_data(): void
    {
        $register = $this->postJson('/api/v1/register', [
            'name' => 'PT Nusantara Ekspor',
            'email' => 'baru@nusantara.id',
            'password' => 'secret123',
        ]);

        $register->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'baru@nusantara.id',
                        'role' => 'customer',
                    ],
                ],
            ]);

        $bootstrap = $this->getJson('/api/v1/bootstrap');
        $bootstrap->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'locations',
                    'carriers',
                    'customers',
                    'stats' => [
                        'total_shipments',
                        'pending_confirmation',
                        'in_transit',
                        'delivered',
                        'delayed',
                        'open_exceptions',
                    ],
                ],
            ]);
    }

    public function test_audit_logs_require_operator_or_admin_role(): void
    {
        $customer = User::where('role', 'customer')->firstOrFail();
        $operator = User::where('role', 'operator')->firstOrFail();

        $this->getJson('/api/v1/audit-logs')->assertStatus(401);
        $this->actingAs($customer)->getJson('/api/v1/audit-logs')->assertStatus(403);
        $this->actingAs($operator)->getJson('/api/v1/audit-logs')->assertStatus(200);
    }

    public function test_admin_exclusive_management_endpoints_enforce_rbac_and_persist(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $operator = User::where('role', 'operator')->firstOrFail();

        // Operator cannot access Admin-exclusive endpoints
        $this->actingAs($operator)->getJson('/api/v1/admin/overview')->assertStatus(403);
        $this->actingAs($operator)->getJson('/api/v1/admin/users')->assertStatus(403);

        // Admin gets overview & creates carrier + location
        $this->actingAs($admin)->getJson('/api/v1/admin/overview')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['metrics', 'by_status', 'by_carrier']]);

        $this->actingAs($admin)->postJson('/api/v1/admin/carriers', [
            'code' => 'ZIMU',
            'name' => 'ZIM Integrated Shipping',
            'transport_mode' => 'SEA',
            'contact_email' => 'ops@zim.com',
        ])->assertStatus(201);

        $this->actingAs($admin)->postJson('/api/v1/admin/locations', [
            'code' => 'IDSUB',
            'name' => 'Port of Tanjung Perak (Surabaya)',
            'type' => 'PORT',
            'country' => 'Indonesia',
            'latitude' => -7.2047,
            'longitude' => 112.7294,
        ])->assertStatus(201);

        $this->assertDatabaseHas('carriers', ['code' => 'ZIMU']);
        $this->assertDatabaseHas('locations', ['code' => 'IDSUB']);
    }
}
