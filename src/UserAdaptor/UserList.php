<?php

namespace WScore\Auth\UserAdaptor;

use WScore\Auth\UserProviderInterface;

class UserList implements UserProviderInterface
{
    /**
     * @var array
     */
    private $idList;

    /**
     * @param array $idList
     */
    public function __construct($idList)
    {
        $this->idList = $idList;
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
        if (array_key_exists($loginId, $this->idList)) {
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
        if (!array_key_exists($loginId, $this->idList)) {
            return null;
        }
        if ($this->idList[$loginId] === $password) {
            return $this->idList[$loginId];
        }
        return null;
    }
}