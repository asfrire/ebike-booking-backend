<?php

namespace App\Http\Middleware;

use App\Services\BookingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTimeoutsMiddleware
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for expired assignments on booking-related API calls
        // This prevents the need for background workers on free hosting
        $this->bookingService->checkTimeouts();

        return $next($request);
    }
}
