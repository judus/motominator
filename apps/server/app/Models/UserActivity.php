<?php

namespace App\Models;

use App\Activity\Enums\ActivityEvent;
use Database\Factories\UserActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property array<string, array<string, mixed>> $changes
 * @property int $id
 * @property int $user_id
 * @property int|null $actor_id
 * @property ActivityEvent $event
 * @property string $subject_type
 * @property int $subject_id
 * @property string $source
 * @property string|null $trace_id
 * @property Carbon $created_at
 * @property-read User|null $actor
 * @property-read User $user
 * @method static \Database\Factories\UserActivityFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
class UserActivity extends Model
{
    /** @use HasFactory<UserActivityFactory> */
    use HasFactory;
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['event' => ActivityEvent::class, 'changes' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return Builder<UserActivity> */
    public function prunable(): Builder
    {
        return UserActivity::query()->where(
            'created_at',
            '<',
            now()->subDays(max(1, Config::integer('logging.activity_retention_days', 365)))
        );
    }
}
