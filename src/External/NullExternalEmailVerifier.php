<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\External;

use WillVincent\EmailVerifier\Contracts\ExternalEmailVerifier;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;

final class NullExternalEmailVerifier implements ExternalEmailVerifier
{
    public function verify(string $email): EmailVerificationResult
    {
        return new EmailVerificationResult(
            accepted: true,
            score: 100,
            normalizedEmail: $email,
            reasons: [],
            meta: ['external' => 'disabled'],
        );
    }
}
