<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Rules;

use WillVincent\EmailVerifier\Contracts\DisposableDomainChecker;
use WillVincent\EmailVerifier\Contracts\Rule;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Results\VerificationContext;

final readonly class DisposableDomainRule implements Rule
{
    public function __construct(
        private DisposableDomainChecker $disposable,
    ) {}

    public function apply(VerificationContext $ctx, EmailVerificationResult $result): void
    {
        if ($this->disposable->isDisposable($ctx->domain)) {
            $result->reject('disposable_domain');
        }
    }
}
