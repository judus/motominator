<?php

namespace App\Models;

use App\Accounts\Exceptions\AccountsAuthenticationException;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $is_admin
 * @property-read AiCredential|null $aiCredential
 * @property-read Collection<int, EvidenceDocument> $evidenceDocuments
 * @property-read Collection<int, Motorcycle> $motorcycles
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read Collection<int, SocialIdentity> $socialIdentities
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, MustVerifyEmail
{
    use HasApiTokens;
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /** @return HasMany<SocialIdentity, $this> */
    public function socialIdentities(): HasMany
    {
        return $this->hasMany(SocialIdentity::class);
    }



    /** @return HasMany<Motorcycle, $this> */
    public function motorcycles(): HasMany
    {
        return $this->hasMany(Motorcycle::class);
    }

    /** @return HasOne<AiCredential, $this> */
    public function aiCredential(): HasOne
    {
        return $this->hasOne(AiCredential::class);
    }

    public function securityFingerprint(bool $includeRecoveryCodes = true): string
    {
        return hash('sha256', json_encode([
            $this->password,
            $this->email,
            $this->two_factor_secret,
            $includeRecoveryCodes ? $this->two_factor_recovery_codes : null,
            $this->two_factor_confirmed_at?->toISOString(),
        ], JSON_THROW_ON_ERROR));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->is_admin;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        if (! $this->hasEnabledTwoFactorAuthentication() || $this->two_factor_secret === null) {
            return null;
        }
        $secret = Fortify::currentEncrypter()->decrypt($this->two_factor_secret);
        if (! is_string($secret)) {
            throw AccountsAuthenticationException::invalidSecret();
        }

        return $secret;
    }

    public function saveAppAuthenticationSecret(#[\SensitiveParameter] ?string $secret): void
    {
        $this->forceFill([
            'two_factor_secret' => $secret === null ? null : Fortify::currentEncrypter()->encrypt(
                $secret
            ),
            'two_factor_confirmed_at' => $secret === null ? null : now(),
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<EvidenceDocument, $this> */
    public function evidenceDocuments(): HasMany
    {
        return $this->hasMany(EvidenceDocument::class);
    }
}
