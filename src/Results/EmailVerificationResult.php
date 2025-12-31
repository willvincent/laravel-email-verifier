<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Results;

final class EmailVerificationResult
{
    /**
     * @param  array<int, string>  $reasons
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $accepted,
        public int $score,
        public ?string $normalizedEmail,
        public array $reasons = [],
        public array $meta = [],
    ) {}

    public function reject(string $reason, int $score = 0): void
    {
        $this->accepted = false;
        $this->score = $score;
        $this->reasons[] = $reason;
    }

    public function addReason(string $reason): void
    {
        $this->reasons[] = $reason;
    }

    public function addMeta(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }
}
