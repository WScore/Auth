<?php

declare(strict_types=1);

namespace WScore\Auth\RememberAdaptor;

use ArrayAccess;

class RememberCookie
{
    protected string $name_id = 'remember-id';

    protected string $token_id = 'remember-me';

    /** @var array<string, mixed>|ArrayAccess<string, mixed> */
    protected array|ArrayAccess $cookie;

    protected int $rememberDays;

    /** @var callable|null */
    protected $setCookie = 'setcookie';

    /**
     * @param ArrayAccess<string, mixed>|array<string, mixed>|null $cookie  Pass a bag for tests; null/omit uses $_COOKIE
     * @param positive-int $rememberDays
     */
    public function __construct(array|ArrayAccess &$cookie = null, int $rememberDays = 7)
    {
        $this->rememberDays = max(1, $rememberDays);
        if ($cookie) {
            $this->cookie = &$cookie;
        } else {
            $this->cookie = &$_COOKIE;
        }
    }

    /**
     * Remember-me cookie against superglobals (typical production use).
     *
     * @param positive-int $rememberDays
     */
    public static function forBrowser(int $rememberDays = 7): self
    {
        $unused = null;

        return new self($unused, $rememberDays);
    }

    /**
     * @param positive-int $days
     */
    public function setRememberDays(int $days): void
    {
        $this->rememberDays = max(1, $days);
    }

    public function getRememberDays(): int
    {
        return $this->rememberDays;
    }

    public function setSetCookie(?callable $setter = null): void
    {
        $this->setCookie = $setter;
    }

    public function retrieveId(): ?string
    {
        return $this->get($this->name_id);
    }

    public function retrieveToken(): ?string
    {
        return $this->get($this->token_id);
    }

    public function save(string $id, string $token): void
    {
        $time = time() + 60 * 60 * 24 * $this->rememberDays;
        $func = $this->setCookie;
        $func($this->name_id, $id, $time, '/', '');
        $func($this->token_id, $token, $time, '/', '');
    }

    /**
     * Clear remember-me cookie.
     */
    public function clear(): void
    {
        $time = time() - 3600;
        $func = $this->setCookie;
        $func($this->name_id, '', $time, '/', '');
        $func($this->token_id, '', $time, '/', '');
    }

    protected function get(string $name): ?string
    {
        $cookie = $this->cookie;
        if ($cookie instanceof ArrayAccess) {
            return $cookie->offsetExists($name) ? $cookie[$name] : null;
        }

        return array_key_exists($name, $cookie) ? $cookie[$name] : null;
    }
}
