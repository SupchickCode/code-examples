<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Core\Interfaces\QueueInterface;
use App\Core\StatisticEvents\FingerprintEvent;
use App\Core\Interfaces\SessionRepositoryInterface;

class FingerprintController extends Controller
{
    /**
     * @var QueueInterface
     */
    private $eventQueue;

    /**
     * FingerprintController constructor.
     */
    public function __construct(
        SessionRepositoryInterface $sessionRepository,
        QueueInterface $eventQueue
    ) {
        $this->repository = $sessionRepository;

        $this->eventQueue = $eventQueue;
    }

    /**
     * Create event and send to main app
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $event = new FingerprintEvent($request);

        $this->eventQueue->push($event->tojson());

        return response()->json([
            'status' => 'success'
        ]);
    }
}
