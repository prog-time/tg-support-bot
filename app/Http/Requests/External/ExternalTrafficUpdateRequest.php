<?php

namespace App\Http\Requests\External;

use Illuminate\Foundation\Http\FormRequest;

class ExternalTrafficUpdateRequest extends FormRequest
{

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
            'message_id' => 'required|numeric',
            'message' => 'required|string',
        ];
    }

    public function messages()
    {
        return [
            'source.required' => 'Поле source обязательно для заполнения',
            'external_id.required' => 'Поле external_id обязательно для заполнения',
        ];
    }

}
