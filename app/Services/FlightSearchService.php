<?php

namespace App\Services;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\App;

class FlightSearchService
{
    private CURLRequest $http;
    private CacheInterface $cache;
    private string $baseUrl;
    private string $supplierBaseUrl;

    public function __construct()
    {
        $this->http    = service('curlrequest', ['timeout' => 3]);
        $this->cache   = cache();
        $config        = config(App::class);
        $this->baseUrl = rtrim($config->baseURL, '/');
        $envBase       = getenv('SUPPLIER_BASE_URL') ?: null;
        $this->supplierBaseUrl = $envBase ? rtrim($envBase, '/') : $this->baseUrl;
    }

    public function search(array $params): array
    {
        $cacheKey = $this->cacheKey($params);
        $cached   = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return ['results' => $cached, 'timings' => ['cache' => true], 'errors' => []];
        }

        $timings = [];
        $errors  = [];
        $merged  = [];

        [$aData, $aTiming, $aError] = $this->callSupplierA($params);
        $timings['supplierA_ms']     = $aTiming;
        if ($aError !== null) {
            $errors[] = ['supplier' => 'A', 'error' => $aError];
        } else {
            $merged = array_merge($merged, $this->normalizeSupplierA($aData));
        }

        [$bData, $bTiming, $bError] = $this->callSupplierB($params);
        $timings['supplierB_ms']     = $bTiming;
        if ($bError !== null) {
            $errors[] = ['supplier' => 'B', 'error' => $bError];
        } else {
            $merged = array_merge($merged, $this->normalizeSupplierB($bData));
        }

        $this->cache->save($cacheKey, $merged, 30);

        return ['results' => $merged, 'timings' => $timings, 'errors' => $errors];
    }

    private function callSupplierA(array $params): array
    {
        $url = $this->supplierBaseUrl . '/mock/supplierA';
        $qs  = [
            'origin'      => $params['origin'],
            'destination' => $params['destination'],
            'date'        => $params['date'],
            'limit'       => $params['limit'] ?? 10,
        ];
        $start = microtime(true);
        try {
            $resp = $this->http->get($url, ['query' => $qs]);
            $ms   = (int) ((microtime(true) - $start) * 1000);
            if ($resp->getStatusCode() !== 200) {
                return [[], $ms, 'status_' . $resp->getStatusCode()];
            }
            $payload = json_decode($resp->getBody(), true);
            return [$payload, $ms, null];
        } catch (\Throwable $e) {
            $ms = (int) ((microtime(true) - $start) * 1000);
            return [[], $ms, 'error_' . $e->getMessage()];
        }
    }

    private function callSupplierB(array $params): array
    {
        $url = $this->supplierBaseUrl . '/mock/supplierB';
        $qs  = [
            'from'   => $params['origin'],
            'to'     => $params['destination'],
            'date'   => $params['date'],
            'limit'  => $params['limit'] ?? 8,
            'slow_ms'=> $params['supplierB_slow_ms'] ?? null,
            'fail'   => $params['supplierB_fail'] ?? null,
        ];
        // Remove nulls
        $qs = array_filter($qs, static fn($v) => $v !== null);
        $start = microtime(true);
        try {
            $resp = $this->http->get($url, ['query' => $qs]);
            $ms   = (int) ((microtime(true) - $start) * 1000);
            if ($resp->getStatusCode() !== 200) {
                return [[], $ms, 'status_' . $resp->getStatusCode()];
            }
            $payload = json_decode($resp->getBody(), true);
            return [$payload, $ms, null];
        } catch (\Throwable $e) {
            $ms = (int) ((microtime(true) - $start) * 1000);
            return [[], $ms, 'error_' . $e->getMessage()];
        }
    }

    private function normalizeSupplierA(array $payload): array
    {
        $list = $payload['flights'] ?? [];
        $out  = [];
        foreach ($list as $f) {
            $out[] = [
                'id'             => $f['id'] ?? '',
                'supplier'       => 'A',
                'airline'        => $f['airline_name'] ?? '',
                'airline_code'   => $f['airline_code'] ?? '',
                'flight_number'  => $f['flight_number'] ?? '',
                'origin'         => $f['origin'] ?? '',
                'destination'    => $f['destination'] ?? '',
                'departure_time' => $f['departure_time'] ?? null,
                'arrival_time'   => $f['arrival_time'] ?? null,
                'price'          => $f['price'] ?? 0,
                'currency'       => $f['currency'] ?? 'USD',
            ];
        }
        return $out;
    }

    private function normalizeSupplierB(array $payload): array
    {
        $list = $payload['results'] ?? [];
        $out  = [];
        foreach ($list as $r) {
            $seg = $r['segments'][0] ?? [];
            $pricing = $r['pricing'] ?? [];
            $carrier = $r['carrier'] ?? [];
            $out[] = [
                'id'             => $r['uid'] ?? '',
                'supplier'       => 'B',
                'airline'        => $carrier['name'] ?? '',
                'airline_code'   => $carrier['iata'] ?? '',
                'flight_number'  => $seg['flightNo'] ?? '',
                'origin'         => $seg['orig'] ?? '',
                'destination'    => $seg['dest'] ?? '',
                'departure_time' => $seg['departAt'] ?? null,
                'arrival_time'   => $seg['arriveAt'] ?? null,
                'price'          => $pricing['amount'] ?? 0,
                'currency'       => $pricing['currency'] ?? 'USD',
            ];
        }
        return $out;
    }

    private function cacheKey(array $params): string
    {
        $keyParts = [
            $params['origin'] ?? '',
            $params['destination'] ?? '',
            $params['date'] ?? '',
            $params['limit'] ?? '',
            $params['airline'] ?? '',
            $params['sortBy'] ?? '',
            $params['sortOrder'] ?? '',
            $params['priceMin'] ?? '',
            $params['priceMax'] ?? '',
        ];
        return 'flights_' . md5(implode('|', $keyParts));
    }
}
