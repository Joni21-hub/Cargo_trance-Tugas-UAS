<?php

namespace App\Http\Requests;

class StoreTrackingEventRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'exists:locations,id'],
            'event_type' => ['required', 'in:DEPARTURE,ARRIVAL,IN_TRANSIT_CHECKPOINT,CUSTOMS_INSPECTION,OUT_FOR_DELIVERY,DELIVERED'],
            'event_time' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
