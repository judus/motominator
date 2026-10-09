<?php

namespace App\Models;

use Database\Factories\AiCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $model
 * @property string $api_key
 * @property string $key_hint
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @method static \Database\Factories\AiCredentialFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(['provider', 'model', 'api_key', 'key_hint'])]
#[Hidden(['api_key'])]
class AiCredential extends Model
{
    /** @use HasFactory<AiCredentialFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['api_key' => 'encrypted'];
    }
}
