<?php


namespace App\Services\Bitcoin;

use App\Contracts\Bitcoin\ConversionRequestContract;
use App\Contracts\Bitcoin\RegistrationContract;
use App\Core\Bitcoin\RegistrationResponse;
use App\Helpers\RegistrationHelper;
use App\Models\Bitcoin\Provider\Provider;
use App\Models\Bitcoin\Registration;
use App\Models\Conversion;
use App\Models\Offer\Goal;
use App\Models\Transaction;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ConversionRequestService implements ConversionRequestContract
{
    /** @var Transaction  */
    private Transaction $transaction;

    /** @var FormRequest  */
    private FormRequest $request;

    /**
     * @param  FormRequest  $request
     * @return ConversionRequestService
     */
    public function setRequest(FormRequest $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     *
     * @throws Exception
     */
    private function makeTransaction()
    {
        if (!$this->request) {
            throw new Exception('You must provide request');
        }

        $this->transaction = UtilsService::makeTransaction($this->request->only('ip')
                + $this->request->validated(), $this->request->user());
    }

    /**
     * @param  RegistrationContract  $registrationService
     * @return JsonResponse
     * @throws Exception
     */
    public function processResult(RegistrationContract $registrationService): JsonResponse
    {
        $this->makeTransaction();
        $this->findGoal();

        try {
            $result = $registrationService->makeRequest($this->request->all(), $this->transaction)
                ->send();

            if (in_array($result->status(), [
                RegistrationResponse::STATUS_BROKERS_NOT_FOUND,
                RegistrationResponse::STATUS_BROKERS_ALL_REJECT
            ])) {

                return response()->json([
                    'result' => 'error',
                    'message' => 'Registration failed. If you encounter a repeated error, please contact our administrator'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            if ($result->status() === RegistrationResponse::STATUS_FROD) {

                return response()->json([
                    'result' => 'error',
                    'message' => 'Registration duplicated'
                ], Response::HTTP_CONFLICT);
            }

            $addData = [];

            if ($this->transaction->offer->landing->type === Provider::PROVIDER_TYPE_BITCOIN) {
                $addData['redirect_url'] = RegistrationHelper::generateAutoLoginURL($result->registration());
            }

            return response()->json([
                    'result' => 'success',
                    'registration_id' => $result->registration()->id ?? null
                ] + $addData);

        } catch (\Throwable $e) {
            \Log::error('Web API Exception:', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'params' => $this->request->all()
            ]);

            return response()->json([
                'result' => 'error',
                'error' => 'Server error. Please contact our administrator'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Set goal to request
     */
    private function findGoal()
    {
        if (!$offerGoal = Goal::findBy($this->transaction->offer_id, $this->transaction->user_id,
            $this->transaction->country_id)->first()) {
            $offerGoal = Goal::findBy($this->transaction->offer_id, null, $this->transaction->country_id)
                ->first();
        }

        $this->request->merge(['goal_id' => $offerGoal->goal_id]);
    }

    /**
     * @return JsonResponse
     */
    public function deposit(): JsonResponse
    {
        if (!$registration = Registration::query()->withoutGlobalScopes()->whereCustomerId($this->request->get('customer_id'))
            ->when($this->request->get('provider_hash'), function (Builder $builder, $hash) {
                $builder->whereHas('provider', fn(Builder $builder) => $builder->whereUuid($hash));
            })
            ->with('conversion')
            ->first()) {
            return response()->json([
                'error' => 'Registration by combination of customer_id and provider_hash was not found',
                'result' => 'error'
            ]);
        }

        try {
            RegistrationHelper::setState($registration, $this->request->get('status', Conversion::STATUS_APPROVED));
        } catch (\Throwable $e) {
            \Log::channel('daily_integration_deposit_errors')->debug("#{$registration->provider->id} {$registration->provider->name}", [
                'result' => 'error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => $registration
            ]);
        }

        return response()->json([
            'result' => 'success'
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function conversion(): JsonResponse
    {
        try {
            $transaction = Transaction::whereUuid($this->request->input('transaction_id'))
                ->firstOrFail();
            $result = app(RegistrationContract::class)
                ->makeRequest($this->request->all(), $transaction)
                ->send();

            if (in_array($result->status(), [
                RegistrationResponse::STATUS_BROKERS_NOT_FOUND,
                RegistrationResponse::STATUS_BROKERS_ALL_REJECT
            ])) {
                return response()->json([
                    'result' => 'error',
                    'error' => 'A suitable broker was not found or each of them refused a register',
                    'code' => 0
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'result' => 'success',
                'response'  => $result->get(),
                'frod' => $result->status() === RegistrationResponse::STATUS_FROD
            ]);
        } catch (\Throwable $e) {
            \Log::channel('daily_integration_provider_errors')->debug("Registration fail", [
                'result' => 'error',
                'error' => "Failed registration (phone validation error or http request)",
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'params' => $this->request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'result' => 'error',
                'error' => 'Failed registration (phone validation error or http request)',
                'code' => 1
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}