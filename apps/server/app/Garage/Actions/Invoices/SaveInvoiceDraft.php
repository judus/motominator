<?php

namespace App\Garage\Actions\Invoices;

use App\Activity\Actions\RecordUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Garage\Exceptions\GarageInvoiceException;
use App\Models\InvoiceImport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class SaveInvoiceDraft
{
    public function __construct(
        private readonly ValidateInvoiceDraft $validateInvoiceDraft,
        private readonly RecordUserActivity $recordUserActivity,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function __invoke(User $owner, InvoiceImport $import, array $input, int $version): InvoiceImport
    {
        if (! $owner->hasVerifiedEmail()) {
            throw new AuthorizationException();
        }
        if (! ($import->user_id === $owner->id)) {
            throw new ModelNotFoundException();
        }

        return DB::transaction(function () use ($owner, $import, $input, $version): InvoiceImport {
            $owner = User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            if (! $owner->hasVerifiedEmail()) {
                throw new AuthorizationException();
            }
            $locked = InvoiceImport::query()->lockForUpdate()->findOrFail($import->id);
            if (! ($locked->status === 'ready' && $locked->version === $version)) {
                throw GarageInvoiceException::draftChanged();
            }
            $locked->update(['draft' => ($this->validateInvoiceDraft)($input), 'version' => $locked->version + 1]);
            ($this->recordUserActivity)(
                $owner,
                $owner->id,
                ActivityEvent::InvoiceDraftUpdated,
                $locked->id,
                force: true
            );

            return $locked;
        });
    }
}
