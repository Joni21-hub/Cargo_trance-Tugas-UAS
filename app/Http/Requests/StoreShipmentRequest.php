<?php

namespace App\Http\Requests;

use App\Rules\Iso6346Rule;

class StoreShipmentRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tracking_number' => [
                'nullable',
                'string',
                'max:50',
                'unique:shipments,tracking_number',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (is_string($value)) {
                        $clean = strtoupper(trim(str_replace([' ', '-'], '', $value)));
                        if (preg_match('/^[A-Z]{4}\d{7}$/', $clean)) {
                            $rule = new Iso6346Rule();
                            $rule->validate($attribute, $clean, $fail);
                        }
                    }
                },
            ],
            'container_number' => ['nullable', 'string', 'max:20', new Iso6346Rule()],
            'customer_id' => ['required', 'exists:users,id'],
            'buyer_id' => ['nullable', 'exists:users,id'],
            'carrier_id' => ['required', 'exists:carriers,id'],
            'origin_location_id' => ['required', 'exists:locations,id'],
            'destination_location_id' => ['required', 'different:origin_location_id', 'exists:locations,id'],
            'cargo_description' => ['required', 'string', 'max:255'],
            'weight_kg' => ['required', 'numeric', 'min:1'],
            'volume_cbm' => ['nullable', 'numeric', 'min:0.1'],
            'expected_departure' => ['required', 'date'],
            'expected_arrival' => ['required', 'date', 'after:expected_departure'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.item_name' => ['required_with:items', 'string'],
            'items.*.quantity' => ['nullable', 'integer'],
            'items.*.weight_kg' => ['nullable', 'numeric'],
            'items.*.condition' => ['nullable', 'string'],
        ];
    }
}
