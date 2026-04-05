<?php

declare(strict_types=1);

namespace WScore\Auth\Session;

use WScore\Auth\Contracts\AuthSessionStoreInterface;

/**
 * Binds auth payload to $root[$namespace][$segmentKey] (e.g. $_SESSION['WS-Auth']['my-provider']).
 */
final class ArrayAuthSessionStore implements AuthSessionStoreInterface
{
    /**
     * @param array<mixed> $root
     */
    public function __construct(
        private array &$root,
        private string $namespace,
        private string $segmentKey,
    ) {
    }

    public function read(): ?array
    {
        if (!isset($this->root[$this->namespace][$this->segmentKey])) {
            return null;
        }
        $data = $this->root[$this->namespace][$this->segmentKey];
        if (!is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function write(array $payload): void
    {
        if (!isset($this->root[$this->namespace]) || !is_array($this->root[$this->namespace])) {
            $this->root[$this->namespace] = [];
        }
        $this->root[$this->namespace][$this->segmentKey] = $payload;
    }

    public function clear(): void
    {
        unset($this->root[$this->namespace][$this->segmentKey]);
        if (isset($this->root[$this->namespace]) && $this->root[$this->namespace] === []) {
            unset($this->root[$this->namespace]);
        }
    }
}
