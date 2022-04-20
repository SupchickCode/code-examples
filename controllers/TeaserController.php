<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeaserResource;
use App\Contracts\TeaserSelectionContract;
use App\Http\Requests\Api\TeaserRequest;

class TeaserController extends Controller
{
    private $teaserSelectionService;

    public function __construct(TeaserSelectionContract $teaserSelectionService)
    {
        $this->middleware('request.logging:daily_api_requests');
        
        $this->teaserSelectionService = $teaserSelectionService;
    }

    public function __invoke(TeaserRequest $request)
    {
        $teasers = $this->teaserSelectionService
            ->setIp($request->ip)
            ->setPageViewUuid($request->page_view_uuid)
            ->setLang($request->lang)
            ->setCount($request->count)
            ->getTeasers($request);

        return TeaserResource::collection($teasers);
    }
}
