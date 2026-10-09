<?php

namespace App\Garage\Actions;

use App\Activity\Actions\RecordUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Models\EvidenceDocument;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AttachMaintenanceEvidence
{
    public function __construct(
        private readonly RecordUserActivity $recordUserActivity,
    ) {
    }

    public function __invoke(User $actor, MaintenanceRecord $record, EvidenceDocument $document): void
    {
        DB::transaction(function () use ($actor, $record, $document): void {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $locked = MaintenanceRecord::query()->lockForUpdate()->findOrFail($record->id);
            Gate::forUser($actor)->authorize('update', $locked);
            $evidence = EvidenceDocument::query()->lockForUpdate()->findOrFail($document->id);
            if (! ($evidence->user_id === $locked->motorcycle->user_id)) {
                throw new ModelNotFoundException();
            }
            if ($locked->evidenceDocuments()->whereKey($evidence->id)->exists()) {
                return;
            }
            $locked->evidenceDocuments()->attach($evidence->id, ['attached_by_id' => $actor->id]);
            ($this->recordUserActivity)(
                $actor,
                $locked->motorcycle->user_id,
                ActivityEvent::EvidenceAttached,
                $locked->id,
                force: true
            );
        });
    }
}
