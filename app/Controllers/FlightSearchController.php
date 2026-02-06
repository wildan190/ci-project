<?php

namespace App\Controllers;

use App\Services\FlightSearchService;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

class FlightSearchController extends ResourceController
{
    public function search(): ResponseInterface
    {
        $rules = [
            'origin'      => 'required|alpha_numeric|exact_length[3]',
            'destination' => 'required|alpha_numeric|exact_length[3]',
            'date'        => 'required|valid_date',
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $params = [
            'origin'      => strtoupper($this->request->getGet('origin')),
            'destination' => strtoupper($this->request->getGet('destination')),
            'date'        => $this->request->getGet('date'),
            'limit'       => (int) ($this->request->getGet('limit') ?? 20),
            'airline'     => $this->request->getGet('airline'),
            'sortBy'      => $this->request->getGet('sortBy') ?? 'price',
            'sortOrder'   => strtolower($this->request->getGet('sortOrder') ?? 'asc'),
            'priceMin'    => $this->request->getGet('priceMin'),
            'priceMax'    => $this->request->getGet('priceMax'),
            'supplierB_slow_ms' => $this->request->getGet('supplierB_slow_ms'),
            'supplierB_fail'    => $this->request->getGet('supplierB_fail'),
        ];

        $service = new FlightSearchService();
        $start   = microtime(true);
        $agg     = $service->search($params);
        $results = $agg['results'];

        // Filtering
        if (! empty($params['airline'])) {
            $needle  = strtolower($params['airline']);
            $results = array_values(array_filter($results, static function ($r) use ($needle) {
                return str_contains(strtolower($r['airline'] ?? ''), $needle)
                    || str_contains(strtolower($r['airline_code'] ?? ''), $needle);
            }));
        }
        if ($params['priceMin'] !== null) {
            $min = (float) $params['priceMin'];
            $results = array_values(array_filter($results, static fn($r) => (float) ($r['price'] ?? 0) >= $min));
        }
        if ($params['priceMax'] !== null) {
            $max = (float) $params['priceMax'];
            $results = array_values(array_filter($results, static fn($r) => (float) ($r['price'] ?? 0) <= $max));
        }

        // Sorting
        $sortBy    = in_array($params['sortBy'], ['price', 'departure_time', 'arrival_time'], true) ? $params['sortBy'] : 'price';
        $sortOrder = $params['sortOrder'] === 'desc' ? 'desc' : 'asc';
        usort($results, static function ($a, $b) use ($sortBy, $sortOrder) {
            $va = $a[$sortBy] ?? null;
            $vb = $b[$sortBy] ?? null;
            if ($va == $vb) {
                return 0;
            }
            if ($sortOrder === 'asc') {
                return $va <=> $vb;
            }
            return $vb <=> $va;
        });

        $durationMs = (int) ((microtime(true) - $start) * 1000);
        service('logger')->info(json_encode([
            'endpoint' => 'api/flights/search',
            'params'   => $params,
            'count'    => count($results),
            'timings'  => array_merge($agg['timings'], ['total_ms' => $durationMs]),
            'errors'   => $agg['errors'],
        ]));

        return $this->respond([
            'meta' => [
                'count'   => count($results),
                'errors'  => $agg['errors'],
                'timings' => array_merge($agg['timings'], ['total_ms' => $durationMs]),
            ],
            'data' => $results,
        ]);
    }
}

