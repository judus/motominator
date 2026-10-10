<?php

namespace App\Accounts\Data;

final readonly class NativeAuthIntent
{
    public function __construct(
        public string $provider,
        public string $challenge,
        public string $deviceName,
        public int $expiresAt,
        public ?int $userId,
        public ?string $securityFingerprint = null,
    ) {
    }

    public static function fromCache(mixed $value): ?self
    {
        if (
            ! is_array($value)
            || ! is_string($value['provider'] ?? null)
            || ! is_string($value['challenge'] ?? null)
            || ! is_string($value['device_name'] ?? null)
            || ! is_int($value['expires_at'] ?? null)
            || ! array_key_exists('user_id', $value)
            || ! array_key_exists('security_fingerprint', $value)
            || ($value['security_fingerprint'] !== null && ! is_string($value['security_fingerprint']))
            || ($value['user_id'] !== null && ! is_int($value['user_id']))
        ) {
            return null;
        }

        return new self(
            $value['provider'],
            $value['challenge'],
            $value['device_name'],
            $value['expires_at'],
            $value['user_id'],
            $value['security_fingerprint'],
        );
    }

    /**
     * @return array{
     *     provider: string, challenge: string, device_name: string, expires_at: int,
     *     user_id: int|null, security_fingerprint: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'challenge' => $this->challenge,
            'device_name' => $this->deviceName,
            'expires_at' => $this->expiresAt,
            'user_id' => $this->userId,
            'security_fingerprint' => $this->securityFingerprint,
        ];
    }
}
