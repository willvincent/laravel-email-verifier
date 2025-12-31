<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Rules;

use WillVincent\EmailVerifier\Contracts\Rule;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Results\VerificationContext;

final class FormatRule implements Rule
{
    public function apply(VerificationContext $ctx, EmailVerificationResult $result): void
    {
        // Treat invalid email as hard fail.
        if (! filter_var($ctx->email, FILTER_VALIDATE_EMAIL)) {
            $result->reject('invalid_format');
            $result->normalizedEmail = null;

            return;
        }
    }
}
