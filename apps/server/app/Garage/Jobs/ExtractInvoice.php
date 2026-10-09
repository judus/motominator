<?php

namespace App\Garage\Jobs;

use App\Activity\Actions\RecordUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Ai\Agents\InvoiceExtractor;
use App\Ai\Exceptions\AiInvoiceExtractionException;
use App\Ai\Exceptions\AiOperationException;
use App\Garage\Actions\Invoices\NormalizeExtractedInvoice;
use App\Garage\Actions\Invoices\ValidateInvoiceDraft;
use App\Models\InvoiceImport;
use App\Support\Input;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Telescope\Telescope;
use Throwable;

class ExtractInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 55;

    public bool $failOnTimeout = true;

    public function __construct(public int $importId, public string $attemptId)
    {
    }

    public function handle(
        ValidateInvoiceDraft $validateInvoiceDraft,
        NormalizeExtractedInvoice $normalizeExtractedInvoice,
        RecordUserActivity $recordUserActivity
    ): void {
        try {
            if (class_exists(Telescope::class)) {
                Telescope::withoutRecording(
                    fn () => $this->extract($validateInvoiceDraft, $normalizeExtractedInvoice, $recordUserActivity)
                );
            } else {
                $this->extract($validateInvoiceDraft, $normalizeExtractedInvoice, $recordUserActivity);
            }
        } catch (Throwable $exception) {
            $failure = AiOperationException::unexpected($exception);
            try {
                $this->markFailed($failure);
            } catch (Throwable $cleanupFailure) {
                Log::error('invoice.failure_state_unavailable', [
                    'import_id' => $this->importId,
                    'attempt_id' => $this->attemptId,
                ] + AiOperationException::diagnostics($cleanupFailure));
            }
            throw $failure;
        } finally {
            Ai::flushState();
        }
    }

    private function extract(
        ValidateInvoiceDraft $validateInvoiceDraft,
        NormalizeExtractedInvoice $normalizeExtractedInvoice,
        RecordUserActivity $recordUserActivity
    ): void {
        $import = DB::transaction(function (): ?InvoiceImport {
            $import = InvoiceImport::query()->lockForUpdate()->find($this->importId);
            if (! $import || $import->attempt_id !== $this->attemptId || $import->status !== 'queued') {
                return null;
            }
            $import->update(['status' => 'processing']);

            return $import;
        });
        if (! $import) {
            return;
        }
        try {
            $owner = $import->user;
            if (! $owner->hasVerifiedEmail() || $import->motorcycle->user_id !== $owner->id) {
                throw AiInvoiceExtractionException::ownerUnavailable();
            }
            $credential = $owner->aiCredential()->first();
            $configuration = $credential ? config('ai_byok.providers.' . $credential->provider) : null;
            if (! $credential || ! is_array($configuration)) {
                throw AiInvoiceExtractionException::settingsUnavailable();
            }
            $response = (new InvoiceExtractor())->prompt(
                'Extract the attached invoice into the review schema.',
                attachments: [$import->mime === 'application/pdf' ? Document::fromStorage(
                    $import->path,
                    'invoices'
                )->as(
                    'invoice.pdf'
                ) : Image::fromStorage(
                    $import->path,
                    'invoices'
                )
                ],
                provider: [Ai::build(
                    [
                        'driver' => $credential->provider,
                        'url' => $configuration['url'],
                        'key' => $credential->api_key,
                        'store' => false,
                        'models' => ['text' => ['default' => $credential->model]]
                    ]
                )
                ],
                timeout: 45,
            );
            if (! $response instanceof StructuredAgentResponse) {
                throw AiInvoiceExtractionException::structuredResponseMissing();
            }
            $draft = $validateInvoiceDraft($normalizeExtractedInvoice(Input::object(
                $response->toArray()
            )));
            $saved = DB::transaction(function () use ($draft, $credential, $owner, $recordUserActivity): bool {
                $locked = InvoiceImport::query()->lockForUpdate()->find($this->importId);
                if (! $locked || $locked->attempt_id !== $this->attemptId || $locked->status !== 'processing') {
                    return false;
                }
                $locked->update(
                    [
                        'draft' => $draft,
                        'status' => 'ready',
                        'provider' => $credential->provider,
                        'model' => $credential->model,
                        'version' => $locked->version + 1
                    ]
                );
                Context::scope(
                    fn () => $recordUserActivity(
                        $owner,
                        $owner->id,
                        ActivityEvent::InvoiceDraftReady,
                        $locked->id,
                        force: true
                    ),
                    ['activity_source' => 'ai']
                );
                return true;
            });
            if ($saved) {
                Log::info('invoice.extraction_ready', [
                    'import_id' => $this->importId,
                    'attempt_id' => $this->attemptId,
                ]);
            }
        } catch (ValidationException $exception) {
            $this->markFailed($exception, array_keys($exception->errors()));
        } catch (AiInvoiceExtractionException | AiException | ConnectionException | RequestException $exception) {
            $this->markFailed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception);
    }

    /** @param list<string> $validationFields */
    private function markFailed(?Throwable $exception, array $validationFields = []): void
    {
        $changed = InvoiceImport::query()->whereKey($this->importId)->where('attempt_id', $this->attemptId)->whereIn(
            'status',
            ['queued', 'processing']
        )->update(
            [
                'status' => 'failed',
                'error' => $validationFields !== []
                ? 'The AI response did not match the invoice format. Retry extraction; if it '
                . 'persists, try another model.'
                : ($exception instanceof AiOperationException
                    ? 'An internal error prevented extraction. Please retry; if it persists, contact support.'
                    : 'Extraction failed. Check your BYOK key, model document support and provider availability, '
                        . 'then retry.'),
            ]
        );
        if ($changed === 0) {
            return;
        }
        Log::warning(
            'invoice.extraction_failed',
            [
                'import_id' => $this->importId,
                'attempt_id' => $this->attemptId,
                'failure_type' => $exception ? $exception::class : 'worker_failure',
                'validation_fields' => $validationFields,
            ] + ($exception ? AiOperationException::diagnostics($exception) : []),
        );
    }
}
