<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class SupplierBController extends BaseController
{
    public function search(): ResponseInterface
    {
        $from  = $this->request->getGet('from');
        $to    = $this->request->getGet('to');
        $date  = $this->request->getGet('date');
        $limit = (int) ($this->request->getGet('limit') ?? 8);

        $slowMs = (int) ($this->request->getGet('slow_ms') ?? 0);
        $fail   = filter_var($this->request->getGet('fail'), FILTER_VALIDATE_BOOLEAN);

        if (! $from || ! $to || ! $date) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'from, to, and date are required',
            ]);
        }

        if ($slowMs > 0) {
            usleep($slowMs * 1000);
        }

        if ($fail) {
            return $this->response->setStatusCode(502)->setJSON([
                'supplier' => 'B',
                'error'    => 'simulated_failure',
            ]);
        }

        $logger = service('logger');
        $start  = microtime(true);

        $results = $this->generateResults($from, $to, $date, $limit);

        $timing = [
            'total_ms' => (int) ((microtime(true) - $start) * 1000),
        ];

        $logger->info(json_encode([
            'supplier' => 'B',
            'from' => $from,
            'to' => $to,
            'date' => $date,
            'count' => count($results),
            'timing' => $timing,
        ]));

        return $this->response->setJSON([
            'supplier' => 'B',
            'results'  => $results,
            'meta'     => [
                'currency' => 'USD',
                'source'   => 'mock',
            ],
            'timing'   => $timing,
        ]);
    }

    private function generateResults(string $from, string $to, string $date, int $limit): array
    {
        $carriers = [
            ['code' => 'AK', 'name' => 'AirAsia'],
            ['code' => 'ID', 'name' => 'Batik Air'],
            ['code' => 'NH', 'name' => 'All Nippon Airways'],
        ];
        $results = [];
        for ($i = 0; $i < $limit; $i++) {
            $carrier = $carriers[$i % count($carriers)];
            $results[] = [
                'uid'      => 'B-' . strtoupper($carrier['code']) . '-' . ($i + 200),
                'carrier'  => [
                    'iata' => $carrier['code'],
                    'name' => $carrier['name'],
                ],
                'segments' => [
                    [
                        'flightNo'  => $carrier['code'] . ($i + 500),
                        'orig'      => strtoupper($from),
                        'dest'      => strtoupper($to),
                        'departAt'  => $date . 'T09:' . str_pad((string) ($i % 60), 2, '0', STR_PAD_LEFT) . ':00Z',
                        'arriveAt'  => $date . 'T11:' . str_pad((string) ($i % 60), 2, '0', STR_PAD_LEFT) . ':00Z',
                    ],
                ],
                'pricing'  => [
                    'amount'   => 55 + ($i * 7),
                    'currency' => 'USD',
                ],
            ];
        }
        return $results;
    }
}

