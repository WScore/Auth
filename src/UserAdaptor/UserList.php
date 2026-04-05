<?php

declare(strict_types=1);

namespace WScore\Auth\UserAdaptor;

use ArrayAccess;
use WScore\Auth\AuthKind;
use WScore\Auth\Contracts\UserProviderInterface;
use WScore\Auth\Identity;

/**
 * Sample in-memory user list: id => plain password string. User object is stdClass with id + secret.
 */
class UserList implements UserProviderInterface
{
    /**
     * @param array<string|int, string>|ArrayAccess<string|int, string> $idList
     */
    public function __construct(
        private array|ArrayAccess $idList,
    ) {
    }

    public function getProviderKey(): string
    {
        return 'user-list';
    }

    public function findByIdentity(Identity $identity): ?object
    {
        return match ($identity->kind) {
            AuthKind::Password => $this->findByPassword($identity),
            AuthKind::ForceLogin => $this->findByForceLogin($identity),
            AuthKind::OAuth, AuthKind::OneTimeToken, AuthKind::Remember => null,
        };
    }

    private function findByPassword(Identity $identity): ?object
    {
        $id = $identity->credentials['id'] ?? null;
        $password = $identity->credentials['password'] ?? null;
        if (!is_string($id) && !is_int($id)) {
            return null;
        }
        if (!is_string($password)) {
            return null;
        }
        if (!$this->hasKey($id)) {
            return null;
        }
        if ($this->getSecret($id) !== $password) {
            return null;
        }

        return $this->makeUser($id);
    }

    private function findByForceLogin(Identity $identity): ?object
    {
        $id = $identity->credentials['id'] ?? null;
        if (!is_string($id) && !is_int($id)) {
            return null;
        }
        if (!$this->hasKey($id)) {
            return null;
        }

        return $this->makeUser($id);
    }

    public function getUserId(object $user): string|int
    {
        return $user->id;
    }

    public function findById(string|int $userId): ?object
    {
        if (!$this->hasKey($userId)) {
            return null;
        }

        return $this->makeUser($userId);
    }

    /**
     * @param string|int $id
     */
    private function makeUser(string|int $id): object
    {
        return (object) [
            'id' => $id,
            'secret' => $this->getSecret($id),
        ];
    }

    /**
     * @param string|int $id
     */
    private function getSecret(string|int $id): string
    {
        return $this->idList[$id];
    }

    /**
     * @param string|int $key
     */
    private function hasKey(string|int $key): bool
    {
        $list = $this->idList;
        if ($list instanceof ArrayAccess) {
            return $list->offsetExists($key);
        }

        return array_key_exists($key, $list);
    }
}
