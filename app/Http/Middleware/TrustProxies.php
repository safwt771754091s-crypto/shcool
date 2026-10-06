<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fideloper\Proxy\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * Render and Cloudflare sit in front of the Laravel application.
     * Trust their forwarded scheme/host headers so Laravel sees the
     * original HTTPS request instead of the internal HTTP hop.
     *
     * @var array|string|null
     */
    protected $proxies = '*';

    /**
     * The current proxy header mappings.
     *
     * @var int
     */
    protected $headers = [
        Request::HEADER_FORWARDED => 'FORWARDED',
        Request::HEADER_X_FORWARDED_FOR => 'X_FORWARDED_FOR',
        Request::HEADER_X_FORWARDED_HOST => 'X_FORWARDED_HOST',
        Request::HEADER_X_FORWARDED_PORT => 'X_FORWARDED_PORT',
        Request::HEADER_X_FORWARDED_PROTO => 'X_FORWARDED_PROTO',
    ];
}
