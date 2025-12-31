<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use WillVincent\EmailVerifier\Contracts\DisposableDomainChecker;
use WillVincent\EmailVerifier\Contracts\DnsResolver;
use WillVincent\EmailVerifier\Contracts\EmailVerifierContract;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;
use WillVincent\EmailVerifier\Tests\Fakes\FakeDisposableChecker;
use WillVincent\EmailVerifier\Tests\Fakes\FakeDnsResolver;
use WillVincent\EmailVerifier\Validation\VerifiedEmail;

it('passes when verifier accepts and score >= min', function (): void {
    $mock = Mockery::mock(EmailVerifierContract::class);
    $mock->shouldReceive('verify')->andReturn(
        new EmailVerificationResult(true, 90, 'person@example.com', [], [])
    );
    app()->instance(EmailVerifierContract::class, $mock);

    $v = Validator::make(['email' => 'person@example.com'], [
        'email' => [new VerifiedEmail(minScore: 80, allowExternal: false)],
    ]);

    expect($v->passes())->toBeTrue();
});

it('fails when verifier rejects', function (): void {
    $mock = Mockery::mock(EmailVerifierContract::class);
    $mock->shouldReceive('verify')->andReturn(
        new EmailVerificationResult(false, 0, 'person@example.com', ['no_mx_records'], [])
    );
    app()->instance(EmailVerifierContract::class, $mock);

    $v = Validator::make(['email' => 'person@example.com'], [
        'email' => [new VerifiedEmail(minScore: 70, allowExternal: false)],
    ]);

    expect($v->passes())->toBeFalse()
        ->and($v->errors()->first('email'))->not->toBeEmpty();
});

it('fails when value is not a string', function (): void {
    $v = Validator::make(['email' => 123], [
        'email' => [new VerifiedEmail()],
    ]);

    expect($v->passes())->toBeFalse();
});

it('fails when value is empty string', function (): void {
    $v = Validator::make(['email' => ''], [
        'email' => ['required', new VerifiedEmail()],
    ]);

    expect($v->passes())->toBeFalse();
});

it('fails when score is below custom min_score', function (): void {
    $mock = Mockery::mock(EmailVerifierContract::class);
    $mock->shouldReceive('verify')->andReturn(
        new EmailVerificationResult(true, 75, 'person@example.com', [], [])
    );
    app()->instance(EmailVerifierContract::class, $mock);

    $v = Validator::make(['email' => 'person@example.com'], [
        'email' => [new VerifiedEmail(minScore: 80, allowExternal: false)],
    ]);

    expect($v->passes())->toBeFalse();
});

it('passes when score meets custom min_score', function (): void {
    $mock = Mockery::mock(EmailVerifierContract::class);
    $mock->shouldReceive('verify')->andReturn(
        new EmailVerificationResult(true, 80, 'person@example.com', [], [])
    );
    app()->instance(EmailVerifierContract::class, $mock);

    $v = Validator::make(['email' => 'person@example.com'], [
        'email' => [new VerifiedEmail(minScore: 80, allowExternal: false)],
    ]);

    expect($v->passes())->toBeTrue();
});

it('disables external verification when allowExternal is false', function (): void {
    config()->set('email-verifier.external.driver', 'kickbox');

    $dns = new FakeDnsResolver();
    $dns->setMx('example.com', [['target' => 'mx1.example.com', 'pri' => 10]]);

    app()->instance(DnsResolver::class, $dns);

    $disposable = new FakeDisposableChecker();
    app()->instance(DisposableDomainChecker::class, $disposable);

    $v = Validator::make(['email' => 'test@example.com'], [
        'email' => [new VerifiedEmail(allowExternal: false)],
    ]);

    expect($v->passes())->toBeTrue();

    // Verify config was restored
    expect(config('email-verifier.external.driver'))->toBe('kickbox');
});
