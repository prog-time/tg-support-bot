<?php

namespace App\Http\Requests\External;

use Illuminate\Foundation\Http\FormRequest;

class ExternalTrafficStoreRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'source' => 'required|string',
            'external_id' => 'required|string',
            'text' => 'nullable|string',
            'attachment' => 'nullable|array',        
        ];
    }

}