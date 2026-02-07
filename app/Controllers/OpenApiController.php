<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class OpenApiController extends BaseController
{
    public function docs(): string
    {
        return view('swagger');
    }

    public function spec(): ResponseInterface
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Flight Search Aggregation API',
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => '/'],
            ],
            'paths' => [
                '/api/flights/search' => [
                    'get' => [
                        'summary' => 'Aggregated flight search',
                        'parameters' => [
                            ['name' => 'origin', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'example' => 'CGK']],
                            ['name' => 'destination', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'example' => 'DPS']],
                            ['name' => 'date', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'date', 'example' => '2026-02-15']],
                            ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20]],
                            ['name' => 'airline', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'sortBy', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['price', 'departure_time', 'arrival_time']]],
                            ['name' => 'sortOrder', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
                            ['name' => 'priceMin', 'in' => 'query', 'schema' => ['type' => 'number']],
                            ['name' => 'priceMax', 'in' => 'query', 'schema' => ['type' => 'number']],
                            ['name' => 'supplierB_slow_ms', 'in' => 'query', 'schema' => ['type' => 'integer', 'example' => 1500]],
                            ['name' => 'supplierB_fail', 'in' => 'query', 'schema' => ['type' => 'boolean', 'example' => false]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'meta' => ['type' => 'object'],
                                                'data' => ['type' => 'array'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '429' => ['description' => 'Rate limit exceeded'],
                        ],
                    ],
                ],
                '/mock/supplierA' => [
                    'get' => [
                        'summary' => 'Mock Supplier A',
                        'parameters' => [
                            ['name' => 'origin', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'destination', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'date', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'date']],
                            ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/mock/supplierB' => [
                    'get' => [
                        'summary' => 'Mock Supplier B (slow/fail simulation)',
                        'parameters' => [
                            ['name' => 'from', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'to', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'date', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'date']],
                            ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'slow_ms', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'fail', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ];

        return $this->response->setJSON($spec);
    }
}
