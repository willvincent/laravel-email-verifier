<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Contracts;

use WillVincent\EmailVerifier\Results\EmailVerificationResult;

interface EmailVerifierContract
{
    public function verify(string $email): EmailVerificationResult;
}
