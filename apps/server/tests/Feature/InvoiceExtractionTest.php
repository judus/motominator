<?php

namespace Tests\Feature;

use App\Garage\Actions\Invoices\NormalizeExtractedInvoice;
use Illuminate\Validation\ValidationException;
use App\Garage\Actions\Invoices\ValidateInvoiceDraft;
use App\Ai\Agents\InvoiceExtractor;
use App\Ai\Exceptions\AiOperationException;
use App\Activity\Actions\RecordUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Garage\Actions\Invoices\StartInvoiceExtraction;
use App\Garage\Jobs\ExtractInvoice;
use App\Models\AiCredential;
use App\Models\InvoiceImport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Exceptions\AiException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Fixtures\InvoiceDraft;
use Tests\TestCase;

class InvoiceExtractionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function testNormalizationDoesNotInventMissingInvoiceItems(): void
    {
        $draft = InvoiceDraft::data();
        unset($draft['items']);
        $normalized = app(NormalizeExtractedInvoice::class)($draft);
        $this->assertSame($draft, $normalized);
        $this->expectException(ValidationException::class);
        app(ValidateInvoiceDraft::class)($normalized);
    }

    public function testExtractionRequiresByokAndQueuesOnlyIdsOncePerActiveAttempt(): void
    {
        Queue::fake([ExtractInvoice::class]);
        $import = InvoiceImport::factory()->create();
        $path = "/api/v1/motorcycles/{$import->motorcycle_id}/invoice-imports/{$import->id}/extract";
        $this->actingAs($import->user)->postJson($path)->assertUnprocessable()->assertJsonValidationErrors('ai');
        AiCredential::factory()->create(['user_id' => $import->user_id]);
        $this->postJson($path)->assertOk()->assertJsonPath('data.status', 'queued');
        $this->postJson($path)->assertOk();
        Queue::assertPushed(ExtractInvoice::class, 1);
        Queue::assertPushed(ExtractInvoice::class, function (ExtractInvoice $job) use ($import): bool {
            $this->assertSame($import->id, $job->importId);
            $this->assertStringNotContainsString('test-key-not-a-real-secret', serialize($job));

            return true;
        });
    }

    #[TestWith(['openai', 'application/pdf'])]
    #[TestWith(['anthropic', 'image/jpeg'])]
    #[TestWith(['gemini', 'image/png'])]
    public function testJobUsesCurrentOwnerCredentialsAndStoresOnlyAReviewDraft(string $provider, string $mime): void
    {
        Storage::fake('invoices');
        $import = InvoiceImport::factory()->create(
            ['status' => 'queued', 'mime' => $mime, 'attempt_id' => (string) Str::uuid()]
        );
        AiCredential::factory()->create(['user_id' => $import->user_id, 'provider' => $provider]);
        InvoiceExtractor::fake([InvoiceDraft::data()])->preventStrayPrompts();

        $this->app->call([new ExtractInvoice($import->id, $this->stringValue($import->attempt_id)), 'handle']);
        $this->app->call([new ExtractInvoice($import->id, $this->stringValue($import->attempt_id)), 'handle']);

        $this->assertSame('ready', $import->refresh()->status);
        $this->assertSame('75.20', data_get($import->refresh()->draft, 'total_amount'));
        $this->assertDatabaseCount('maintenance_records', 0);
        $this->assertDatabaseCount('workshops', 0);
        InvoiceExtractor::assertPrompted(function (AgentPrompt $prompt): bool {
            $this->assertSame('test-key-not-a-real-secret', $prompt->provider->providerCredentials()['key']);
            $this->assertSame('test-model', $prompt->model);
            $this->assertSame(45, $prompt->timeout);
            $this->assertCount(1, $prompt->attachments);

            return true;
        });
        $this->assertDatabaseHas(
            'user_activities',
            ['event' => 'invoice.draft_ready', 'source' => 'ai', 'user_id' => $import->user_id]
        );
    }

    public function testRevokedKeysFailSafelyAndObsoleteAttemptsDoNotCallAi(): void
    {
        $import = InvoiceImport::factory()->create(['status' => 'queued', 'attempt_id' => (string) Str::uuid()]);
        InvoiceExtractor::fake()->preventStrayPrompts();
        $this->app->call([new ExtractInvoice($import->id, (string) Str::uuid()), 'handle']);
        $this->app->call([new ExtractInvoice($import->id, $this->stringValue($import->attempt_id)), 'handle']);
        InvoiceExtractor::assertNeverPrompted();
        $this->assertSame('failed', $import->refresh()->status);
        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function testProviderFailuresAreSanitizedWithoutAutomaticPaidRetries(): void
    {
        $import = InvoiceImport::factory()->create(['status' => 'queued', 'attempt_id' => (string) Str::uuid()]);
        AiCredential::factory()->create(['user_id' => $import->user_id]);
        InvoiceExtractor::fake(
            fn () => throw new AiException('secret-key-provider-response')
        )->preventStrayPrompts();
        $job = new ExtractInvoice($import->id, $this->stringValue($import->attempt_id));
        $this->app->call([$job, 'handle']);
        $this->assertSame(1, $job->tries);
        $this->assertSame('failed', $import->refresh()->status);
        $this->assertStringNotContainsString('secret-key', $this->stringValue($import->refresh()->error));
        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function testStalledAttemptCanBeReplacedAndLateFailureCannotOverwriteTheNewAttempt(): void
    {
        Queue::fake([ExtractInvoice::class]);
        $this->freezeTime();
        $oldAttempt = (string) Str::uuid();
        $import = InvoiceImport::factory()->create(
            ['status' => 'processing', 'attempt_id' => $oldAttempt, 'started_at' => now()->subMinutes(4)]
        );
        AiCredential::factory()->create(['user_id' => $import->user_id]);
        $new = app(StartInvoiceExtraction::class)($import->user, $import);
        $log = Log::spy();
        (new ExtractInvoice($import->id, $oldAttempt))->failed(new \RuntimeException());
        $this->assertSame('queued', $import->refresh()->status);
        $this->assertNotSame($oldAttempt, $new->attempt_id);
        Queue::assertPushed(ExtractInvoice::class, 1);
        $log->shouldNotHaveReceived('warning');
    }

    public function testLateSuccessDoesNotClaimANewAttemptIsReady(): void
    {
        $old = (string) Str::uuid();
        $new = (string) Str::uuid();
        $import = InvoiceImport::factory()->create(['status' => 'queued', 'attempt_id' => $old]);
        AiCredential::factory()->create(['user_id' => $import->user_id]);
        InvoiceExtractor::fake(function () use ($import, $new): array {
            $import->refresh()->update(['status' => 'queued', 'attempt_id' => $new]);

            return InvoiceDraft::data();
        })->preventStrayPrompts();
        $log = Log::spy();

        $this->app->call([new ExtractInvoice($import->id, $old), 'handle']);

        $this->assertSame('queued', $import->refresh()->status);
        $this->assertSame($new, $import->refresh()->attempt_id);
        $this->assertNull($import->refresh()->draft);
        $log->shouldNotHaveReceived('info');
        $this->assertDatabaseCount('user_activities', 0);
        InvoiceExtractor::assertPromptedTimes(1);
    }

    public function testFailureCallbackKeepsSafeDiagnosticsAndRecordsTheTransitionOnlyOnce(): void
    {
        $attempt = (string) Str::uuid();
        $import = InvoiceImport::factory()->create(['status' => 'processing', 'attempt_id' => $attempt]);
        $exception = new \Illuminate\Queue\TimeoutExceededException('private-timeout-sentinel');
        $log = Log::spy();
        $job = new ExtractInvoice($import->id, $attempt);

        $job->failed($exception);
        $job->failed($exception);

        $this->assertSame('failed', $import->refresh()->status);
        $log->shouldHaveReceived('warning')->with('invoice.extraction_failed', \Mockery::on(
            fn (array $context): bool => $context['attempt_id'] === $attempt
                && $context['failure_class'] === \Illuminate\Queue\TimeoutExceededException::class
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'private-timeout-sentinel')
        ))->once();
    }

    public function testInternalFailureAfterThePaidResponseFailsTheQueueWithSafeDiagnostics(): void
    {
        $import = InvoiceImport::factory()->create(['status' => 'queued', 'attempt_id' => (string) Str::uuid()]);
        AiCredential::factory()->create(['user_id' => $import->user_id]);
        InvoiceExtractor::fake([InvoiceDraft::data()])->preventStrayPrompts();
        $activity = \Mockery::mock(RecordUserActivity::class)->makePartial();
        $activity->shouldReceive('__invoke')->withArgs(
            fn ($actor, $owner, $event) => $event === ActivityEvent::InvoiceDraftReady
        )->andThrow(new \TypeError('private-internal-sentinel'));
        $this->app->instance(RecordUserActivity::class, $activity);
        $log = Log::spy();

        try {
            Bus::dispatchSync(new ExtractInvoice($import->id, $this->stringValue($import->attempt_id)));
            $this->fail('An internal failure must fail the queue.');
        } catch (AiOperationException $exception) {
            $this->assertSame(\TypeError::class, $exception->context()['failure_class']);
            $this->assertArrayHasKey('failure_file', $exception->context());
            $this->assertArrayHasKey('failure_line', $exception->context());
            $this->assertArrayHasKey('failure_trace', $exception->context());
            $this->assertStringNotContainsString('private-internal-sentinel', (string) $exception);
            $this->assertStringNotContainsString('private-internal-sentinel', serialize($exception));
        }

        $this->assertSame('failed', $import->refresh()->status);
        $this->assertNull($import->refresh()->draft);
        $this->assertStringContainsString('internal error', $this->stringValue($import->refresh()->error));
        $this->assertDatabaseCount('user_activities', 0);
        $log->shouldHaveReceived('error')->with('queue.job_failed', \Mockery::type('array'))->once();
        $log->shouldNotHaveReceived('info', ['queue.job_completed', \Mockery::any()]);
        $log->shouldHaveReceived('warning')->with('invoice.extraction_failed', \Mockery::on(
            fn (array $context): bool => $context['failure_class'] === \TypeError::class
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'private-internal-sentinel')
        ))->once();
        InvoiceExtractor::assertPromptedTimes(1);
    }

    public function testOpenaiTransportSendsPrivatePdfWithOwnerKeyAndDisablesStorage(): void
    {
        Storage::fake('invoices');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'response_invoice_test',
                'model' => 'test-model',
                'status' => 'completed',
                'output' => [
                    [
                        'type' => 'message',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode(
                                    InvoiceDraft::data()
                                )
                            ],
                        ]
                    ],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 100, 'total_tokens' => 110],
            ])
        ]);
        $import = InvoiceImport::factory()->create(
            ['status' => 'queued', 'mime' => 'application/pdf', 'attempt_id' => (string) Str::uuid()]
        );
        $contents = "%PDF-1.4\nfixture document";
        Storage::disk('invoices')->put($import->path, $contents);
        AiCredential::factory()->create(['user_id' => $import->user_id]);
        $this->app->call([new ExtractInvoice($import->id, $this->stringValue($import->attempt_id)), 'handle']);
        $this->assertSame('ready', $import->refresh()->status);
        Http::assertSent(function (Request $request) use ($contents): bool {
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-key-not-a-real-secret'));
            $this->assertSame('test-model', $request['model']);
            $this->assertFalse($request['store']);
            $this->assertStringContainsString(
                base64_encode($contents),
                json_encode($request->data(), JSON_THROW_ON_ERROR)
            );
            $this->assertSame('json_schema', data_get($request->data(), 'text.format.type'));

            return true;
        });
        Http::assertSentCount(1);
    }

    #[TestWith(['8.1%', '8.1'])]
    #[TestWith([' 8,1 % ', '8.1'])]
    #[TestWith(['19%', '19'])]
    public function testExplicitPercentageNotationIsNormalizedBeforeDraftValidation(
        string $rate,
        string $expected
    ): void {
        $import = InvoiceImport::factory()->create(['status' => 'queued', 'attempt_id' => (string) Str::uuid()]);
        AiCredential::factory()->create(['user_id' => $import->user_id]);
        $draft = InvoiceDraft::data();
        $draft['items'][0]['tax_rate'] = $rate;
        InvoiceExtractor::fake([$draft])->preventStrayPrompts();
        $this->app->call([new ExtractInvoice($import->id, $this->stringValue($import->attempt_id)), 'handle']);
        $this->assertSame('ready', $import->refresh()->status);
        $this->assertSame($expected, data_get($import->refresh()->draft, 'items.0.tax_rate'));
        $this->assertSame('75.20', data_get($import->refresh()->draft, 'total_amount'));
        $this->assertDatabaseCount('maintenance_records', 0);
        InvoiceExtractor::assertPromptedTimes(1);
    }

    public function testInvalidRatesFailWithSafeFieldDiagnosticsWithoutGuessing(): void
    {
        $import = InvoiceImport::factory()->create(['status' => 'queued', 'attempt_id' => (string) Str::uuid()]);
        AiCredential::factory()->create(['user_id' => $import->user_id]);
        $draft = InvoiceDraft::data();
        $draft['items'][0]['tax_rate'] = 'private-invalid-tax-value';
        InvoiceExtractor::fake([$draft])->preventStrayPrompts();
        $log = Log::spy();
        $this->app->call([new ExtractInvoice($import->id, $this->stringValue($import->attempt_id)), 'handle']);
        $this->assertSame('failed', $import->refresh()->status);
        $this->assertNull($import->refresh()->draft);
        $this->assertStringContainsString('AI response', $this->stringValue($import->refresh()->error));
        $log->shouldHaveReceived('warning')->with(
            'invoice.extraction_failed',
            \Mockery::on(
                fn (array $context): bool => $context['validation_fields'] === ['items.0.tax_rate'] && ! str_contains(
                    json_encode($context, JSON_THROW_ON_ERROR),
                    'private-invalid'
                )
            )
        );
        InvoiceExtractor::assertPromptedTimes(1);
    }
}
