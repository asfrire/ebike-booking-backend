<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$subdivision = App\Models\Subdivision::where('name', 'Sonera')->first();
$service = app(App\Services\BookingService::class);

echo "Debug Phase Determination:\n";
echo "Subdivision: {$subdivision->name} (ID: {$subdivision->id})\n";

$testCases = [
    ['block' => '5', 'lot' => '10', 'expected' => 'Phase 1'], // Block 1-15
    ['block' => '2', 'lot' => '25', 'expected' => 'Phase 1'], // Block 2 Lot 1-57
    ['block' => '20', 'lot' => '15', 'expected' => 'Phase 2'], // Block 16-25
    ['block' => '2', 'lot' => '60', 'expected' => 'Phase 2'], // Block 2 Lot 58+
];

foreach ($testCases as $case) {
    $phase = $service->determinePhase($subdivision->id, $case['block'], $case['lot']);
    $result = $phase ? $phase->name : 'null';
    $status = $result === $case['expected'] ? '✅' : '❌';
    
    echo "{$status} Block {$case['block']}, Lot {$case['lot']}: Expected {$case['expected']}, Got {$result}\n";
}
