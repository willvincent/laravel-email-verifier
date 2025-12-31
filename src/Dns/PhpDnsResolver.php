<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Dns;

use WillVincent\EmailVerifier\Contracts\DnsResolver;

final class PhpDnsResolver implements DnsResolver
{
    public function getMxRecords(string $domain): array
    {
        // Prefer dns_get_record for portability and extra detail.
        $records = @dns_get_record($domain, DNS_MX);

        if (is_array($records) && $records !== []) {
            return $records;
        }

        // Fallback: getmxrr (some environments differ)
        $hosts = [];
        $weights = [];
        $ok = @getmxrr($domain, $hosts, $weights);

        if ($ok && count($hosts) > 0) {
            $out = [];
            foreach ($hosts as $i => $host) {
                $out[] = [
                    'target' => $host,
                    'pri' => isset($weights[$i]) ? (int) $weights[$i] : null,
                ];
            }

            return $out;
        }

        return [];
    }
}
