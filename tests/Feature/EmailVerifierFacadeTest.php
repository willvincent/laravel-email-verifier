<?php

declare(strict_types=1);

use WillVincent\EmailVerifier\Contracts\DisposableDomainChecker;
use WillVincent\EmailVerifier\Contracts\DnsResolver;
use WillVincent\EmailVerifier\Facades\EmailVerifier;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Tests\Fakes\FakeDisposableChecker;
use WillVincent\EmailVerifier\Tests\Fakes\FakeDnsResolver;

it('facade resolves to EmailVerifier instance', function (): void {
    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    app()->instance(DnsResolver::class, $dns);

    $disposable = new FakeDisposableChecker();
    app()->instance(DisposableDomainChecker::class, $disposable);

    $result = EmailVerifier::verify('test@example.com');

    expect($result)->toBeInstanceOf(EmailVerificationResult::class)
        ->and($result->accepted)->toBeTrue()
        ->and($result->normalizedEmail)->toBe('test@example.com');
});
