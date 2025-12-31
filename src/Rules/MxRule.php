<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Rules;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use WillVincent\EmailVerifier\Contracts\DnsResolver;
use WillVincent\EmailVerifier\Contracts\Rule;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Results\VerificationContext;

final readonly class MxRule implements Rule
{
    public function __construct(
        private ConfigRepository $config,
        private DnsResolver $dns,
    ) {}

    public function apply(VerificationContext $ctx, EmailVerificationResult $result): void
    {
        $mx = $this->dns->getMxRecords($ctx->domain);

        $result->addMeta('mx_count', count($mx));

        if ($mx === []) {
            if ((bool) $this->config->get('email-verifier.mx_strict', true)) {
                $result->reject('no_mx_records');

                return;
            }

            // non-strict: penalize
            $result->score -= 25;
            $result->addReason('no_mx_records_soft');
        }
    }
}
