<?php

namespace App\Http\Requests;

use App\Helpers\Helper;
use Illuminate\Http\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DateComparisonRequest extends FormRequest
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
            'purchase_date' => 'required|date',
            'expiration_date' => 'required|date|after:purchase_date'
        ];
    }

    public function messages()
    {
        return [
            'expiration_date.after' => 'The expiration date must be later than the purchase date.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(Helper::formatStandardApiResponse('error', null, $validator->errors()), Response::HTTP_BAD_REQUEST));
    }
}
