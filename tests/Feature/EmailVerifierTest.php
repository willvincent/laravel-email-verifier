<?php

declare(strict_types=1);

use WillVincent\EmailVerifier\Contracts\DisposableDomainChecker;
use WillVincent\EmailVerifier\Contracts\DnsResolver;
use WillVincent\EmailVerifier\Contracts\ExternalEmailVerifier;
use WillVincent\EmailVerifier\EmailVerifier;
use WillVincent\EmailVerifier\External\ExternalEmailVerifierManager;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Tests\Fakes\FakeDisposableChecker;
use WillVincent\EmailVerifier\Tests\Fakes\FakeDnsResolver;

it('rejects empty email', function (): void {
    $verifier = resolve(EmailVerifier::class);

    $r = $verifier->verify('   ');
    expect($r->accepted)->toBeFalse()
        ->and($r->reasons)->toContain('empty_email');
});

it('rejects invalid format', function (): void {
    $verifier = resolve(EmailVerifier::class);

    $r = $verifier->verify('nope');
    expect($r->accepted)->toBeFalse()
        ->and($r->reasons)->toContain('invalid_format');
});

it('passes valid domain through sanity check', function (): void {
    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    app()->instance(DnsResolver::class, $dns);

    $disposable = new FakeDisposableChecker();
    app()->instance(DisposableDomainChecker::class, $disposable);

    $verifier = resolve(EmailVerifier::class);

    $r = $verifier->verify('test@example.com');
    expect($r->accepted)->toBeTrue()
        ->and($r->reasons)->not->toContain('invalid_domain');
});

it('penalizes role-based local part', function (): void {
    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    $disp = new FakeDisposableChecker();

    app()->bind(DnsResolver::class, fn (): FakeDnsResolver => $dns);
    app()->bind(DisposableDomainChecker::class, fn (): FakeDisposableChecker => $disp);

    $verifier = resolve(EmailVerifier::class);

    $r = $verifier->verify('info@example.com');
    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(85)
        ->and($r->reasons)->toContain('role_based_local_part');
});

it('penalizes plus addressing when enabled', function (): void {
    config()->set('email-verifier.check_plus_addressing', true);

    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    $disp = new FakeDisposableChecker();

    app()->bind(DnsResolver::class, fn (): FakeDnsResolver => $dns);
    app()->bind(DisposableDomainChecker::class, fn (): FakeDisposableChecker => $disp);

    $verifier = resolve(EmailVerifier::class);

    $r = $verifier->verify('person+tag@example.com');
    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(95)
        ->and($r->reasons)->toContain('plus_addressing');
});

it('hard-fails disposable domains', function (): void {
    $dns = new FakeDnsResolver();
    $dns->setMx('mailinator.com', [['target' => 'mx.mailinator.com', 'pri' => 10]]);

    $disp = new FakeDisposableChecker();
    $disp->markDisposable('mailinator.com');

    app()->bind(DnsResolver::class, fn (): FakeDnsResolver => $dns);
    app()->bind(DisposableDomainChecker::class, fn (): FakeDisposableChecker => $disp);

    $verifier = resolve(EmailVerifier::class);

    $r = $verifier->verify('test@mailinator.com');
    expect($r->accepted)->toBeFalse()
        ->and($r->reasons)->toContain('disposable_domain');
});

it('hard-fails when MX is missing in strict mode', function (): void {
    config()->set('email-verifier.mx.strict', true);

    $dns = new FakeDnsResolver(); // no MX set -> none returned
    $disp = new FakeDisposableChecker();

    app()->bind(DnsResolver::class, fn (): FakeDnsResolver => $dns);
    app()->bind(DisposableDomainChecker::class, fn (): FakeDisposableChecker => $disp);

    $verifier = resolve(EmailVerifier::class);

    $r = $verifier->verify('person@example.com');
    expect($r->accepted)->toBeFalse()
        ->and($r->reasons)->toContain('no_mx_records');
});

