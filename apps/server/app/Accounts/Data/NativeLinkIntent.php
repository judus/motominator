<?php

namespace App\Accounts\Data;

final readonly class NativeLinkIntent
{
    public function __construct(
        public int $userId,
        public string $provider,
        public string $challenge,
        public int $confirmedAt,
        public int $expiresAt,
        public string $securityFingerprint,
        public ?string $providerUserId = null,
    ) {
    }

    public static function fromCache(mixed $value): ?self
    {
        if (
            ! is_array($value) || ! is_int($value['user_id'] ?? null)
            || ! is_string($value['provider'] ?? null) || ! is_string($value['challenge'] ?? null)
            || ! is_int($value['confirmed_at'] ?? null) || ! is_int($value['expires_at'] ?? null)
            || ! is_string($value['security_fingerprint'] ?? null)
            || ! array_key_exists('provider_user_id', $value)
            || ($value['provider_user_id'] !== null && ! is_string($value['provider_user_id']))
        ) {
            return null;
        }

        return new self(
            $value['user_id'],
            $value['provider'],
            $value['challenge'],
            $value['confirmed_at'],
            $value['expires_at'],
            $value['security_fingerprint'],
            $value['provider_user_id'],
        );
    }

    /**
     * @return array{
     *     user_id: int, provider: string, challenge: string,
     *     confirmed_at: int, expires_at: int, security_fingerprint: string, provider_user_id: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'provider' => $this->provider,
            'challenge' => $this->challenge,
            'confirmed_at' => $this->confirmedAt,
            'expires_at' => $this->expiresAt,
            'security_fingerprint' => $this->securityFingerprint,
            'provider_user_id' => $this->providerUserId,
        ];
    }
}
