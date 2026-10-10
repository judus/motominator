<?php

namespace Tests\Feature;

use App\Activity\Actions\RecordUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Garage\Actions\Invoices\ConfirmInvoiceDraft;
use App\Garage\Actions\Invoices\UploadInvoice;
use App\Garage\Enums\HistoryOrigin;
use App\Garage\Exceptions\GarageInvoiceStorageException;
use App\Garage\Enums\MaintenancePerformer;
use App\Models\InvoiceImport;
use App\Models\MaintenanceInvoice;
use App\Models\Motorcycle;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Laravel\Telescope\Telescope;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Fixtures\InvoiceDraft;
use Tests\TestCase;

class InvoiceImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function path(InvoiceImport $import): string
    {
        return "/api/v1/motorcycles/{$import->motorcycle_id}/invoice-imports/{$import->id}";
    }

    #[TestWith(['line-a'])]
    #[TestWith([-1])]
    #[TestWith([1])]
    #[TestWith([65536])]
    public function testNonListDraftItemsAreRejectedBeforeAnyPersistence(string|int $key): void
    {
        $draft = InvoiceDraft::data();
        $draft['items'] = [$key => $draft['items'][0]];
        $import = InvoiceImport::factory()->create(['status' => 'ready']);
        $this->actingAs($import->user);

        $body = ['draft' => $draft, 'version' => 0];
        $this->putJson($this->path($import), $body)->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson($this->path($import) . '/confirm', $body)
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame(0, $import->refresh()->version);
        $this->assertNull($import->refresh()->draft);
        $this->assertDatabaseCount('maintenance_records', 0);
        $this->assertDatabaseCount('maintenance_invoice_items', 0);
        $this->assertDatabaseCount('workshops', 0);
        $this->assertDatabaseCount('user_activities', 0);
    }

    public function testUploadIsPrivateOwnedAndActivityContainsNoFileData(): void
    {
        Storage::fake('invoices');
        $owner = User::factory()->create();
        $bike = Motorcycle::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/motorcycles/{$bike->id}/invoice-imports",
            ['file' => UploadedFile::fake()->image('bill.jpg')]
        )->assertCreated()->assertJsonPath(
            'data.status',
            'uploaded'
        )->assertJsonMissingPath(
            'data.path'
        )->assertJsonMissingPath(
            'data.user_id'
        );

        $import = InvoiceImport::query()->sole();
        Storage::disk('invoices')->assertExists($import->path);
        $this->assertSame('private', Storage::disk('invoices')->getVisibility($import->path));
        $this->assertSame($owner->id, $import->user_id);
        $document = $import->evidenceDocument;
        $this->assertNotNull($document);
        $this->assertSame($owner->id, $document->user_id);
        $this->assertSame($owner->id, $document->uploaded_by_id);
        $this->assertSame($import->path, $document->path);
        $this->assertSame($import->sha256, $document->sha256);
        $this->assertDatabaseHas(
            'user_activities',
            ['event' => 'invoice.uploaded', 'subject_id' => $response->json('data.id')]
        );
        $this->getJson($this->path($import) . '/download')->assertOk()->assertHeader(
            'X-Content-Type-Options',
            'nosniff'
        );
        $this->getJson("/api/v1/motorcycles/{$bike->id}/invoice-imports")->assertOk()->assertJsonCount(1, 'data');
    }

    public function testAccountDocumentQuotaIncludesEarlierUploadsAcrossMotorcyclesAndRejectsDirectCalls(): void
    {
        Storage::fake('invoices');
        config(['garage.invoice_storage.max_documents' => 1]);
        $owner = User::factory()->create();
        $firstBike = Motorcycle::factory()->for($owner)->create();
        $otherBike = Motorcycle::factory()->for($owner)->create();
        $first = app(UploadInvoice::class)($owner, $firstBike, UploadedFile::fake()->image('first.jpg'));
        try {
            app(UploadInvoice::class)($owner, $otherBike, UploadedFile::fake()->image('second.jpg'));
            $this->fail('The account quota must apply to direct callers.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Your account document storage limit has been reached.'],
                $exception->errors()['file']
            );
        }
        $this->assertDatabaseCount('invoice_imports', 1);
        $this->assertDatabaseCount('evidence_documents', 1);
        $this->assertCount(1, Storage::disk('invoices')->allFiles());
        Storage::disk('invoices')->assertExists($first->path);
        $foreign = Motorcycle::factory()->create();
        app(UploadInvoice::class)($foreign->user, $foreign, UploadedFile::fake()->image('foreign.jpg'));
        $this->assertDatabaseCount('invoice_imports', 2);
    }

    public function testByteQuotaAllowsExactCapacityAndRejectsFurtherUploadsBeforeStoring(): void
    {
        Storage::fake('invoices');
        $bike = Motorcycle::factory()->create();
        $file = UploadedFile::fake()->image('first.jpg');
        config(['garage.invoice_storage.max_bytes' => $file->getSize()]);
        app(UploadInvoice::class)($bike->user, $bike, $file);
        $this->actingAs($bike->user)->postJson(
            "/api/v1/motorcycles/{$bike->id}/invoice-imports",
            ['file' => UploadedFile::fake()->image('second.jpg')]
        )->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertCount(1, Storage::disk('invoices')->allFiles());
        $this->assertDatabaseCount('evidence_documents', 1);
    }

    public function testFailedStorageWriteDoesNotConsumeAccountQuota(): void
    {
        Storage::fake('invoices');
        config(['garage.invoice_storage.max_documents' => 1]);
        $bike = Motorcycle::factory()->create();
        $source = UploadedFile::fake()->image('failed.jpg');
        $file = $this->partialMock(UploadedFile::class);
        $file->__construct($source->getPathname(), 'failed.jpg', null, null, true);
        $file->shouldReceive('storeAs')->once()->andReturnUsing(function (string $directory, string $name): false {
            Storage::disk('invoices')->put($directory . '/' . $name, 'partial storage bytes');

            return false;
        });
        try {
            app(UploadInvoice::class)($bike->user, $bike, $file);
            $this->fail('Storage rejection must fail the upload.');
        } catch (GarageInvoiceStorageException $exception) {
            $this->assertSame('Invoice storage failed.', $exception->getMessage());
        }
        $this->assertDatabaseCount('invoice_imports', 0);
        $this->assertDatabaseCount('evidence_documents', 0);
        Storage::disk('invoices')->assertDirectoryEmpty('/');
        app(UploadInvoice::class)($bike->user, $bike, UploadedFile::fake()->image('retry.jpg'));
        $this->assertDatabaseCount('evidence_documents', 1);
    }

    public function testFailedUploadRollsBackQuotaAllocationAndRemovesStoredBytes(): void
    {
        Storage::fake('invoices');
        config(['garage.invoice_storage.max_documents' => 1]);
        $bike = Motorcycle::factory()->create();
        $activity = \Mockery::mock(RecordUserActivity::class, [$this->app, $this->app->make(Request::class)])
            ->makePartial();
        $activity->shouldReceive('__invoke')->once()->andThrow(new \RuntimeException('Storage rollback probe'));
        $this->app->instance(RecordUserActivity::class, $activity);
        try {
            app(UploadInvoice::class)($bike->user, $bike, UploadedFile::fake()->image('failed.jpg'));
            $this->fail('Activity failure must abort the upload.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Storage rollback probe', $exception->getMessage());
        }
        $this->assertDatabaseCount('invoice_imports', 0);
        $this->assertDatabaseCount('evidence_documents', 0);
        Storage::disk('invoices')->assertDirectoryEmpty('/');
        $this->app->forgetInstance(RecordUserActivity::class);
        app(UploadInvoice::class)($bike->user, $bike, UploadedFile::fake()->image('retry.jpg'));
        $this->assertDatabaseCount('evidence_documents', 1);
    }

    public function testUploadedDocumentIsAttachedWhenTheInvoiceIsConfirmed(): void
    {
        Storage::fake('invoices');
        $bike = Motorcycle::factory()->create();
        $import = app(UploadInvoice::class)($bike->user, $bike, UploadedFile::fake()->image('bill.jpg'));
        $import->update(['status' => 'ready']);
        app(ConfirmInvoiceDraft::class)($bike->user, $import, InvoiceDraft::data(), 0);
        $record = $import->invoice()->firstOrFail()->maintenanceRecord;
        $this->assertSame($import->evidence_document_id, $record->evidenceDocuments()->sole()->id);
        $this->assertSame($bike->user_id, $record->recorded_by_id);
        $this->assertSame(HistoryOrigin::Invoice, $record->origin);
        $this->assertSame(MaintenancePerformer::Workshop, $record->performer);
        $this->assertDatabaseCount('evidence_documents', 1);
        $this->assertDatabaseCount('maintenance_evidence', 1);
    }

    public function testGuestsForeignOwnersMismatchedMotorcyclesAndUnverifiedWritesAreRejected(): void
    {
        Storage::fake('invoices');
        $import = InvoiceImport::factory()->create();
        $this->getJson($this->path($import))->assertUnauthorized();
        $owner = User::factory()->create();
        $bike = Motorcycle::factory()->for($owner)->create();
        $this->actingAs($owner)->getJson($this->path($import))->assertNotFound();
        $this->getJson($this->path($import) . '/download')->assertNotFound();
        $this->postJson($this->path($import) . '/extract')->assertNotFound();
        $this->getJson("/api/v1/motorcycles/{$bike->id}/invoice-imports/{$import->id}")->assertNotFound();
        $this->putJson($this->path($import), ['draft' => InvoiceDraft::data(), 'version' => 0])->assertNotFound();
        $this->postJson(
            $this->path($import) . '/confirm',
            ['draft' => InvoiceDraft::data(), 'version' => 0]
        )->assertNotFound();
        $owner->forceFill(['email_verified_at' => null])->save();
        $this->postJson(
            "/api/v1/motorcycles/{$bike->id}/invoice-imports",
            ['file' => UploadedFile::fake()->image('bill.jpg')]
        )->assertForbidden();
        $this->assertDatabaseCount('user_activities', 0);
    }

    public function testInvalidMimeAndOversizeUploadsCreateNoRowsOrFiles(): void
    {
        Storage::fake('invoices');
        $owner = User::factory()->create();
        $bike = Motorcycle::factory()->for($owner)->create();
        $path = "/api/v1/motorcycles/{$bike->id}/invoice-imports";
        $this->actingAs($owner)->postJson(
            $path,
            ['file' => UploadedFile::fake()->create('pretend.pdf', 1, 'text/html')]
        )->assertUnprocessable()->assertJsonValidationErrors(
            'file'
        );
        $this->postJson(
            $path,
            ['file' => UploadedFile::fake()->image('large.jpg')->size(10241)]
        )->assertUnprocessable()->assertJsonValidationErrors(
            'file'
        );
        $this->assertDatabaseCount('invoice_imports', 0);
        Storage::disk('invoices')->assertDirectoryEmpty('/');
    }

    public function testNativeInvoicePermissionsAreEnforced(): void
    {
        $import = InvoiceImport::factory()->create();
        $owner = $import->user;
        Sanctum::actingAs($owner, ['account:read']);
        $this->getJson($this->path($import))->assertForbidden();
        Sanctum::actingAs($owner, ['garage:read']);
        $this->getJson($this->path($import))->assertOk();
        $this->postJson($this->path($import) . '/extract')->assertForbidden();
        Sanctum::actingAs($owner, ['garage:write']);
        $this->postJson($this->path($import) . '/extract')->assertForbidden();
        $this->assertDatabaseCount('user_activities', 0);
    }

    public function testConfirmationIsIdempotentAndPersistsWorkshopPositionsTaxDurationAndExactCosts(): void
    {
        $import = InvoiceImport::factory()->create(['status' => 'ready', 'draft' => InvoiceDraft::data()]);
        $this->actingAs($import->user);
        $body = ['draft' => InvoiceDraft::data(), 'version' => 0];

        $response = $this->postJson($this->path($import) . '/confirm', $body)->assertOk()->assertJsonPath(
            'data.status',
            'confirmed'
        );
        $this->postJson($this->path($import) . '/confirm', $body)->assertOk()->assertJsonPath(
            'data.maintenance_record_id',
            $response->json('data.maintenance_record_id')
        );

        $this->assertDatabaseCount('maintenance_records', 1);
        $this->assertDatabaseCount('workshops', 1);
        $invoice = MaintenanceInvoice::query()->sole();
        $this->assertSame('75.20', $invoice->total_amount);
        $this->assertSame('5.20', $invoice->tax_amount);
        $this->assertSame(30, $invoice->labor_minutes);
        $this->assertSame('75.20', $invoice->items()->sole()->total_amount);
        $cost = $invoice->maintenanceRecord->costItems()->sole();
        $this->assertSame('75.20', $cost->total_amount);
        $this->assertSame($invoice->items()->sole()->id, $cost->invoiceItem()->firstOrFail()->id);
        $this->assertSame($invoice->currency, $cost->currency);
        $this->assertDatabaseCount('maintenance_cost_items', 1);
        $this->assertDatabaseCount('maintenance_actions', 0);
        $this->assertDatabaseHas('motorcycles', ['id' => $import->motorcycle_id, 'odometer_km' => 15000]);
        $this->assertDatabaseHas(
            'maintenance_records',
            ['id' => $response->json('data.maintenance_record_id'), 'cost_amount' => '75.20']
        );
        $this->assertDatabaseHas('user_activities', ['event' => 'invoice.confirmed', 'subject_id' => $import->id]);
    }

    public function testPartialReviewCanBeSavedButStaleAndIncompleteConfirmationsAreRejected(): void
    {
        $draft = InvoiceDraft::data();
        $draft['odometer_km'] = null;
        $draft['currency'] = null;
        $import = InvoiceImport::factory()->create(['status' => 'ready', 'draft' => $draft]);
        $this->actingAs($import->user);
        $this->putJson($this->path($import), ['draft' => $draft, 'version' => 0])->assertOk()->assertJsonPath(
            'data.version',
            1
        );
        $this->postJson(
            $this->path($import) . '/confirm',
            ['draft' => InvoiceDraft::data(), 'version' => 0]
        )->assertConflict()->assertJsonPath('reason', 'draft_changed');
        $this->postJson(
            $this->path($import) . '/confirm',
            ['draft' => $draft, 'version' => 1]
        )->assertUnprocessable()->assertJsonValidationErrors(
            ['odometer_km', 'currency']
        );
        $this->assertDatabaseCount('maintenance_records', 0);
        $this->assertDatabaseCount('workshops', 0);
        $this->assertSame('ready', $import->refresh()->status);
        $ciphertext = DB::table('invoice_imports')->where('id', $import->id)->value('draft');
        $this->assertStringNotContainsString('Local Workshop', $this->stringValue($ciphertext));
    }

    public function testWorkshopsAreUpsertedOnlyWithinTheOwnersAccountAndMissingContactsArePreserved(): void
    {
        $first = InvoiceImport::factory()->create(['status' => 'ready']);
        $this->actingAs($first->user)->postJson(
            $this->path($first) . '/confirm',
            ['draft' => InvoiceDraft::data(), 'version' => 0]
        )->assertOk();
        $second = InvoiceImport::factory()->create(['motorcycle_id' => $first->motorcycle_id, 'status' => 'ready']);
        $draft = InvoiceDraft::data();
        $draft['workshop']['email'] = null;
        $this->postJson($this->path($second) . '/confirm', ['draft' => $draft, 'version' => 0])->assertOk();
        $this->assertDatabaseCount('workshops', 1);
        $this->assertSame('shop@example.com', Workshop::query()->sole()->email);
        $foreign = InvoiceImport::factory()->create(['status' => 'ready']);
        Sanctum::actingAs($foreign->user, ['garage:write']);
        $this->postJson($this->path($foreign) . '/confirm', ['draft' => $draft, 'version' => 0])->assertOk();
        $this->assertDatabaseCount('workshops', 2);
    }

    public function testLargeValidReviewDraftsSurviveEncryptionAndStorage(): void
    {
        $draft = InvoiceDraft::data();
        $item = $draft['items'][0];
        $item['description'] = str_repeat('A', 1000);
        $draft['items'] = array_fill(0, 100, $item);
        $import = InvoiceImport::factory()->create(['status' => 'ready']);
        $this->actingAs($import->user)->putJson(
            $this->path($import),
            ['draft' => $draft, 'version' => 0]
        )->assertOk()->assertJsonCount(
            100,
            'data.draft.items'
        );
        $this->assertSame($draft, $import->refresh()->draft);
    }

    public function testInvoiceRoutesDisableDebugCaptureAndConfirmationRequiresAWorkshopNameForContactData(): void
    {
        $import = InvoiceImport::factory()->create(['status' => 'ready']);
        $draft = InvoiceDraft::data();
        $draft['workshop']['name'] = null;
        Telescope::startRecording(false);
        $this->actingAs($import->user)->postJson(
            $this->path($import) . '/confirm',
            ['draft' => $draft, 'version' => 0]
        )->assertUnprocessable()->assertJsonValidationErrors(
            'workshop.name'
        );
        $this->assertFalse(Telescope::isRecording());
        $this->assertDatabaseCount('workshops', 0);
        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function testConfirmationRollsBackEveryDomainWriteWhenActivityPersistenceFails(): void
    {
        $import = InvoiceImport::factory()->create(['status' => 'ready']);
        $mileage = $import->motorcycle->odometer_km;
        $activity = \Mockery::mock(
            RecordUserActivity::class,
            [$this->app, $this->app->make(Request::class)]
        )->makePartial();
        $activity->shouldReceive('__invoke')->passthru()->byDefault();
        $activity->shouldReceive('__invoke')->withArgs(
            fn ($actor, $owner, $event) => $event === ActivityEvent::InvoiceConfirmed
        )->andThrow(
            new \RuntimeException('Simulated activity persistence failure')
        );
        $this->app->instance(RecordUserActivity::class, $activity);
        try {
            app(ConfirmInvoiceDraft::class)($import->user, $import, InvoiceDraft::data(), 0);
            $this->fail('Confirmation should fail.');
        } catch (\RuntimeException $failure) {
            $this->assertSame('Simulated activity persistence failure', $failure->getMessage());
        }
        foreach (
            [
                'workshops',
                'maintenance_records',
                'maintenance_invoices',
                'maintenance_invoice_items',
                'maintenance_cost_items',
                'maintenance_evidence',
                'user_activities',
            ] as $table
        ) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame('ready', $import->refresh()->status);
        $this->assertSame($mileage, $import->motorcycle->refresh()->odometer_km);
    }
}
