<?php

declare(strict_types=1);

namespace WScore\Auth\Session;

use RuntimeException;
use WScore\Auth\Contracts\AuthSessionStoreInterface;

/**
 * Binds auth payload to $root[$namespace][$segmentKey] (e.g., $_SESSION['WS-Auth']['my-provider']).
 */
final class ArrayAuthSessionStore implements AuthSessionStoreInterface
{
    /**
     * @param array<string, mixed> $root top-level session bucket (by reference)
     */
    public function __construct(
        private array &$root,
        private readonly string $namespace,
    ) {
    }

    /**
     * Use the active PHP session as the root bucket (same reference as {@see $_SESSION}).
     *
     * @throws RuntimeException when no PHP session is active
     */
    public static function forPhpSession(string $namespace): self
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            throw new RuntimeException(
                'Session is not active. Pass a session array by reference to Auth constructor or call setSession().',
            );
        }
        $root = &$_SESSION;

        return new self($root, $namespace);
    }

    public function read(string $segmentKey): ?array
    {
        if (!isset($this->root[$this->namespace][$segmentKey])) {
            return null;
        }
        $data = $this->root[$this->namespace][$segmentKey];
        if (!is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function write(string $segmentKey, array $payload): void
    {
        if (!isset($this->root[$this->namespace]) || !is_array($this->root[$this->namespace])) {
            $this->root[$this->namespace] = [];
        }
        $this->root[$this->namespace][$segmentKey] = $payload;
    }

    public function clear(string $segmentKey): void
    {
        unset($this->root[$this->namespace][$segmentKey]);
        if (isset($this->root[$this->namespace]) && $this->root[$this->namespace] === []) {
            unset($this->root[$this->namespace]);
        }
    }
}
