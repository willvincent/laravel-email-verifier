<?php

declare(strict_types=1);

namespace App\Support\Email\Disposable;

interface DisposableDomainChecker
{
    public function isDisposable(string $domain): bool;
}
