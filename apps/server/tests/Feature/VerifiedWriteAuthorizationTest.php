<?php

namespace Tests\Feature;

use App\Ai\Actions\SaveAiSettings;
use App\Garage\Actions\Invoices\ConfirmInvoiceDraft;
use App\Garage\Actions\Invoices\SaveInvoiceDraft;
use App\Garage\Actions\Invoices\StartInvoiceExtraction;
use App\Garage\Jobs\ExtractInvoice;
use App\Models\AiCredential;
use App\Models\InvoiceImport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Fixtures\InvoiceDraft;
use Tests\TestCase;

class VerifiedWriteAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['settings'])]
    #[TestWith(['confirm'])]
    #[TestWith(['draft'])]
    #[TestWith(['extract'])]
    public function testRevokedVerificationRejectsWritesFromAStaleActor(string $operation): void
    {
        Queue::fake([ExtractInvoice::class]);
        $import = InvoiceImport::factory()->create([
            'status' => $operation === 'extract' ? 'uploaded' : 'ready',
            'draft' => $operation === 'extract' ? null : InvoiceDraft::data(),
        ]);
        $owner = $import->user;
        $credential = AiCredential::factory()->create(['user_id' => $owner->id]);
        $beforeCredential = $credential->refresh()->getRawOriginal();
        $beforeImport = $import->refresh()->getRawOriginal();
        User::query()->findOrFail($owner->id)->forceFill([
            'email' => 'changed@example.test',
            'email_verified_at' => null,
        ])->save();
        $this->assertTrue($owner->hasVerifiedEmail());

        try {
            match ($operation) {
                'settings' => app(SaveAiSettings::class)($owner, [
                    'provider' => 'openai', 'model' => 'changed-model', 'api_key' => 'replacement-test-key',
                ]),
                'confirm' => app(ConfirmInvoiceDraft::class)($owner, $import, InvoiceDraft::data(), 0),
                'draft' => app(SaveInvoiceDraft::class)($owner, $import, InvoiceDraft::data(), 0),
                'extract' => app(StartInvoiceExtraction::class)($owner, $import),
                default => throw new \LogicException('Unknown test operation.'),
            };
            $this->fail('Verification must be checked against the current account row.');
        } catch (AuthorizationException) {
            $this->assertSame($beforeCredential, $credential->refresh()->getRawOriginal());
            $this->assertSame($beforeImport, $import->refresh()->getRawOriginal());
            $this->assertDatabaseCount('user_activities', 0);
            $this->assertDatabaseCount('maintenance_records', 0);
            $this->assertDatabaseCount('maintenance_invoices', 0);
            Queue::assertNotPushed(ExtractInvoice::class);
        }
    }
}
