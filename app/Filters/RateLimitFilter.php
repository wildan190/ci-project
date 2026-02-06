<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $limit   = isset($arguments[0]) ? (int) $arguments[0] : 30;
        $window  = isset($arguments[1]) ? (int) $arguments[1] : 60;
        $ip      = $request->getIPAddress();
        $path    = $request->getUri()->getPath();
        $key     = 'rl_' . md5($ip . '|' . $path);
        $cache   = cache();
        $current = $cache->get($key);
        if ($current === null) {
            $cache->save($key, 1, $window);
            return;
        }
        if ($current >= $limit) {
            $response = service('response');
            return $response->setStatusCode(429)->setJSON([
                'error' => 'rate_limit_exceeded',
                'limit' => $limit,
                'window_seconds' => $window,
            ]);
        }
        $cache->save($key, $current + 1, $window);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
