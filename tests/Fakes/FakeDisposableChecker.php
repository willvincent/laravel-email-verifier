<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Tests\Fakes;

use WillVincent\EmailVerifier\Contracts\DisposableDomainChecker;

final class FakeDisposableChecker implements DisposableDomainChecker
{
    /** @var array<string, bool> */
    private array $disposable = [];

    public function markDisposable(string $domain): void
    {
        $this->disposable[mb_strtolower($domain)] = true;
    }

    public function isDisposable(string $domain): bool
    {
        return isset($this->disposable[mb_strtolower($domain)]);
    }
}
