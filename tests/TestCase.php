<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use WillVincent\EmailVerifier\EmailVerifierServiceProvider;
use WillVincent\EmailVerifier\Rules\DisposableDomainRule;
use WillVincent\EmailVerifier\Rules\DomainSanityRule;
use WillVincent\EmailVerifier\Rules\FormatRule;
use WillVincent\EmailVerifier\Rules\MxRule;
use WillVincent\EmailVerifier\Rules\PlusAddressingRule;
use WillVincent\EmailVerifier\Rules\RoleBasedLocalRule;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            EmailVerifierServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Default config for tests
        $app['config']->set('email-verifier.min_score', 70);
        $app['config']->set('email-verifier.normalize.enabled', true);
        $app['config']->set('email-verifier.normalize.lowercase_local', false);

        $app['config']->set('email-verifier.mx_strict', true);

        // External off by default
        $app['config']->set('email-verifier.external.driver', null);

        // Rules order – keep consistent in tests
        $app['config']->set('email-verifier.rules', [
            FormatRule::class,
            DomainSanityRule::class,
            RoleBasedLocalRule::class,
            PlusAddressingRule::class,
            DisposableDomainRule::class,
            MxRule::class,
        ]);

        $app['config']->set('email-verifier.role_based_locals', ['info', 'admin', 'support']);
    }
}
