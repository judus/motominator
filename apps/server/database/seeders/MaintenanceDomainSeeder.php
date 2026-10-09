<?php

namespace Database\Seeders;

use App\Garage\Actions\FulfilMaintenanceTaskOccurrence;
use App\Garage\Enums\HistoryOrigin;
use App\Garage\Enums\MaintenancePerformer;
use App\Models\MaintenanceAction;
use App\Models\MaintenanceCostItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceTask;
use App\Models\MaintenanceTaskOccurrence;
use App\Models\MileageReading;
use App\Models\Motorcycle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaintenanceDomainSeeder extends Seeder
{
    public function run(FulfilMaintenanceTaskOccurrence $fulfil): void
    {
        DB::transaction(function () use ($fulfil): void {
            // Opt-in demonstration data. No assumed service intervals or fake invoices.
            $bike = Motorcycle::factory()->create();
            MileageReading::factory()->for($bike)->create();
            $record = MaintenanceRecord::factory()->for($bike)->create([
                'title' => 'DIY chain inspection',
                'notes' => 'Inspected and lubricated the chain.',
                'performer' => MaintenancePerformer::Self,
                'origin' => HistoryOrigin::Manual,
                'recorded_by_id' => $bike->user_id,
                'cost_amount' => '12.50',
                'currency' => 'CHF',
            ]);
            MaintenanceCostItem::factory()->for($record)->create();
            $action = MaintenanceAction::factory()->for($record)->create();
            $plan = MaintenancePlan::factory()->for($bike)->create(['created_by_id' => $bike->user_id]);
            $task = MaintenanceTask::factory()->for($plan)->create();
            $occurrence = MaintenanceTaskOccurrence::factory()->for($task)->create();
            $fulfil($bike->user, $occurrence, $action);
        });
    }
}
