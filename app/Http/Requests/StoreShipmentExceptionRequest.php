<?php

namespace App\Http\Requests;

class StoreShipmentExceptionRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tracking_event_id' => ['nullable', 'exists:tracking_events,id'],
            'exception_type' => ['required', 'in:CUSTOMS_HOLD,WEATHER_DELAY,PORT_CONGESTION,DAMAGED,MISROUTED,DOCUMENTATION_ISSUE,VESSEL_BREAKDOWN,DOCUMENTATION_ERROR'],
            'severity' => ['required', 'in:LOW,MEDIUM,HIGH,CRITICAL'],
            'impact_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }
}
