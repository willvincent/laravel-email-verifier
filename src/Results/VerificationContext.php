<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Results;

final class VerificationContext
{
    public function __construct(
        public string $originalEmail,
        public string $email,
        public string $local,
        public string $domain,
    ) {}
}
