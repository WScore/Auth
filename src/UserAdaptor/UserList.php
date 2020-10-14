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
    public function __construct( $idList )
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
     * @param string|int $id
     * @return mixed|null
     */
    public function getUserById($id)
    {
        if(array_key_exists($id, $this->idList)) {
            return $this->idList[$id];
        }
        return null;
    }

    /**
     * returns user data based on user $id with
     * valid $pw (password).
     * must return NULL if no $id exists or $pw is invalidated.
     *
     * @param string|int $id
     * @param string     $pw
     * @return mixed|null
     */
    public function getUserByIdAndPw($id, $pw)
    {
        if(!array_key_exists($id, $this->idList)) {
            return null;
        }
        if ($this->idList[$id] === $pw) {
            return $this->idList[$id];
        }
        return null;
    }
}