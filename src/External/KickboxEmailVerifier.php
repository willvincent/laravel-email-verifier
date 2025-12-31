<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\External;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;
use WillVincent\EmailVerifier\Contracts\ExternalEmailVerifier;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;

final readonly class KickboxEmailVerifier implements ExternalEmailVerifier
{
    public function __construct(
        private HttpFactory $http,
        private ConfigRepository $config,
    ) {}

    public function verify(string $email): EmailVerificationResult
    {
        /** @var string $apiKey */
        $apiKey = $this->config->get('email-verifier.external.kickbox.api_key', '');
        /** @var string $endpoint */
        $endpoint = $this->config->get('email-verifier.external.kickbox.endpoint', '');
        /** @var int $timeout */
        $timeout = $this->config->get('email-verifier.external.timeout_seconds', 5);

        if ($apiKey === '' || $endpoint === '') {
            return new EmailVerificationResult(
                accepted: true,
                score: 100,
                normalizedEmail: $email,
                reasons: [],
                meta: ['provider' => 'kickbox', 'configured' => false],
            );
        }

        try {
            $resp = $this->http
                ->timeout($timeout)
                ->retry(1, 250)
                ->get($endpoint, [
                    'email' => $email,
                    'apikey' => $apiKey,
                ]);

            if (! $resp->ok()) {
                return new EmailVerificationResult(
                    accepted: true,
                    score: 90,
                    normalizedEmail: $email,
                    reasons: ['external_provider_unavailable'],
                    meta: [
                        'provider' => 'kickbox',
                        'configured' => true,
                        'http_status' => $resp->status(),
                    ],
                );
            }

            /** @var array<string, mixed> $data */
            $data = $resp->json() ?? [];

            // Kickbox indicates whether the request succeeded with "success"
            if (! (bool) ($data['success'] ?? false)) {
                return new EmailVerificationResult(
                    accepted: true,
                    score: 90,
                    normalizedEmail: $email,
                    reasons: ['external_provider_unavailable'],
                    meta: [
                        'provider' => 'kickbox',
                        'configured' => true,
                        'kickbox_success' => false,
                        'raw' => $data,
                    ],
                );
            }

            /** @var string $resultRaw */
            $resultRaw = $data['result'] ?? '';
            $result = mb_strtolower($resultRaw);
            /** @var string $reasonRaw */
            $reasonRaw = $data['reason'] ?? '';
            $reason = mb_strtolower($reasonRaw);

            $meta = [
                'provider' => 'kickbox',
                'configured' => true,
                'status' => $result,
                'reason' => $reason,
                'raw' => $data,
            ];

            // Map Kickbox results into our thin statuses
            return match ($result) {
                'deliverable' => new EmailVerificationResult(true, 100, $email, [], $meta),

                // "undeliverable" is a hard reject; include their reason for debugging/tuning
                'undeliverable' => new EmailVerificationResult(
                    accepted: false,
                    score: 0,
                    normalizedEmail: $email,
                    reasons: ['external_rejected:'.($reason !== '' ? $reason : 'undeliverable')],
                    meta: $meta,
                ),

                // "risky" is a soft accept but penalized
                'risky' => new EmailVerificationResult(
                    accepted: true,
                    score: 75,
                    normalizedEmail: $email,
                    reasons: ['external_risky'],
                    meta: $meta,
                ),

                'unknown' => new EmailVerificationResult(
                    accepted: true,
                    score: 80,
                    normalizedEmail: $email,
                    reasons: ['external_unknown'],
                    meta: $meta,
                ),

                default => new EmailVerificationResult(
                    accepted: true,
                    score: 80,
                    normalizedEmail: $email,
                    reasons: ['external_unrecognized_status'],
                    meta: $meta,
                ),
            };
        } catch (Throwable $throwable) {
            return new EmailVerificationResult(
                accepted: true,
                score: 90,
                normalizedEmail: $email,
                reasons: ['external_exception'],
                meta: [
                    'provider' => 'kickbox',
                    'configured' => true,
                    'error' => $throwable->getMessage(),
                ],
            );
        }
    }
}
