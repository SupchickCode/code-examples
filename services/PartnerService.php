<?php

namespace App\Services\Api;

use App\Contracts\PartnerContract;
use App\Http\Requests\TeasersRequest;
use App\Models\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PartnerService implements PartnerContract
{
    /**
     * @var token
     */
    private $token;

    /**
     * @var api_partner
     */
    private $base_uri;

    /**
     * PartnerService constructor.
     */
    public function __construct()
    {
        $this->token = config("api.token");
        $this->base_uri = Str::finish(config("api.url"), '/');
    }

    /**
     * sending data for writing to the affiliate api program.
     *
     * @param array $data
     *
     * @return mixed
     */
    public function sendPage(array $data): mixed
    {
        $url = $this->base_uri . 'showcase/pages';

        return Http::retry(1, 3)
            ->withToken($this->token)
            ->post($url, $data)
            ->throw()
            ->json();
    }

    /**
     * receiving teasers from the affiliate api program.
     *
     * @param array $data
     *
     * @return \Illuminate\Support\Collection
     */
    public function getTeasers(TeasersRequest $request): \Illuminate\Support\Collection
    {
        $page = Page::find($request->page_id);

        $data = [
            'ip'          => $request->ip(),
            'count'       => $request->count,
            'page_id'     => $request->page_id,
            'page_view_uuid' => $request->page_view_uuid,
            'lang'        => optional($page)->language->name ?? config('app.locale'),
            'user_agent'  => $request->userAgent(),
        ];

        $url = $this->base_uri . 'teasers';

        $items = Http::retry(1, 3)
            ->withToken($this->token)
            ->get($url, $data)
            ->throw()
            ->json('data');

        return collect($items);
    }

    /**
     * Receive teasers from the affiliate api programm.
     *
     * @param array $items
     *
     * @return array
     */
    public function filteredArry(array $response): array
    {
        foreach ($response['data'] as $i => $teaser) {
            $teaserHash = md5($teaser['url']);

            if (!Cache::has('teaser_' . $teaserHash)) {
                Cache::put('teaser_' . $teaserHash, $teaser['url']);
            }

            $response['data'][$i]['hash'] = $teaserHash;
        }

        return $response['data'];
    }

    /**
     * Send statistic event to main app
     *
     * @param array $event
     *
     * @return bool
     */
    public function sendEvents(array $events): bool
    {
        try {
            $url = $this->base_uri . 'events';

            info($url);

            $response = Http::retry(1, 3)
                ->withToken($this->token)
                ->post($url, $events)
                ->throw();

            $status = $response->status();
            $resp = $response->json();

            Log::debug('click response:', ['status' => $status, 'response' => $resp]);

            return $response->successful();
            
        } catch (\Throwable $th) {
            Log::error($th);
            return false;
        }
    }
}
