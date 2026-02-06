<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\App;

class SupplierAController extends BaseController
{
    public function search(): ResponseInterface
    {
        $origin      = $this->request->getGet('origin');
        $destination = $this->request->getGet('destination');
        $date        = $this->request->getGet('date');
        $limit       = (int) ($this->request->getGet('limit') ?? 10);

        if (! $origin || ! $destination || ! $date) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'origin, destination, and date are required',
            ]);
        }

        $logger = service('logger');
        $start  = microtime(true);

        $key  = getenv('AVIATIONSTACK_KEY') ?: '';
        $base = getenv('AVIATIONSTACK_BASE') ?: 'https://api.aviationstack.com/v1';

        $results = [];
        $timing  = [];
        $error   = null;

        if ($key) {
            $client = service('curlrequest', [
                'timeout' => 5,
            ]);
            $url = $base . '/flights';

            $query = [
                'access_key' => $key,
                'limit'      => $limit,
                'dep_iata'   => $origin,
                'arr_iata'   => $destination,
                // Note: aviationstack free tier may not filter by date reliably; included for completeness
                'flight_date' => $date,
            ];

            try {
                $reqStart = microtime(true);
                $response = $client->get($url, ['query' => $query]);
                $timing['aviationstack_ms'] = (int) ((microtime(true) - $reqStart) * 1000);
                if ($response->getStatusCode() === 200) {
                    $body = json_decode($response->getBody(), true);
                    $data = $body['data'] ?? [];
                    foreach ($data as $item) {
                        $results[] = [
                            'id'            => $item['flight']['iata'] ?? ($item['flight']['number'] ?? uniqid('A-')),
                            'airline_code'  => $item['airline']['iata'] ?? '',
                            'airline_name'  => $item['airline']['name'] ?? '',
                            'flight_number' => $item['flight']['number'] ?? '',
                            'origin'        => $item['departure']['iata'] ?? '',
                            'destination'   => $item['arrival']['iata'] ?? '',
                            'departure_time'=> $item['departure']['scheduled'] ?? $item['departure']['estimated'] ?? null,
                            'arrival_time'  => $item['arrival']['scheduled'] ?? $item['arrival']['estimated'] ?? null,
                            // Supplier A will provide a synthetic price for demo purposes
                            'price'         => $this->syntheticPrice($item),
                            'currency'      => 'USD',
                        ];
                    }
                } else {
                    $error = 'aviationstack_status_' . $response->getStatusCode();
                }
            } catch (\Throwable $e) {
                $error = 'aviationstack_error_' . $e->getMessage();
            }
        } else {
            // Fallback synthetic data if no key provided
            $results = $this->generateSyntheticFlights($origin, $destination, $date, $limit);
        }

        $timing['total_ms'] = (int) ((microtime(true) - $start) * 1000);
        $logger->info(json_encode([
            'supplier' => 'A',
            'origin' => $origin,
            'destination' => $destination,
            'date' => $date,
            'count' => count($results),
            'timing' => $timing,
            'error' => $error,
        ]));

        return $this->response->setJSON([
            'supplier' => 'A',
            'flights'  => $results,
            'timing'   => $timing,
            'error'    => $error,
        ]);
    }

    private function generateSyntheticFlights(string $origin, string $destination, string $date, int $limit): array
    {
        $airlines = [
            ['code' => 'GA', 'name' => 'Garuda Indonesia'],
            ['code' => 'JT', 'name' => 'Lion Air'],
            ['code' => 'QG', 'name' => 'Citilink'],
            ['code' => 'SJ', 'name' => 'Sriwijaya Air'],
        ];
        $results = [];
        for ($i = 0; $i < $limit; $i++) {
            $al = $airlines[$i % count($airlines)];
            $results[] = [
                'id'            => 'A-' . strtoupper($al['code']) . '-' . ($i + 100),
                'airline_code'  => $al['code'],
                'airline_name'  => $al['name'],
                'flight_number' => $al['code'] . ($i + 1000),
                'origin'        => strtoupper($origin),
                'destination'   => strtoupper($destination),
                'departure_time'=> $date . 'T08:' . str_pad((string) ($i % 60), 2, '0', STR_PAD_LEFT) . ':00Z',
                'arrival_time'  => $date . 'T10:' . str_pad((string) ($i % 60), 2, '0', STR_PAD_LEFT) . ':00Z',
                'price'         => 50 + ($i * 5),
                'currency'      => 'USD',
            ];
        }
        return $results;
    }

    private function syntheticPrice(array $item): float
    {
        $base = 80.0;
        $airlineCode = $item['airline']['iata'] ?? '';
        $multiplier  = strlen($airlineCode) ? (ord($airlineCode[0]) % 10) / 10.0 : 0.5;
        return round($base * (1 + $multiplier), 2);
    }
}

