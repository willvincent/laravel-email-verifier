<?php

declare(strict_types=1);

use WillVincent\EmailVerifier\External\NullExternalEmailVerifier;

it('always returns accepted with score 100', function (): void {
    $verifier = new NullExternalEmailVerifier();

    $result = $verifier->verify('test@example.com');

    expect($result->accepted)->toBeTrue()
        ->and($result->score)->toBe(100)
        ->and($result->normalizedEmail)->toBe('test@example.com')
        ->and($result->reasons)->toBe([])
        ->and($result->meta)->toBe(['external' => 'disabled']);
});

it('works with any email address', function (): void {
    $verifier = new NullExternalEmailVerifier();

    $result1 = $verifier->verify('invalid@invalid.invalid');
    $result2 = $verifier->verify('');
    $result3 = $verifier->verify('not-an-email');

    expect($result1->accepted)->toBeTrue()
        ->and($result1->score)->toBe(100)
        ->and($result2->accepted)->toBeTrue()
        ->and($result2->score)->toBe(100)
        ->and($result3->accepted)->toBeTrue()
        ->and($result3->score)->toBe(100);
});
