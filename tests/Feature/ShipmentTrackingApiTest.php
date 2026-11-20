<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_shipments_api_returns_json_list(): void
    {
        $response = $this->getJson('/api/v1/shipments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'tracking_number',
                            'status',
                            'cargo_description',
                            'carrier',
                            'origin_location',
                            'destination_location',
                        ]
                    ]
                ]
            ]);

        $this->assertTrue($response->json('success'));
    }

    public function test_public_single_shipment_api_returns_detailed_tracking_info(): void
    {
        $response = $this->getJson('/api/v1/shipments/CTG-20261101-0001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tracking_number' => 'CTG-20261101-0001',
                    'status' => 'IN_TRANSIT',
                ]
            ]);
    }
}
