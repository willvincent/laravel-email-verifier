<?php

declare(strict_types=1);

use WillVincent\EmailVerifier\Rules\DisposableDomainRule;
use WillVincent\EmailVerifier\Rules\DomainSanityRule;
use WillVincent\EmailVerifier\Rules\FormatRule;
use WillVincent\EmailVerifier\Rules\MxRule;
use WillVincent\EmailVerifier\Rules\PlusAddressingRule;
use WillVincent\EmailVerifier\Rules\RoleBasedLocalRule;

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum Acceptance Score
    |--------------------------------------------------------------------------
    |
    | The minimum score (0-100) required for an email to be considered valid.
    | Emails scoring below this threshold will be rejected. Default is 70.
    |
    */

    'min_score' => env('EMAIL_VERIFIER_MIN_SCORE', 70),

    /*
    |--------------------------------------------------------------------------
    | Email Normalization
    |--------------------------------------------------------------------------
    |
    | These options control how email addresses are normalized before
    | verification. Normalization ensures consistent formatting and can
    | help with deduplication.
    |
    */

    'normalize' => [
        // Whether to enable email normalization
        'enabled' => true,

        // Whether to lowercase the local part (before @)
        // Note: Email local parts are technically case-sensitive per RFC,
        // but most providers treat them as case-insensitive
        'lowercase_local' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | The ordered list of validation rules to apply during email verification.
    | Rules are executed in the order specified. You can add custom rules by
    | implementing the Rule contract and adding them to this array.
    |
    | To enable/disable rules, simply add or remove them from this array.
    | For example, to disable plus addressing detection, remove PlusAddressingRule::class.
    |
    */

    'rules' => [
        FormatRule::class,
        DomainSanityRule::class,
        RoleBasedLocalRule::class,
        PlusAddressingRule::class,
        DisposableDomainRule::class,
        MxRule::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Role-Based Email Detection
    |--------------------------------------------------------------------------
    |
    | List of local parts (before @) considered "role-based" addresses.
    | These are typically group mailboxes rather than individual users.
    | Detecting role-based addresses penalizes the score by 15 points.
    |
    */

    'role_based_locals' => [
        'abuse',
        'admin',
        'administrator',
        'billing',
        'contact',
        'hello',
        'help',
        'info',
        'mail',
        'no-reply',
        'noreply',
        'office',
        'postmaster',
        'sales',
        'security',
        'support',
        'team',
    ],

    /*
    |--------------------------------------------------------------------------
    | Disposable Email Domains
    |--------------------------------------------------------------------------
    |
    | Configuration for detecting disposable/temporary email addresses.
    | These services provide temporary inboxes and are often used for spam
    | or to avoid legitimate communication.
    |
    | To disable disposable domain checking, remove DisposableDomainRule
    | from the 'rules' array above.
    |
    */

    'disposable' => [
        // Additional domains to consider disposable beyond the fetched list
        'extra_domains' => [],

        // Local file path where the disposable domains list is stored
        'file' => storage_path('app/disposable_domains.txt'),

        // Remote URL to fetch the disposable domains list from
        'source_url' => env(
            'EMAIL_VERIFIER_DISPOSABLE_SOURCE_URL',
            'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/master/disposable_email_blocklist.conf'
        ),

        // Timeout in seconds when fetching the remote list
        'timeout_seconds' => env('EMAIL_VERIFIER_DISPOSABLE_TIMEOUT', 10),

        // Maximum file size in bytes to prevent memory issues (2MB default)
        'max_bytes' => env('EMAIL_VERIFIER_DISPOSABLE_MAX_BYTES', 2_000_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | MX Record Validation
    |--------------------------------------------------------------------------
    |
    | Strict mode: hard reject if no MX records found.
    | Non-strict mode: penalize score by 25 points but allow to continue.
    |
    | To disable MX record checking entirely, remove MxRule from the
    | 'rules' array above.
    |
    */

    'mx_strict' => env('EMAIL_VERIFIER_MX_STRICT', true),

    /*
    |--------------------------------------------------------------------------
    | External Verification Providers
    |--------------------------------------------------------------------------
    |
    | Configuration for third-party email verification services. These
    | providers offer advanced verification including SMTP testing, catch-all
    | detection, and reputation scoring. Set 'driver' to enable.
    |
    | Supported drivers: 'bouncer', 'emailable', 'kickbox', 'neverbounce',
    | 'verifiedemail', 'zerobounce', or null
    |
    */

    'external' => [
        // The external provider to use (null/empty disables external verification)
        'driver' => env('EMAIL_VERIFIER_EXTERNAL_DRIVER'),

        // Timeout in seconds for external API calls
        'timeout_seconds' => env('EMAIL_VERIFIER_TIMEOUT', 5),

        /*
        |----------------------------------------------------------------------
        | Bouncer Configuration
        | https://usebouncer.com
        |----------------------------------------------------------------------
        */
        'bouncer' => [
            'api_key' => env('BOUNCER_API_KEY'),
            'endpoint' => env('BOUNCER_ENDPOINT', 'https://api.usebouncer.com/v1.1/email/verify'),
        ],

        /*
        |----------------------------------------------------------------------
        | Emailable Configuration
        | https://emailable.com
        |----------------------------------------------------------------------
        */
        'emailable' => [
            'api_key' => env('EMAILABLE_API_KEY'),
            'endpoint' => env('EMAILABLE_ENDPOINT', 'https://api.emailable.com/v1/verify'),
        ],

        /*
        |----------------------------------------------------------------------
        | Kickbox Configuration
        | https://kickbox.com
        |----------------------------------------------------------------------
        */
        'kickbox' => [
            'api_key' => env('KICKBOX_API_KEY'),
            'endpoint' => env('KICKBOX_ENDPOINT', 'https://api.kickbox.com/v2/verify'),
        ],

        /*
        |----------------------------------------------------------------------
        | NeverBounce Configuration
        | https://neverbounce.com
        |----------------------------------------------------------------------
        */
        'neverbounce' => [
            'api_key' => env('NEVERBOUNCE_API_KEY'),
            'endpoint' => env('NEVERBOUNCE_ENDPOINT', 'https://api.neverbounce.com/v4/single/check'),
        ],

        /*
        |----------------------------------------------------------------------
        | VerifiedEmail Configuration
        | https://verified.email
        |----------------------------------------------------------------------
        */
        'verifiedemail' => [
            'api_key' => env('VERIFIEDEMAIL_API_KEY'),
            'endpoint' => env('VERIFIEDEMAIL_ENDPOINT', 'https://app.verify-email.org/api/v1/'),
        ],

        /*
        |----------------------------------------------------------------------
        | ZeroBounce Configuration
        | https://zerobounce.com
        |----------------------------------------------------------------------
        */
        'zerobounce' => [
            'api_key' => env('ZEROBOUNCE_API_KEY'),
            'endpoint' => env('ZEROBOUNCE_ENDPOINT', 'https://api.zerobounce.net/v2/validate'),
        ],
    ],
];
