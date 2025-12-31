<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Rules;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use WillVincent\EmailVerifier\Contracts\Rule;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Results\VerificationContext;

final readonly class RoleBasedLocalRule implements Rule
{
    public function __construct(
        private ConfigRepository $config,
    ) {}

    public function apply(VerificationContext $ctx, EmailVerificationResult $result): void
    {
        /** @var array<int|string, mixed> $rawLocals */
        $rawLocals = $this->config->get('email-verifier.role_based_locals', []);
        $locals = array_map(static function (mixed $v): string {
            /** @var string $str */
            $str = $v;

            return mb_strtolower($str);
        }, $rawLocals);

        if (in_array(mb_strtolower($ctx->local), $locals, true)) {
            $result->score -= 15;
            $result->addReason('role_based_local_part');
        }
    }
}
