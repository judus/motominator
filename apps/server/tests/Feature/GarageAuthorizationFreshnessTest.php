<?php

namespace Tests\Feature;

use App\Garage\Actions\AttachMaintenanceEvidence;
use App\Garage\Actions\FulfilMaintenanceTaskOccurrence;
use App\Garage\Actions\SaveMaintenanceRecord;
use App\Garage\Actions\SaveMotorcycle;
use App\Models\EvidenceDocument;
use App\Models\MaintenanceAction;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceTask;
use App\Models\MaintenanceTaskOccurrence;
use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GarageAuthorizationFreshnessTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['create', false])]
    #[TestWith(['update', false])]
    #[TestWith(['maintenance', false])]
    #[TestWith(['evidence', false])]
    #[TestWith(['fulfil', false])]
    #[TestWith(['create', true])]
    #[TestWith(['update', true])]
    #[TestWith(['maintenance', true])]
    #[TestWith(['evidence', true])]
    #[TestWith(['fulfil', true])]
    public function testRevokedVerificationOrAdminAccessRejectsStaleActors(string $operation, bool $admin): void
    {
        $bike = Motorcycle::factory()->create();
        $owner = $bike->user;
        $actor = $admin ? User::factory()->create(['is_admin' => true]) : $owner;
        $record = MaintenanceRecord::factory()->for($bike)->create();
        $document = EvidenceDocument::factory()->create(['user_id' => $owner->id]);
        $work = MaintenanceAction::factory()->for($record)->create();
        $plan = MaintenancePlan::factory()->for($bike)->create();
        $task = MaintenanceTask::factory()->for($plan)->create();
        $occurrence = MaintenanceTaskOccurrence::factory()->for($task)->create();
        $beforeBike = $bike->refresh()->getRawOriginal();
        $beforeRecord = $record->refresh()->getRawOriginal();
        $beforeOccurrence = $occurrence->refresh()->getRawOriginal();
        User::query()->findOrFail($actor->id)->forceFill(
            $admin ? ['is_admin' => false] : ['email_verified_at' => null]
        )->save();
        $input = ['make' => 'Ducati', 'model' => 'Changed', 'year' => 2022, 'odometer_km' => 60000];

        try {
            match ($operation) {
                'create' => app(SaveMotorcycle::class)($actor, $input, owner: $owner),
                'update' => app(SaveMotorcycle::class)($actor, $input, $bike),
                'maintenance' => app(SaveMaintenanceRecord::class)($actor, $bike, [
                    'performed_on' => '2025-06-01', 'odometer_km' => 60000, 'title' => 'Changed',
                ], $record),
                'evidence' => app(AttachMaintenanceEvidence::class)($actor, $record, $document),
                'fulfil' => app(FulfilMaintenanceTaskOccurrence::class)($actor, $occurrence, $work),
                default => throw new \LogicException('Unknown test operation.'),
            };
            $this->fail('Writes must recheck current verification and admin privileges.');
        } catch (AuthorizationException) {
            $this->assertSame($beforeBike, $bike->refresh()->getRawOriginal());
            $this->assertSame($beforeRecord, $record->refresh()->getRawOriginal());
            $this->assertSame($beforeOccurrence, $occurrence->refresh()->getRawOriginal());
            $this->assertDatabaseCount('motorcycles', 1);
            $this->assertDatabaseCount('maintenance_records', 1);
            $this->assertDatabaseCount('maintenance_evidence', 0);
            $this->assertDatabaseCount('maintenance_fulfilments', 0);
            $this->assertDatabaseCount('user_activities', 0);
        }
    }
}
