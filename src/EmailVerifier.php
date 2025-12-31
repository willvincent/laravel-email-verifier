<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use WillVincent\EmailVerifier\Contracts\EmailVerifierContract;
use WillVincent\EmailVerifier\Contracts\ExternalEmailVerifier;
use WillVincent\EmailVerifier\Contracts\Rule;
use WillVincent\EmailVerifier\External\ExternalEmailVerifierManager;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Results\VerificationContext;

final readonly class EmailVerifier implements EmailVerifierContract
{
    public function __construct(
        private Container $container,
        private ConfigRepository $config,
        private ExternalEmailVerifierManager $externalManager,
    ) {}

    public function verify(string $email): EmailVerificationResult
    {
        $original = mb_trim($email);

        if ($original === '') {
            return new EmailVerificationResult(false, 0, null, ['empty_email']);
        }

        $normalized = $this->shouldNormalize()
            ? $this->normalize($original)
            : $original;

        $result = new EmailVerificationResult(true, 100, $normalized);

        [$local, $domain] = $this->splitEmail($normalized);

        $ctx = new VerificationContext(
            originalEmail: $original,
            email: $normalized,
            local: $local,
            domain: mb_strtolower($domain),
        );

        /** @var array<int, class-string<Rule>> $rules */
        $rules = (array) $this->config->get('email-verifier.rules', []);

        foreach ($rules as $ruleClass) {
            /** @var Rule $rule */
            $rule = $this->container->make($ruleClass);
            $rule->apply($ctx, $result);

            if (! $result->accepted) {
                return $this->finalize($result);
            }
        }

        /** @var int $minScore */
        $minScore = $this->config->get('email-verifier.min_score', 70);

        // App behavior: if score below threshold, reject BEFORE external
        if ($result->score < $minScore) {
            $result->accepted = false;
            $result->addReason('score_below_threshold');

            return $this->finalize($result);
        }

        // External (driver-based): only run if a driver was explicitly configured
        /** @var string $driver */
        $driver = $this->config->get('email-verifier.external.driver', '');
        if ($driver !== '') {
            /** @var ExternalEmailVerifier $externalVerifier */
            $externalVerifier = $this->externalManager->driver($driver);
            $ext = $externalVerifier->verify($ctx->email);

            $result->score = min($result->score, $ext->score);
            $result->reasons = array_values(array_unique(array_merge($result->reasons, $ext->reasons)));
            $result->meta = array_merge($result->meta, ['external' => $ext->meta]);

            if (! $ext->accepted) {
                $result->accepted = false;

                return $this->finalize($result);
            }

            if ($result->score < $minScore) {
                $result->accepted = false;
                $result->addReason('score_below_threshold');
            }
        }

        return $this->finalize($result);
    }

    private function finalize(EmailVerificationResult $result): EmailVerificationResult
    {
        $result->reasons = array_values(array_unique($result->reasons));

        return $result;
    }

    private function shouldNormalize(): bool
    {
        return (bool) $this->config->get('email-verifier.normalize.enabled', true);
    }

    private function normalize(string $email): string
    {
        $email = mb_trim($email);
        $parts = explode('@', $email);

        if (count($parts) !== 2) {
            return $email;
        }

        $local = mb_trim($parts[0]);
        $domain = mb_strtolower(mb_trim($parts[1]));

        if ((bool) $this->config->get('email-verifier.normalize.lowercase_local', false)) {
            $local = mb_strtolower($local);
        }

        return $local.'@'.$domain;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitEmail(string $email): array
    {
        $pos = mb_strrpos($email, '@');
        if ($pos === false) {
            return [$email, ''];
        }

        return [mb_substr($email, 0, $pos), mb_substr($email, $pos + 1)];
    }
}
