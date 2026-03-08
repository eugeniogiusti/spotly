<?php

namespace App\Http\Requests\Api;

use App\Enums\PoiTagEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Validates the tag value when toggling a community tag on a POI.
 * The tag must be a valid PoiTagEnum case.
 */
class TogglePoiTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'tag' => ['required', new Enum(PoiTagEnum::class)],
        ];
    }
}
