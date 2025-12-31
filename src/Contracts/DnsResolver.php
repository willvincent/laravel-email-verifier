<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Contracts;

interface DnsResolver
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMxRecords(string $domain): array;
}
