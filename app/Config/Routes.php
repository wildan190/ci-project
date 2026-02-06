<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Mock suppliers
$routes->get('mock/supplierA', 'SupplierAController::search');
$routes->get('mock/supplierB', 'SupplierBController::search');

// Flight search aggregation API
$routes->get('api/flights/search', 'FlightSearchController::search');

// Swagger docs
$routes->get('docs', 'OpenApiController::docs');
$routes->get('openapi.json', 'OpenApiController::spec');
