<?php

declare(strict_types=1);

namespace App\Support\Email\Dns;

interface DnsResolver
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMxRecords(string $domain): array;
}
