<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PoisRequest extends FormRequest
{
    /**
     * The endpoint is public — no auth required, but rate limited on the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        $validLayers = implode(',', array_keys(config('layers')));

        return [
            'bbox' => ['required', 'string', 'regex:/^-?\d+(\.\d+)?,-?\d+(\.\d+)?,-?\d+(\.\d+)?,-?\d+(\.\d+)?$/'],
            'layer' => ['required', 'string', "in:{$validLayers}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bbox.required' => 'A bounding box is required.',
            'bbox.regex' => 'The bbox must be in the format: lat1,lon1,lat2,lon2.',
            'layer.required' => 'A layer is required.',
            'layer.in' => 'The selected layer is not valid.',
        ];
    }
}
