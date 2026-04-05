<?php

declare(strict_types=1);

namespace WScore\Auth;

/**
 * @phpstan-type Credentials array<string, mixed>
 * @phpstan-type Options array<string, mixed>
 */
final readonly class Identity
{
    /**
     * @param Credentials $credentials
     * @param Options $options e.g. OAuth: ['provider' => 'google'], remember: ['remember' => true]
     */
    public function __construct(
        public AuthKind $kind,
        public array $credentials,
        public array $options = [],
    ) {
    }
}
