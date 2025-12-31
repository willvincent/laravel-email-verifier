<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use WillVincent\EmailVerifier\Console\FetchDisposableDomainsCommand;
use WillVincent\EmailVerifier\Contracts\DisposableDomainChecker;
use WillVincent\EmailVerifier\Contracts\DnsResolver;
use WillVincent\EmailVerifier\Contracts\EmailVerifierContract;
use WillVincent\EmailVerifier\Disposable\FileBackedDisposableDomainChecker;
use WillVincent\EmailVerifier\Dns\PhpDnsResolver;
use WillVincent\EmailVerifier\External\ExternalEmailVerifierManager;
use WillVincent\EmailVerifier\Validation\VerifiedEmail;

final class EmailVerifierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/email-verifier.php', 'email-verifier');

        $this->app->bind(DnsResolver::class, PhpDnsResolver::class);
        $this->app->bind(DisposableDomainChecker::class, FileBackedDisposableDomainChecker::class);

        $this->app->singleton(ExternalEmailVerifierManager::class, fn (Application $app): ExternalEmailVerifierManager => new ExternalEmailVerifierManager($app));

        $this->app->singleton(EmailVerifier::class);
        $this->app->singleton(EmailVerifierContract::class, EmailVerifier::class);

        $this->commands([
            FetchDisposableDomainsCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/email-verifier.php' => config_path('email-verifier.php'),
        ], 'email-verifier-config');

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'email-verifier');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/email-verifier'),
        ], 'email-verifier-lang');

        Validator::extend('verified_email', function (string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool {
            $minScore = isset($parameters[0]) ? (int) $parameters[0] : null;
            $allowExternal = ! isset($parameters[1]) || $parameters[1] !== 'no_external';

            $rule = new VerifiedEmail($minScore, $allowExternal);

            $failed = false;

            // @phpstan-ignore-next-line argument.type
            $rule->validate($attribute, $value, function (string $message, ?string $key = null) use (&$failed): string|array|null {
                $failed = true;

                return __($message, $key !== null ? [$key => ''] : []);
            });

            return ! $failed;
        });

        Validator::replacer('verified_email',
            // Always use our package translation if present

            fn (string $message, string $attribute): string|array => __('email-verifier::validation.verified_email', ['attribute' => $attribute]));
    }
}
