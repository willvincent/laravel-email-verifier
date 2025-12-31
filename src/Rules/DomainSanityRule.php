<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Rules;

use WillVincent\EmailVerifier\Contracts\Rule;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Results\VerificationContext;

final readonly class DomainSanityRule implements Rule
{
    public function apply(VerificationContext $ctx, EmailVerificationResult $result): void
    {
        if ($ctx->domain === '' || ! str_contains($ctx->domain, '.')) {
            $result->reject('invalid_domain');

            return;
        }

        if (str_starts_with($ctx->domain, '.') || str_ends_with($ctx->domain, '.')) {
            $result->reject('invalid_domain');

            return;
        }
    }
}
