<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinFastRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
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
            'name' => 'required',
            'branch_id' => 'required',
            'entry_id' => 'required',
            'note' => 'required',
            'supplier_id' => 'required',
            'asset_ids' => 'required|string'
        ];
    }
}
