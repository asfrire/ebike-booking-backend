<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!File::exists($logPath)) {
            return response()->json(['logs' => []]);
        }

        $logs = File::get($logPath);
        $logLines = array_filter(explode("\n", $logs));
        
        // Get last 50 lines
        $recentLogs = array_slice($logLines, -50);
        
        // Filter for WebSocket logs (with emojis)
        $websocketLogs = array_filter($recentLogs, function($line) {
            return strpos($line, '🔌') !== false || 
                   strpos($line, '🔐') !== false || 
                   strpos($line, '🏍️') !== false || 
                   strpos($line, '📊') !== false || 
                   strpos($line, '👨‍💼') !== false || 
                   strpos($line, '📡') !== false || 
                   strpos($line, '❌') !== false;
        });

        return response()->json([
            'logs' => array_values($websocketLogs),
            'total' => count($websocketLogs)
        ]);
    }

    public function destroy(Request $request)
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (File::exists($logPath)) {
            File::put($logPath, '');
        }

        return response()->json(['message' => 'Logs cleared successfully']);
    }
}
