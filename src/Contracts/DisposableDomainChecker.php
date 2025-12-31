<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Contracts;

interface DisposableDomainChecker
{
    public function isDisposable(string $domain): bool;
}
