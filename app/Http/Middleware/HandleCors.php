<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\HandleCors as Middleware;

class HandleCors extends Middleware
{
    /**
     * Get the cors configuration.
     *
     * @return array
     */
    protected function corsOptions()
    {
        return [
            'paths' => ['api/*', 'sanctum/csrf-cookie'],
            'allowed_methods' => ['*'],
            'allowed_origins' => ['*'], // Allow all origins
            'allowed_origins_patterns' => ['*'], // Allow all origin patterns
            'allowed_headers' => ['*'],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => true,
        ];
    }
}
