<?php

namespace WScore\Auth\UserAdaptor;

use ArrayAccess;
use WScore\Auth\Contracts\UserProviderInterface;

class UserList implements UserProviderInterface
{
    /**
     * @var array|ArrayAccess
     */
    private $idList;

    /**
     * @param array|ArrayAccess $idList
     */
    public function __construct($idList)
    {
        $this->idList = $idList;
    }

    /**
     * @param array|ArrayAccess $container
     * @param string|int $key
     */
    private function hasKey($container, $key): bool
    {
        if ($container instanceof ArrayAccess) {
            return $container->offsetExists($key);
        }

        return array_key_exists($key, $container);
    }

    /**
     * returns user type token string to identify the
     * user when using multiple user object.
     *
     * @return string
     */
    public function getUserType()
    {
        return 'user-list';
    }

    /**
     * returns user data based on user $id.
     * must return NULL if no $id exists for login.
     *
     * @param string|int $loginId
     * @return mixed|null
     */
    public function getUserById($loginId)
    {
        if ($this->hasKey($this->idList, $loginId)) {
            return $this->idList[$loginId];
        }
        return null;
    }

    /**
     * returns user data based on user $id with
     * valid $pw (password).
     * must return NULL if no $id exists or $pw is invalidated.
     *
     * @param string|int $loginId
     * @param string $password
     * @return mixed|null
     */
    public function getUserByIdAndPw($loginId, $password)
    {
        if (!$this->hasKey($this->idList, $loginId)) {
            return null;
        }
        if ($this->idList[$loginId] === $password) {
            return $this->idList[$loginId];
        }
        return null;
    }
}