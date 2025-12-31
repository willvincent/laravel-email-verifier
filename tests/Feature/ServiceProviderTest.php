<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use WillVincent\EmailVerifier\Contracts\DisposableDomainChecker;
use WillVincent\EmailVerifier\Contracts\DnsResolver;
use WillVincent\EmailVerifier\Tests\Fakes\FakeDisposableChecker;
use WillVincent\EmailVerifier\Tests\Fakes\FakeDnsResolver;

it('registers verified_email validator extension', function (): void {
    $v = Validator::make(['email' => 'invalid'], [
        'email' => ['verified_email'],
    ]);

    expect($v)->toBeInstanceOf(Illuminate\Validation\Validator::class);
});

it('verified_email validator fails for invalid email', function (): void {
    $dns = new FakeDnsResolver();
    app()->instance(DnsResolver::class, $dns);

    $disposable = new FakeDisposableChecker();
    app()->instance(DisposableDomainChecker::class, $disposable);

    $v = Validator::make(['email' => 'notanemail'], [
        'email' => ['verified_email'],
    ]);

    expect($v->fails())->toBeTrue();
});

it('verified_email validator passes for valid email', function (): void {
    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    app()->instance(DnsResolver::class, $dns);

    $disposable = new FakeDisposableChecker();
    app()->instance(DisposableDomainChecker::class, $disposable);

    $v = Validator::make(['email' => 'test@example.com'], [
        'email' => ['verified_email'],
    ]);

    expect($v->passes())->toBeTrue();
});

it('verified_email validator supports min_score parameter', function (): void {
    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    app()->instance(DnsResolver::class, $dns);

    $disposable = new FakeDisposableChecker();
    app()->instance(DisposableDomainChecker::class, $disposable);

    // Role-based email (info@) gets score 85
    $v = Validator::make(['email' => 'info@example.com'], [
        'email' => ['verified_email:90'],
    ]);

    expect($v->fails())->toBeTrue();
});

it('verified_email validator supports no_external parameter', function (): void {
    config()->set('email-verifier.external.driver', 'kickbox');

    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    app()->instance(DnsResolver::class, $dns);

    $disposable = new FakeDisposableChecker();
    app()->instance(DisposableDomainChecker::class, $disposable);

    $v = Validator::make(['email' => 'test@example.com'], [
        'email' => ['verified_email:70,no_external'],
    ]);

    expect($v->passes())->toBeTrue();
});

it('verified_email validator has custom error message', function (): void {
    $dns = new FakeDnsResolver();
    app()->instance(DnsResolver::class, $dns);

    $disposable = new FakeDisposableChecker();
    app()->instance(DisposableDomainChecker::class, $disposable);

    $v = Validator::make(['email' => 'invalid'], [
        'email' => ['verified_email'],
    ]);

    $v->validate();
})->throws(ValidationException::class);
