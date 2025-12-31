<?php

declare(strict_types=1);

use WillVincent\EmailVerifier\External\BouncerEmailVerifier;
use WillVincent\EmailVerifier\External\EmailableEmailVerifier;
use WillVincent\EmailVerifier\External\ExternalEmailVerifierManager;
use WillVincent\EmailVerifier\External\KickboxEmailVerifier;
use WillVincent\EmailVerifier\External\NeverBounceEmailVerifier;
use WillVincent\EmailVerifier\External\NullExternalEmailVerifier;
use WillVincent\EmailVerifier\External\VerifiedEmailVerifier;
use WillVincent\EmailVerifier\External\ZeroBounceEmailVerifier;

it('returns null driver when config is empty', function (): void {
    config()->set('email-verifier.external.driver', '');

    $manager = resolve(ExternalEmailVerifierManager::class);
    $driver = $manager->driver();

    expect($driver)->toBeInstanceOf(NullExternalEmailVerifier::class);
});

it('returns null driver when config is null', function (): void {
    config()->set('email-verifier.external.driver');

    $manager = resolve(ExternalEmailVerifierManager::class);
    $driver = $manager->driver();

    expect($driver)->toBeInstanceOf(NullExternalEmailVerifier::class);
});

it('creates bouncer driver', function (): void {
    config()->set('email-verifier.external.driver', 'bouncer');

    $manager = resolve(ExternalEmailVerifierManager::class);
    $driver = $manager->driver('bouncer');

    expect($driver)->toBeInstanceOf(BouncerEmailVerifier::class);
});

it('creates emailable driver', function (): void {
    config()->set('email-verifier.external.driver', 'emailable');

    $manager = resolve(ExternalEmailVerifierManager::class);
    $driver = $manager->driver('emailable');

    expect($driver)->toBeInstanceOf(EmailableEmailVerifier::class);
});

it('creates kickbox driver', function (): void {
    config()->set('email-verifier.external.driver', 'kickbox');

    $manager = resolve(ExternalEmailVerifierManager::class);
    $driver = $manager->driver('kickbox');

    expect($driver)->toBeInstanceOf(KickboxEmailVerifier::class);
});

it('creates neverbounce driver', function (): void {
    config()->set('email-verifier.external.driver', 'neverbounce');

    $manager = resolve(ExternalEmailVerifierManager::class);
    $driver = $manager->driver('neverbounce');

    expect($driver)->toBeInstanceOf(NeverBounceEmailVerifier::class);
});

it('creates verifiedemail driver', function (): void {
    config()->set('email-verifier.external.driver', 'verifiedemail');

    $manager = resolve(ExternalEmailVerifierManager::class);
    $driver = $manager->driver('verifiedemail');

    expect($driver)->toBeInstanceOf(VerifiedEmailVerifier::class);
});

it('creates zerobounce driver', function (): void {
    config()->set('email-verifier.external.driver', 'zerobounce');

    $manager = resolve(ExternalEmailVerifierManager::class);
    $driver = $manager->driver('zerobounce');

    expect($driver)->toBeInstanceOf(ZeroBounceEmailVerifier::class);
});

it('uses configured driver as default', function (): void {
    config()->set('email-verifier.external.driver', 'kickbox');

    $manager = resolve(ExternalEmailVerifierManager::class);
    $driver = $manager->driver();

    expect($driver)->toBeInstanceOf(KickboxEmailVerifier::class);
});