it('calls external driver only after local checks pass', function (): void {
    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    $disp = new FakeDisposableChecker();

    app()->bind(DnsResolver::class, fn (): FakeDnsResolver => $dns);
    app()->bind(DisposableDomainChecker::class, fn (): FakeDisposableChecker => $disp);

    // Register a fake external driver
    /** @var ExternalEmailVerifierManager $mgr */
    $mgr = resolve(ExternalEmailVerifierManager::class);

    $mgr->extend('fake', fn (): ExternalEmailVerifier => new class implements ExternalEmailVerifier
    {
        public function verify(string $email): EmailVerificationResult
        {
            return new EmailVerificationResult(
                accepted: false,
                score: 0,
                normalizedEmail: $email,
                reasons: ['external_rejected'],
                meta: ['driver' => 'fake'],
            );
        }
    });

    config()->set('email-verifier.external.driver', 'fake');

    $verifier = resolve(EmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->reasons)->toContain('external_rejected')
        ->and($r->meta)->toHaveKey('external');
});
it('skips normalization when disabled', function (): void {
    config()->set('email-verifier.normalize.enabled', false);

    $dns = new FakeDnsResolver();
    $dns->setMx('EXAMPLE.COM', [['target' => 'mx1.example.com', 'pri' => 10]]);

    $disp = new FakeDisposableChecker();

    app()->bind(DnsResolver::class, fn (): FakeDnsResolver => $dns);
    app()->bind(DisposableDomainChecker::class, fn (): FakeDisposableChecker => $disp);

    $verifier = resolve(EmailVerifier::class);

    $r = $verifier->verify('Test@EXAMPLE.COM');

    // Without normalization, domain stays uppercase, but still lowercased for processing
    expect($r->normalizedEmail)->toBe('Test@EXAMPLE.COM');
});

it('lowercases local part when configured', function (): void {
    config()->set('email-verifier.normalize.enabled', true);
    config()->set('email-verifier.normalize.lowercase_local', true);

    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    $disp = new FakeDisposableChecker();

    app()->bind(DnsResolver::class, fn (): FakeDnsResolver => $dns);
    app()->bind(DisposableDomainChecker::class, fn (): FakeDisposableChecker => $disp);

    $verifier = resolve(EmailVerifier::class);

    $r = $verifier->verify('Test@Example.COM');

    expect($r->normalizedEmail)->toBe('test@example.com');
});

it('rejects when score falls below threshold before external check', function (): void {
    config()->set('email-verifier.min_score', 90);

    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    $disp = new FakeDisposableChecker();

    app()->bind(DnsResolver::class, fn (): FakeDnsResolver => $dns);
    app()->bind(DisposableDomainChecker::class, fn (): FakeDisposableChecker => $disp);

    $verifier = resolve(EmailVerifier::class);

    // Role-based reduces score to 85, which is below 90 threshold
    $r = $verifier->verify('info@example.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(85)
        ->and($r->reasons)->toContain('score_below_threshold');
});

it('rejects when external provider lowers score below threshold', function (): void {
    config()->set('email-verifier.min_score', 90);

    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    $disp = new FakeDisposableChecker();

    app()->bind(DnsResolver::class, fn (): FakeDnsResolver => $dns);
    app()->bind(DisposableDomainChecker::class, fn (): FakeDisposableChecker => $disp);

    // Register a fake external driver that returns score 85
    /** @var ExternalEmailVerifierManager $mgr */
    $mgr = resolve(ExternalEmailVerifierManager::class);

    $mgr->extend('fake', fn (): ExternalEmailVerifier => new class implements ExternalEmailVerifier
    {
        public function verify(string $email): EmailVerificationResult
        {
            return new EmailVerificationResult(
                accepted: true,
                score: 85,
                normalizedEmail: $email,
                reasons: ['external_risky'],
                meta: ['driver' => 'fake'],
            );
        }
    });

    config()->set('email-verifier.external.driver', 'fake');

    $verifier = resolve(EmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(85)
        ->and($r->reasons)->toContain('score_below_threshold');
});
