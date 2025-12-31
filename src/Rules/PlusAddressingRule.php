<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Rules;

use WillVincent\EmailVerifier\Contracts\Rule;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Results\VerificationContext;

final readonly class PlusAddressingRule implements Rule
{
    public function apply(VerificationContext $ctx, EmailVerificationResult $result): void
    {
        // RFC allows + in local-part, but many spam signups use it
        if (str_contains($ctx->local, '+')) {
            $result->score -= 5;
            $result->addReason('plus_addressing');
        }
    }
}
