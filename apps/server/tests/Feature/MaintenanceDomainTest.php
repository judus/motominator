<?php

namespace Tests\Feature;

use App\Garage\Actions\AttachMaintenanceEvidence;
use App\Garage\Actions\FulfilMaintenanceTaskOccurrence;
use App\Garage\Enums\MaintenanceActionType;
use App\Garage\Enums\MaintenanceOccurrenceStatus;
use App\Garage\Enums\MaintenancePerformer;
use App\Garage\Enums\MileageUnit;
use App\Garage\Enums\ReadingCertainty;
use App\Garage\Exceptions\GarageMaintenanceException;
use App\Models\EvidenceDocument;
use App\Models\InvoiceImport;
use App\Models\MaintenanceAction;
use App\Models\MaintenanceCostItem;
use App\Models\MaintenanceFulfilment;
use App\Models\MaintenanceInvoice;
use App\Models\MaintenanceInvoiceItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceTask;
use App\Models\MaintenanceTaskOccurrence;
use App\Models\MileageReading;
use App\Models\Motorcycle;
use Database\Seeders\MaintenanceDomainSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaintenanceDomainTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function testDatedMileageIsIndependentOfInvoicesAndPreservesUnitsAndCertainty(): void
    {
        $bike = Motorcycle::factory()->create(['odometer_km' => 55000]);
        $reading = MileageReading::factory()->for($bike)->create([
            'odometer_value' => '49500.500',
            'unit' => MileageUnit::Miles,
            'certainty' => ReadingCertainty::Approximate,
        ]);
        $this->assertSame('49500.500', $reading->refresh()->odometer_value);
        $this->assertSame(MileageUnit::Miles, $reading->refresh()->unit);
        $this->assertSame(ReadingCertainty::Approximate, $reading->refresh()->certainty);
        $this->assertSame(55000, $bike->refresh()->odometer_km);
        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function testDiyEntriesAcceptOptionalExactCostsWithoutAnInvoice(): void
    {
        $record = MaintenanceRecord::factory()->create(
            ['performer' => MaintenancePerformer::Self, 'notes' => 'Checked chain tension.']
        );
        $action = MaintenanceAction::factory()->for($record)->create();
        $cost = MaintenanceCostItem::factory()->for($record)->create();
        $this->assertSame(MaintenancePerformer::Self, $record->refresh()->performer);
        $this->assertSame('12.50', $record->costItems()->sole()->total_amount);
        $this->assertSame($record->id, $action->maintenanceRecord->id);
        $this->assertNull($cost->invoiceItem);
        $this->assertDatabaseCount('maintenance_invoices', 0);
    }

    public function testEvidenceCanSupportSeveralEntriesWithoutDuplicateAttachments(): void
    {
        $first = MaintenanceRecord::factory()->create();
        $secondBike = Motorcycle::factory()->for($first->motorcycle->user)->create();
        $second = MaintenanceRecord::factory()->for($secondBike)->create();
        $document = EvidenceDocument::factory()->for($first->motorcycle->user)->create();
        $attach = app(AttachMaintenanceEvidence::class);
        $attach($first->motorcycle->user, $first, $document);
        $attach($first->motorcycle->user, $first, $document);
        $attach($first->motorcycle->user, $second, $document);
        $this->assertDatabaseCount('maintenance_evidence', 2);
        $this->assertCount(2, $document->maintenanceRecords);
        $this->assertDatabaseCount('user_activities', 2);
        $this->assertArrayNotHasKey('path', $document->toArray());
        $this->assertArrayNotHasKey('sha256', $document->toArray());
    }

    public function testForeignEvidenceCannotBeAttachedEvenByAnAdministrator(): void
    {
        $record = MaintenanceRecord::factory()->create();
        $actor = $record->motorcycle->user;
        $actor->forceFill(['is_admin' => true])->save();
        $document = EvidenceDocument::factory()->create();
        try {
            app(AttachMaintenanceEvidence::class)($actor, $record, $document);
            $this->fail('Foreign evidence must be rejected.');
        } catch (ModelNotFoundException $exception) {
            $this->assertInstanceOf(ModelNotFoundException::class, $exception);
        }
        $this->assertDatabaseCount('maintenance_evidence', 0);
    }

    /** @return array{MaintenanceTaskOccurrence, MaintenanceAction} */
    private function taskAndWork(): array
    {
        $action = MaintenanceAction::factory()->create();
        $plan = MaintenancePlan::factory()->for($action->maintenanceRecord->motorcycle)->create();
        $task = MaintenanceTask::factory()->for($plan)->create();

        return [MaintenanceTaskOccurrence::factory()->for($task)->create(), $action];
    }

    public function testPartialFulfilmentCanBeCompletedAndRetriesAreIdempotent(): void
    {
        [$occurrence, $action] = $this->taskAndWork();
        $actor = $action->maintenanceRecord->motorcycle->user;
        $fulfil = app(FulfilMaintenanceTaskOccurrence::class);
        $first = $fulfil($actor, $occurrence, $action, false);
        $this->assertSame(MaintenanceOccurrenceStatus::Partial, $occurrence->refresh()->status);
        $second = $fulfil($actor, $occurrence, $action);
        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->completed);
        $fulfil($actor, $occurrence, $action);
        $this->assertSame(MaintenanceOccurrenceStatus::Completed, $occurrence->refresh()->status);
        $this->assertDatabaseCount('maintenance_fulfilments', 1);
        $this->assertDatabaseCount('user_activities', 2);
    }

    public function testOneActionCannotCompleteLaterOccurrencesOfTheSameTask(): void
    {
        [$occurrence, $action] = $this->taskAndWork();
        $actor = $action->maintenanceRecord->motorcycle->user;
        app(FulfilMaintenanceTaskOccurrence::class)($actor, $occurrence, $action);
        $later = MaintenanceTaskOccurrence::factory()->for($occurrence->maintenanceTask)->create();
        try {
            app(FulfilMaintenanceTaskOccurrence::class)($actor, $later, $action);
            $this->fail('One action must not fulfil multiple cycles.');
        } catch (GarageMaintenanceException $exception) {
            $this->assertSame('action_already_used', $exception->reason);
        }
        $this->assertSame(MaintenanceOccurrenceStatus::Open, $later->refresh()->status);
        $this->assertDatabaseCount('maintenance_fulfilments', 1);
    }

    public function testInspectionCannotFulfilAReplacementTask(): void
    {
        [$occurrence, $action] = $this->taskAndWork();
        $occurrence->maintenanceTask->update(['type' => MaintenanceActionType::Replace]);
        $this->expectException(ValidationException::class);
        app(FulfilMaintenanceTaskOccurrence::class)($action->maintenanceRecord->motorcycle->user, $occurrence, $action);
    }

    public function testFrontAndRearComponentsAreNotInterchangeable(): void
    {
        [$occurrence, $action] = $this->taskAndWork();
        $occurrence->maintenanceTask->update(['position' => 'rear']);
        $action->update(['position' => 'front']);
        $this->expectException(ValidationException::class);
        app(FulfilMaintenanceTaskOccurrence::class)($action->maintenanceRecord->motorcycle->user, $occurrence, $action);
    }

    public function testWorkFromAnotherMotorcycleIsRejected(): void
    {
        [$occurrence, $action] = $this->taskAndWork();
        $foreign = MaintenanceAction::factory()->create();
        $this->expectException(ModelNotFoundException::class);
        app(
            FulfilMaintenanceTaskOccurrence::class
        )($action->maintenanceRecord->motorcycle->user, $occurrence, $foreign);
    }

    public function testSkippedOccurrencesAndWorkBeforeTheWindowAreRejected(): void
    {
        [$occurrence, $action] = $this->taskAndWork();
        $actor = $action->maintenanceRecord->motorcycle->user;
        $occurrence->update(['window_starts_on' => '2025-07-01']);
        try {
            app(FulfilMaintenanceTaskOccurrence::class)($actor, $occurrence, $action);
            $this->fail('Old work must not fulfil a new window.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('action', $exception->errors());
        }
        $occurrence->update(['status' => MaintenanceOccurrenceStatus::Skipped]);
        $this->expectException(GarageMaintenanceException::class);
        app(FulfilMaintenanceTaskOccurrence::class)($actor, $occurrence, $action);
    }

    public function testBackfillPreservesOriginalEvidenceAndExactCostsAndIsRepeatable(): void
    {
        $import = InvoiceImport::factory()->create(['status' => 'confirmed']);
        $record = MaintenanceRecord::factory()->for($import->motorcycle)->create();
        $invoice = MaintenanceInvoice::factory()->create(
            ['invoice_import_id' => $import->id, 'maintenance_record_id' => $record->id, 'currency' => 'CHF']
        );
        $item = MaintenanceInvoiceItem::factory()->for($invoice)->create(
            ['net_amount' => '70.00', 'tax_amount' => '5.20']
        );
        $original = DB::table('maintenance_invoice_items')->where('id', $item->id)->first();
        $backfill = require database_path('migrations/2026_10_09_115126_backfill_maintenance_evidence_and_costs.php');
        $up = [$backfill, 'up'];
        $this->assertIsCallable($up);
        $up();
        $up();
        $this->assertDatabaseCount('evidence_documents', 1);
        $this->assertDatabaseCount('maintenance_evidence', 1);
        $this->assertDatabaseCount('maintenance_cost_items', 1);
        $this->assertEquals($original, DB::table('maintenance_invoice_items')->where('id', $item->id)->first());
        $this->assertSame($import->path, $import->refresh()->evidenceDocument()->firstOrFail()->path);
        $this->assertSame('75.20', $record->costItems()->sole()->total_amount);
        $this->assertSame('5.20', $record->costItems()->sole()->tax_amount);
        $this->assertSame($item->id, $record->costItems()->sole()->invoiceItem()->firstOrFail()->id);
        $this->assertDatabaseCount('maintenance_actions', 0);
        $this->assertDatabaseCount('mileage_readings', 0);
    }

    public function testUnverifiedActorsCannotAttachEvidenceOrConfirmWork(): void
    {
        [$occurrence, $action] = $this->taskAndWork();
        $record = $action->maintenanceRecord;
        $actor = $record->motorcycle->user;
        $actor->forceFill(['email_verified_at' => null])->save();
        $document = EvidenceDocument::factory()->for($actor)->create();
        foreach (
            [
                fn () => app(AttachMaintenanceEvidence::class)($actor, $record, $document),
                fn () => app(FulfilMaintenanceTaskOccurrence::class)($actor, $occurrence, $action),
            ] as $write
        ) {
            try {
                $write();
                $this->fail('Unverified actors must not write.');
            } catch (AuthorizationException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->assertDatabaseCount('maintenance_evidence', 0);
        $this->assertDatabaseCount('maintenance_fulfilments', 0);
        $this->assertDatabaseCount('user_activities', 0);
    }

    public function testFulfilmentsProtectSupportingWorkFromDeletion(): void
    {
        [$occurrence, $action] = $this->taskAndWork();
        app(FulfilMaintenanceTaskOccurrence::class)($action->maintenanceRecord->motorcycle->user, $occurrence, $action);
        $this->expectException(QueryException::class);
        $action->delete();
    }

    public function testDatabaseRejectsAFulfilmentLinkedToTheWrongTask(): void
    {
        [$occurrence, $action] = $this->taskAndWork();
        $other = MaintenanceTask::factory()->create();
        $this->expectException(QueryException::class);
        MaintenanceFulfilment::factory()->create([
            'maintenance_task_occurrence_id' => $occurrence->id,
            'maintenance_task_id' => $other->id,
            'maintenance_action_id' => $action->id,
        ]);
    }

    public function testPublicFillableAttributesExcludeOwnershipAndStorageKeys(): void
    {
        $document = new EvidenceDocument();
        $reading = new MileageReading();
        $fulfilment = new MaintenanceFulfilment();
        foreach (['user_id', 'uploaded_by_id', 'disk', 'path', 'sha256'] as $field) {
            $this->assertFalse($document->isFillable($field));
        }
        foreach (['motorcycle_id', 'recorded_by_id', 'maintenance_record_id', 'evidence_document_id'] as $field) {
            $this->assertFalse($reading->isFillable($field));
        }
        $this->assertFalse($fulfilment->isFillable('maintenance_task_id'));
        $this->assertFalse($fulfilment->isFillable('confirmed_by_id'));
    }

    public function testFactoriesAndOptInSeederBuildCoherentRelationships(): void
    {
        $fulfilment = MaintenanceFulfilment::factory()->create();
        $this->assertSame(
            $fulfilment->maintenanceTask->maintenancePlan->motorcycle_id,
            $fulfilment->maintenanceAction->maintenanceRecord->motorcycle_id
        );
        $this->seed(MaintenanceDomainSeeder::class);
        $this->assertDatabaseCount('maintenance_fulfilments', 2);
        $this->assertDatabaseCount('mileage_readings', 1);
        $this->assertSame(
            MaintenanceOccurrenceStatus::Completed,
            MaintenanceTaskOccurrence::query()->latest('id')->firstOrFail()->status
        );
    }
}
