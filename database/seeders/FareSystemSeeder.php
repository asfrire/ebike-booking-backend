<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subdivision;
use App\Models\Phase;
use App\Models\Fare;

class FareSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create subdivisions
        $primera = Subdivision::create(['name' => 'Primera']);
        $sonera = Subdivision::create(['name' => 'Sonera']);

        $this->command->info('✅ Created subdivisions: Primera, Sonera');

        // Create phases
        $primeraPhase1 = Phase::create([
            'subdivision_id' => $primera->id,
            'name' => 'Phase 1',
        ]);

        $soneraPhase1 = Phase::create([
            'subdivision_id' => $sonera->id,
            'name' => 'Phase 1',
        ]);

        $soneraPhase2 = Phase::create([
            'subdivision_id' => $sonera->id,
            'name' => 'Phase 2',
        ]);

        $this->command->info('✅ Created phases: Primera Phase 1, Sonera Phase 1, Sonera Phase 2');

        // Create fares
        Fare::create([
            'subdivision_id' => $primera->id,
            'phase_id' => $primeraPhase1->id,
            'fare_per_passenger' => 25.00,
        ]);

        Fare::create([
            'subdivision_id' => $sonera->id,
            'phase_id' => $soneraPhase1->id,
            'fare_per_passenger' => 25.00,
        ]);

        Fare::create([
            'subdivision_id' => $sonera->id,
            'phase_id' => $soneraPhase2->id,
            'fare_per_passenger' => 30.00,
        ]);

        $this->command->info('✅ Created fares:');
        $this->command->info('   - Primera Phase 1: ₱25.00');
        $this->command->info('   - Sonera Phase 1: ₱25.00');
        $this->command->info('   - Sonera Phase 2: ₱30.00');

        $this->command->info('🎉 Fare system seeded successfully!');
        $this->command->info('');
        $this->command->info('Sonera Phase Mapping:');
        $this->command->info('Phase 1: Block 1-15 OR Block 2 Lot 1-57');
        $this->command->info('Phase 2: Block 16-25 OR Block 2 Lot 58+');
    }
}
