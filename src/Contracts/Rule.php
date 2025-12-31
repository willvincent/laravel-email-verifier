<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Contracts;

use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Results\VerificationContext;

interface Rule
{
    public function apply(VerificationContext $ctx, EmailVerificationResult $result): void;
}
