<?php

declare(strict_types=1);

use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Results\VerificationContext;
use WillVincent\EmailVerifier\Rules\DomainSanityRule;
use WillVincent\EmailVerifier\Rules\MxRule;
use WillVincent\EmailVerifier\Tests\Fakes\FakeDnsResolver;

// DomainSanityRule tests
it('DomainSanityRule rejects domain without dot', function (): void {
    $rule = new DomainSanityRule();
    $ctx = new VerificationContext('test@localhost', 'test@localhost', 'test', 'localhost');
    $result = new EmailVerificationResult(true, 100, 'test@localhost');

    $rule->apply($ctx, $result);

    expect($result->accepted)->toBeFalse()
        ->and($result->reasons)->toContain('invalid_domain');
});

it('DomainSanityRule rejects empty domain', function (): void {
    $rule = new DomainSanityRule();
    $ctx = new VerificationContext('test@', 'test@', 'test', '');
    $result = new EmailVerificationResult(true, 100, 'test@');

    $rule->apply($ctx, $result);

    expect($result->accepted)->toBeFalse()
        ->and($result->reasons)->toContain('invalid_domain');
});

it('DomainSanityRule rejects domain starting with dot', function (): void {
    $rule = new DomainSanityRule();
    $ctx = new VerificationContext('test@.example.com', 'test@.example.com', 'test', '.example.com');
    $result = new EmailVerificationResult(true, 100, 'test@.example.com');

    $rule->apply($ctx, $result);

    expect($result->accepted)->toBeFalse()
        ->and($result->reasons)->toContain('invalid_domain');
});

it('DomainSanityRule rejects domain ending with dot', function (): void {
    $rule = new DomainSanityRule();
    $ctx = new VerificationContext('test@example.com.', 'test@example.com.', 'test', 'example.com.');
    $result = new EmailVerificationResult(true, 100, 'test@example.com.');

    $rule->apply($ctx, $result);

    expect($result->accepted)->toBeFalse()
        ->and($result->reasons)->toContain('invalid_domain');
});

it('DomainSanityRule accepts valid domain', function (): void {
    $rule = new DomainSanityRule();
    $ctx = new VerificationContext('test@example.com', 'test@example.com', 'test', 'example.com');
    $result = new EmailVerificationResult(true, 100, 'test@example.com');

    $rule->apply($ctx, $result);

    expect($result->accepted)->toBeTrue()
        ->and($result->reasons)->not->toContain('invalid_domain');
});

// MxRule tests
it('MxRule rejects in strict mode when no MX records', function (): void {
    config()->set('email-verifier.mx_strict', true);

    $dns = new FakeDnsResolver();
    $rule = new MxRule(config(), $dns);

    $ctx = new VerificationContext('test@example.com', 'test@example.com', 'test', 'example.com');
    $result = new EmailVerificationResult(true, 100, 'test@example.com');

    $rule->apply($ctx, $result);

    expect($result->accepted)->toBeFalse()
        ->and($result->reasons)->toContain('no_mx_records');
});

it('MxRule penalizes in non-strict mode when no MX records', function (): void {
    config()->set('email-verifier.mx_strict', false);

    $dns = new FakeDnsResolver();
    $rule = new MxRule(config(), $dns);

    $ctx = new VerificationContext('test@example.com', 'test@example.com', 'test', 'example.com');
    $result = new EmailVerificationResult(true, 100, 'test@example.com');

    $rule->apply($ctx, $result);

    expect($result->accepted)->toBeTrue()
        ->and($result->score)->toBe(75)
        ->and($result->reasons)->toContain('no_mx_records_soft');
});

it('MxRule accepts when MX records exist', function (): void {
    config()->set('email-verifier.mx_strict', true);

    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    $rule = new MxRule(config(), $dns);

    $ctx = new VerificationContext('test@example.com', 'test@example.com', 'test', 'example.com');
    $result = new EmailVerificationResult(true, 100, 'test@example.com');

    $rule->apply($ctx, $result);

    expect($result->accepted)->toBeTrue()
        ->and($result->score)->toBe(100)
        ->and($result->meta)->toHaveKey('mx_count')
        ->and($result->meta['mx_count'])->toBe(1);
});
