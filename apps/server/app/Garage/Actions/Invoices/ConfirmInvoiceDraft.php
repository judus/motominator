<?php

namespace App\Garage\Actions\Invoices;

use App\Activity\Actions\RecordUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Garage\Actions\AttachMaintenanceEvidence;
use App\Garage\Actions\SaveMaintenanceRecord;
use App\Garage\Enums\HistoryOrigin;
use App\Garage\Enums\MaintenancePerformer;
use App\Garage\Exceptions\GarageInvoiceException;
use App\Models\InvoiceImport;
use App\Models\MaintenanceInvoice;
use App\Models\User;
use App\Models\Workshop;
use App\Support\Input;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ConfirmInvoiceDraft
{
    public function __construct(
        private readonly ValidateInvoiceDraft $validateInvoiceDraft,
        private readonly RecordUserActivity $recordUserActivity,
        private readonly SaveMaintenanceRecord $saveMaintenanceRecord,
        private readonly AttachMaintenanceEvidence $attachMaintenanceEvidence,
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
            if ($locked->status === 'confirmed') {
                return $locked;
            }
            if (! ($locked->status === 'ready' && $locked->version === $version)) {
                throw GarageInvoiceException::draftChanged();
            }
            $data = ($this->validateInvoiceDraft)($input, confirm: true);
            $bike = $locked->motorcycle;
            if (! ($bike->user_id === $owner->id)) {
                throw new ModelNotFoundException();
            }
            $workshopData = Input::object($data['workshop'], 'workshop');
            $name = $workshopData['name'] ?? null;
            $workshop = null;
            if (is_string($name) && $name !== '') {
                $identity = hash(
                    'sha256',
                    mb_strtolower(
                        preg_replace(
                            '/\s+/u',
                            ' ',
                            trim($name) . '|' . trim(
                                Input::string($workshopData['address'] ?? '', 'workshop.address')
                            )
                        ) ?? ''
                    )
                );
                $workshop = Workshop::query()->updateOrCreate(
                    ['user_id' => $owner->id, 'identity' => $identity],
                    array_filter($workshopData, fn ($value): bool => $value !== null && $value !== '')
                );
                ($this->recordUserActivity)(
                    $owner,
                    $owner->id,
                    ActivityEvent::WorkshopSaved,
                    $workshop->id,
                    force: true
                );
            }
            $record = ($this->saveMaintenanceRecord)($owner, $bike, [
                'performed_on' => $data['performed_on'],
                'odometer_km' => $data['odometer_km'],
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'cost_amount' => $data['total_amount'] ?? null,
                'currency' => isset($data['total_amount']) ? ($data['currency'] ?? null) : null,
            ]);
            $invoice = MaintenanceInvoice::query()->create([
                'invoice_import_id' => $locked->id,
                'maintenance_record_id' => $record->id,
                'workshop_id' => $workshop?->id,
                'invoice_number' => $data['invoice_number'] ?? null,
                'subtotal_amount' => $data['subtotal_amount'] ?? null,
                'tax_amount' => $data['tax_amount'] ?? null,
                'total_amount' => $data['total_amount'] ?? null,
                'currency' => $data['currency'] ?? null,
                'labor_minutes' => $data['labor_minutes'] ?? null,
            ]);
            foreach (Input::objects($data['items'], 'items') as $position => $item) {
                $source = $invoice->items()->create($item + ['position' => $position]);
                $cost = $record->costItems()->make($item + ['position' => $position, 'currency' => $invoice->currency]);
                $cost->invoiceItem()->associate($source);
                $cost->save();
            }
            $record->forceFill([
                'origin' => HistoryOrigin::Invoice,
                'performer' => $workshop ? MaintenancePerformer::Workshop : MaintenancePerformer::Unknown,
                'workshop_id' => $workshop?->id,
                'labor_minutes' => $invoice->labor_minutes,
            ])->save();
            if ($locked->evidenceDocument) {
                ($this->attachMaintenanceEvidence)($owner, $record, $locked->evidenceDocument);
            }
            $locked->update(['draft' => $data, 'status' => 'confirmed', 'version' => $locked->version + 1]);
            ($this->recordUserActivity)($owner, $owner->id, ActivityEvent::InvoiceConfirmed, $locked->id, force: true);

            return $locked;
        });
    }
}
