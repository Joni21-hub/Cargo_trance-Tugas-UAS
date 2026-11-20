<?php

namespace App\Services;

use App\Models\Shipment;
use Carbon\Carbon;

class EtaCalculationService
{
    /**
     * Recalculate ETA and delay status for a shipment.
     * Formula: calculated_eta = expected_arrival + sum(active_exceptions.impact_hours)
     */
    public function recalculate(Shipment $shipment): Shipment
    {
        $baseExpectedArrival = Carbon::parse($shipment->expected_arrival);

        // Sum of impact hours from active exceptions
        $totalImpactHours = (int) $shipment->activeExceptions()->sum('impact_hours');

        $calculatedEta = (clone $baseExpectedArrival)->addHours($totalImpactHours);

        $thresholdHours = (int) config('cargotrace.delay_threshold_hours', 12);

        $delayDiffHours = $baseExpectedArrival->diffInHours($calculatedEta, false);

        $isDelayed = $delayDiffHours >= $thresholdHours;

        $shipment->calculated_eta = $calculatedEta;
        $shipment->delay_hours = max(0, $delayDiffHours);
        $shipment->is_delayed = $isDelayed;
        $shipment->save();

        return $shipment;
    }
}
