<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Services\ExceptionManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtaDelayCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_exception_impact_greater_than_or_equal_to_12_hours_flags_shipment_as_delayed(): void
    {
        $shipment = Shipment::where('tracking_number', 'CTG-20261101-0001')->firstOrFail();

        $this->assertFalse($shipment->is_delayed);
        $this->assertEquals(0, $shipment->delay_hours);

        // Record a 15-hour weather delay exception
        $service = app(ExceptionManagementService::class);
        $service->recordException($shipment, [
            'exception_type' => 'WEATHER_DELAY',
            'severity' => 'HIGH',
            'impact_hours' => 15,
            'description' => 'Tropical Typhoon in South China Sea causing vessel holding pattern.',
        ]);

        $shipment->refresh();

        $this->assertTrue($shipment->is_delayed, 'Shipment should be flagged as delayed');
        $this->assertEquals(15, $shipment->delay_hours);
        $this->assertTrue($shipment->calculated_eta->greaterThan($shipment->expected_arrival));
    }

    public function test_exception_impact_under_12_hours_does_not_flag_delay(): void
    {
        $shipment = Shipment::where('tracking_number', 'CTG-20261101-0001')->firstOrFail();

        $service = app(ExceptionManagementService::class);
        $service->recordException($shipment, [
            'exception_type' => 'PORT_CONGESTION',
            'severity' => 'LOW',
            'impact_hours' => 8,
            'description' => 'Minor berth congestion.',
        ]);

        $shipment->refresh();

        $this->assertFalse($shipment->is_delayed, 'Shipment should NOT be flagged as delayed when impact < 12 hours');
        $this->assertEquals(8, $shipment->delay_hours);
    }

    public function test_boundary_exactly_12_hours_impact_flags_delay(): void
    {
        $shipment = Shipment::where('tracking_number', 'CTG-20261101-0001')->firstOrFail();

        $service = app(ExceptionManagementService::class);
        $service->recordException($shipment, [
            'exception_type' => 'CUSTOMS_HOLD',
            'severity' => 'MEDIUM',
            'impact_hours' => 12,
            'description' => 'Documentation review delay.',
        ]);

        $shipment->refresh();

        $this->assertTrue($shipment->is_delayed, 'Shipment should be flagged as delayed at boundary >= 12 hours');
        $this->assertEquals(12, $shipment->delay_hours);
    }

    public function test_resolving_exception_clears_delayed_flag_and_restores_eta(): void
    {
        $shipment = Shipment::where('tracking_number', 'CTG-20261101-0001')->firstOrFail();
        $originalExpectedArrival = $shipment->expected_arrival->copy();

        $service = app(ExceptionManagementService::class);
        $exception = $service->recordException($shipment, [
            'exception_type' => 'VESSEL_BREAKDOWN',
            'severity' => 'HIGH',
            'impact_hours' => 20,
            'description' => 'Engine inspection required.',
        ]);

        $shipment->refresh();
        $this->assertTrue($shipment->is_delayed);
        $this->assertEquals(20, $shipment->delay_hours);

        // Resolve exception
        $service->resolveException($exception, 'Engine repaired and certified seaworthy.');

        $shipment->refresh();
        $this->assertFalse($shipment->is_delayed, 'Shipment delay flag should be cleared once exception is resolved');
        $this->assertEquals(0, $shipment->delay_hours);
        $this->assertEquals(
            $originalExpectedArrival->toDateTimeString(),
            $shipment->calculated_eta->toDateTimeString()
        );
    }

    public function test_multiple_active_exceptions_accumulate_impact_hours(): void
    {
        $shipment = Shipment::where('tracking_number', 'CTG-20261101-0001')->firstOrFail();

        $service = app(ExceptionManagementService::class);
        $service->recordException($shipment, [
            'exception_type' => 'PORT_CONGESTION',
            'severity' => 'LOW',
            'impact_hours' => 6,
            'description' => 'Berth waiting time.',
        ]);

        $shipment->refresh();
        $this->assertFalse($shipment->is_delayed);
        $this->assertEquals(6, $shipment->delay_hours);

        // Add second exception: 6 + 7 = 13 hours (exceeds 12-hour threshold)
        $service->recordException($shipment, [
            'exception_type' => 'CUSTOMS_HOLD',
            'severity' => 'MEDIUM',
            'impact_hours' => 7,
            'description' => 'Secondary inspection.',
        ]);

        $shipment->refresh();
        $this->assertTrue($shipment->is_delayed, 'Accumulated 13 hours should flag shipment as delayed');
        $this->assertEquals(13, $shipment->delay_hours);
    }
}
