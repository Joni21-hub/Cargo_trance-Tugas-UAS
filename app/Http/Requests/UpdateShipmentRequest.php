<?php

namespace App\Http\Requests;

use App\Rules\Iso6346Rule;

class UpdateShipmentRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:CREATED,BOOKED,PICKED_UP,IN_TRANSIT,DELIVERED,CANCELLED'],
            'carrier_id' => ['nullable', 'exists:carriers,id'],
            'destination_location_id' => ['nullable', 'exists:locations,id'],
            'current_location_id' => ['nullable', 'exists:locations,id'],
            'container_number' => ['nullable', 'string', 'max:20', new Iso6346Rule()],
            'cargo_description' => ['nullable', 'string', 'max:255'],
            'weight_kg' => ['nullable', 'numeric', 'min:1'],
            'volume_cbm' => ['nullable', 'numeric', 'min:0.1'],
            'expected_departure' => ['nullable', 'date'],
            'expected_arrival' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
