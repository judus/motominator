<?php

namespace App\Activity\Actions;

use App\Activity\Enums\ActivityEvent;
use App\Activity\Exceptions\ActivityRecordingException;
use App\Models\MaintenanceRecord;
use App\Models\Motorcycle;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Sanctum\PersonalAccessToken;

class RecordUserActivity
{
    public function __construct(private readonly Application $application, private readonly Request $request)
    {
    }

    public function motorcycleSaved(User $actor, Motorcycle $saved, ?Motorcycle $previous = null): void
    {
        $this(
            actor: $actor,
            ownerId: $saved->user_id,
            event: $previous ? ActivityEvent::MotorcycleUpdated : ActivityEvent::MotorcycleCreated,
            subjectId: $saved->id,
            before: $previous?->attributesToArray() ?? [],
            after: $saved->attributesToArray(),
            force: $previous === null,
        );
    }

    public function maintenanceSaved(
        User $actor,
        MaintenanceRecord $saved,
        ?MaintenanceRecord $previous = null,
        bool $changed = true,
    ): void {
        $this(
            actor: $actor,
            ownerId: $saved->motorcycle->user_id,
            event: $previous ? ActivityEvent::MaintenanceUpdated : ActivityEvent::MaintenanceCreated,
            subjectId: $saved->id,
            before: $previous?->attributesToArray() ?? [],
            after: $saved->attributesToArray(),
            force: $changed,
        );
    }

    /**
     * Call inside the transaction that persists the corresponding change.
     * Maintenance prose and credentials are deliberately excluded from snapshots.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __invoke(
        User $actor,
        int $ownerId,
        ActivityEvent $event,
        int $subjectId,
        array $before = [],
        array $after = [],
        bool $force = false
    ): void {
        if ($actor->getConnection()->transactionLevel() === 0) {
            throw ActivityRecordingException::transactionRequired();
        }
        $changes = [];
        foreach ($event->fields() as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;
            if ($old !== $new) {
                $changes[$field] = in_array(
                    $field,
                    ['title', 'notes'],
                    true
                ) ? ['changed' => true] : ['before' => $old, 'after' => $new];
            }
        }
        if ($changes === [] && ! $force) {
            return;
        }
        $authenticated = $this->request->user();
        $sourceActor = $authenticated instanceof User && $authenticated->is($actor) ? $authenticated : $actor;

        UserActivity::query()->create([
            'user_id' => $ownerId,
            'actor_id' => $actor->id,
            'event' => $event,
            'subject_type' => $event->subjectType(),
            'subject_id' => $subjectId,
            'source' => Context::get(
                'activity_source',
                $this->application->runningInConsole() && ! $this->request->attributes->has(
                    'trace_id'
                ) ? 'system' : $this->clientSource($sourceActor->currentAccessToken())
            ),
            'trace_id' => Context::get('trace_id'),
            'changes' => $changes,
        ]);
    }

    private function clientSource(mixed $token): string
    {
        return $token instanceof PersonalAccessToken ? 'mobile' : 'web';
    }
}
