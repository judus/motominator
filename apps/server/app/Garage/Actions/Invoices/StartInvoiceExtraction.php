<?php

namespace App\Garage\Actions\Invoices;

use App\Activity\Actions\RecordUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Garage\Exceptions\GarageInvoiceException;
use App\Garage\Jobs\ExtractInvoice;
use App\Models\InvoiceImport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartInvoiceExtraction
{
    public function __construct(
        private readonly RecordUserActivity $recordUserActivity,
    ) {
    }

    public function __invoke(User $owner, InvoiceImport $import): InvoiceImport
    {
        if (! $owner->hasVerifiedEmail()) {
            throw new AuthorizationException();
        }
        if (! ($import->user_id === $owner->id)) {
            throw new ModelNotFoundException();
        }
        return DB::transaction(function () use ($owner, $import): InvoiceImport {
            $owner = User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            if (! $owner->hasVerifiedEmail()) {
                throw new AuthorizationException();
            }
            if (! $owner->aiCredential()->exists()) {
                throw ValidationException::withMessages(
                    ['ai' => 'Save your BYOK settings in Account before extracting an invoice.']
                );
            }
            $locked = InvoiceImport::query()->lockForUpdate()->findOrFail($import->id);
            if (
                in_array($locked->status, ['queued', 'processing'], true) && $locked->started_at?->gt(
                    now()->subMinutes(3)
                )
            ) {
                return $locked;
            }
            if (in_array($locked->status, ['ready', 'confirmed'], true)) {
                throw GarageInvoiceException::draftAlreadyExists();
            }
            $attempt = (string) Str::uuid();
            $locked->update(['status' => 'queued', 'attempt_id' => $attempt, 'started_at' => now(), 'error' => null]);
            ($this->recordUserActivity)(
                $owner,
                $owner->id,
                ActivityEvent::InvoiceExtractionRequested,
                $locked->id,
                force: true
            );
            ExtractInvoice::dispatch($locked->id, $attempt)->afterCommit();

            return $locked;
        });
    }
}
