<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use WillVincent\EmailVerifier\Contracts\EmailVerifierContract;

final readonly class VerifiedEmail implements ValidationRule
{
    public function __construct(
        private ?int $minScore = null,
        private bool $allowExternal = true,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('validation.email'));

            return;
        }

        $email = mb_trim($value);

        if ($email === '') {
            $fail(__('validation.required'));

            return;
        }

        // Optionally disable external calls for validation contexts
        $prevDriver = null;
        if (! $this->allowExternal) {
            $prevDriver = config('email-verifier.external.driver');
            config()->set('email-verifier.external.driver');
        }

        try {
            /** @var EmailVerifierContract $verifier */
            $verifier = resolve(EmailVerifierContract::class);

            $result = $verifier->verify($email);

            /** @var int $defaultMinScore */
            $defaultMinScore = config('email-verifier.min_score', 70);
            $min = $this->minScore ?? $defaultMinScore;

            if (! $result->accepted || $result->score < $min) {
                // Keep message simple; we don’t want to leak signal details in UI
                $fail(__('email-verifier::validation.verified_email'));
            }
        } finally {
            if ($prevDriver !== null) {
                config()->set('email-verifier.external.driver', $prevDriver);
            }
        }
    }
}
