<?php
namespace WScore\Auth\UserAdaptor;

use WScore\Auth\UserInterface;

class UserList implements UserInterface
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
     * verifies if $id is valid user's ID.
     *
     * @param string $id
     * @return bool
     */
    public function verifyUserId($id)
    {
        return array_key_exists($id, $this->idList);
    }

    /**
     * verifies if the $pw is valid password for the user.
     *
     * @param string $id
     * @param string $pw
     * @return bool
     */
    public function verifyUserPw($id, $pw)
    {
        if(array_key_exists($id, $this->idList)) {
            return $this->idList[$id] === $pw;
        }
        return false;
    }

    /**
     * get the user information.
     *
     * @param string $id
     * @return mixed
     */
    public function getUserInfo($id)
    {
        if(array_key_exists($id, $this->idList)) {
            return $this->idList[$id];
        }
        return [];
    }
}