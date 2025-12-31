<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Tests\Fakes;

use WillVincent\EmailVerifier\Contracts\DnsResolver;

final class FakeDnsResolver implements DnsResolver
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $mxByDomain = [];

    /**
     * @param  array<int, array<string, mixed>>  $mx
     */
    public function setMx(string $domain, array $mx): void
    {
        $this->mxByDomain[mb_strtolower($domain)] = $mx;
    }

    public function getMxRecords(string $domain): array
    {
        return $this->mxByDomain[mb_strtolower($domain)] ?? [];
    }
}
