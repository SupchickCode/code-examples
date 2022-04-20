<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\ConversionStatusEnum;
use App\Helpers\ConversionStatusHelper;
use App\Models\StatTeaserClick;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConversionRequest extends FormRequest
{
    /**
     * @return bool 
     */
    public function authorize(): bool
    {
        $statTeaserClick = StatTeaserClick::where('uuid', $this->click_uuid)->first();

        // If click belong to this user
        if ($statTeaserClick && $this->user()) {
            return $statTeaserClick->advertiser_id === $this->user()->id;
        }

        return false;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'click_uuid'        => 'required|string|exists:stat_teaser_clicks,uuid',
            'adv_internal_id'   => 'required|string',
            'status'            => [
                'required',
                'string',
                Rule::in(ConversionStatusEnum::allowed())
            ],

            'pending_status'    => 'required|string',
            'approved_status'   => 'required|string',
            'rejected_status'   => 'required|string',
            'paid_status'       => 'required|string',
            'payout'            => 'required',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $status = ConversionStatusHelper::getInternalStatus(
            $this->only([
                ConversionStatusHelper::PENDING_STATUS,
                ConversionStatusHelper::APPROVED_STATUS,
                ConversionStatusHelper::REJECTED_STATUS,
                ConversionStatusHelper::PAID_STATUS,
            ]),
            $this->status
        );

        if ($status) {
            $this->merge(['status' => $status]);
        }
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = (new ValidationException($validator))->errors();

        throw new HttpResponseException(response()->json($errors, JsonResponse::HTTP_UNPROCESSABLE_ENTITY));
    }
}
