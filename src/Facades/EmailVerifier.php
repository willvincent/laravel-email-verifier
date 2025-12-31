<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Facades;

use Illuminate\Support\Facades\Facade;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;

/**
 * @method static EmailVerificationResult verify(string $email)
 */
final class EmailVerifier extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \WillVincent\EmailVerifier\EmailVerifier::class;
    }
}
