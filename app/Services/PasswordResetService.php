<?php

namespace App\Services;

use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\Hash;

class PasswordResetService
{
    public const CODE_TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const VERIFIED_TTL_SECONDS = 600;

    public function createAndSend(User $user): PasswordResetCode
    {
        $this->revokeActiveCodes($user);

        $code = PasswordResetCode::generateCode();

        $record = PasswordResetCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        $user->notify(new PasswordResetCodeNotification($code));

        return $record;
    }

    public function verify(User $user, string $code): string
    {
        $active = PasswordResetCode::query()
            ->where('user_id', $user->id)
            ->where('revoked', false)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $active) {
            return 'invalid';
        }

        if ($active->expires_at->isPast()) {
            $active->update(['revoked' => true]);

            return 'expired';
        }

        if ($active->attempts >= self::MAX_ATTEMPTS) {
            $active->update(['revoked' => true]);

            return 'locked';
        }

        if (! Hash::check($code, $active->code_hash)) {
            $active->increment('attempts');

            if ($active->refresh()->attempts >= self::MAX_ATTEMPTS) {
                $active->update(['revoked' => true]);

                return 'locked';
            }

            return 'invalid';
        }

        $active->update(['used_at' => now()]);

        return 'valid';
    }

    public function revokeActiveCodes(User $user): void
    {
        PasswordResetCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('revoked', false)
            ->update(['revoked' => true]);
    }
}