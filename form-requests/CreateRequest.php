<?php

namespace App\Http\Requests\Admin\Offer;

use App\Helpers\LegalCharsHelper;
use Illuminate\Foundation\Http\FormRequest;
use App\Rules\UrlValidate;
use Illuminate\Validation\Rule;

class CreateRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $this->merge([
            'name' => LegalCharsHelper::removeIllegal($this->get('name')),
            'links' => array_filter($this->get('links', null))
        ]);
    }

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
            'name' => [
                'required',
                'min:3',
                'max:255',
            ],
            'image' => [
                'required',
                'mimes:jpeg,jpg,png',
                'max:' . config('filesystems.max_size_upload')
            ],
            'user_id' => [
                'required',
                'numeric',
                'min:1',
                'exists:users,id',
            ],
            'state' => [
                'required',
            ],
            'dashboard_order' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'url' => ['nullable', new UrlValidate()],
            'additional_url' => ['nullable', new UrlValidate()],
            'landing_id' => [
                Rule::requiredIf(count($this->get('links', [])) === 0),
                'exists:landings,id'
            ],
            'links' => [
                Rule::requiredIf(!$this->get('landing_id')),
                'array'
            ],
            'links.*' => [
                new UrlValidate()
            ],
            'is_default_link' => [
                Rule::requiredIf(!$this->get('landing_id')),
                'array'
            ],
            'is_default_link.*' => [
                'boolean'
            ]
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return \Illuminate\Validation\Validator
     */
    public function withValidator($validator)
    {
        $validator->sometimes('manager_id', 'required|numeric|min:1|exists:users,id', function () {
            return \Auth::user()->isAdmin();
        });

        return $validator;
    }
}
