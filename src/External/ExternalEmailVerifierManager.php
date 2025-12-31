<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\External;

use Illuminate\Support\Manager;
use WillVincent\EmailVerifier\Contracts\ExternalEmailVerifier;

final class ExternalEmailVerifierManager extends Manager
{
    public function getDefaultDriver(): string
    {
        // If user sets null/empty, we'll use "null" driver.
        $driver = $this->config->get('email-verifier.external.driver', '');

        if (! is_string($driver) || $driver === '') {
            return 'null';
        }

        return $driver;
    }

    protected function createBouncerDriver(): ExternalEmailVerifier
    {
        return $this->container->make(BouncerEmailVerifier::class);
    }

    protected function createEmailableDriver(): ExternalEmailVerifier
    {
        return $this->container->make(EmailableEmailVerifier::class);
    }

    protected function createKickboxDriver(): ExternalEmailVerifier
    {
        return $this->container->make(KickboxEmailVerifier::class);
    }

    protected function createNeverbounceDriver(): ExternalEmailVerifier
    {
        return $this->container->make(NeverBounceEmailVerifier::class);
    }

    protected function createNullDriver(): ExternalEmailVerifier
    {
        return new NullExternalEmailVerifier();
    }

    protected function createVerifiedemailDriver(): ExternalEmailVerifier
    {
        return $this->container->make(VerifiedEmailVerifier::class);
    }

    protected function createZerobounceDriver(): ExternalEmailVerifier
    {
        return $this->container->make(ZeroBounceEmailVerifier::class);
    }
}
