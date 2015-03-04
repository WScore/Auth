<?php
namespace WScore\Auth;

interface UserInterface
{
    /**
     * returns user type token string to identify the
     * user when using multiple user object.
     *
     * @return string
     */
    public function getUserType();

    /**
     * verifies if $id is valid user's ID.
     *
     * @param string $id
     * @return bool
     */
    public function verifyUserId($id);

    /**
     * verifies if the $pw is valid password for the user.
     *
     * @param string $pw
     * @return bool
     */
    public function verifyUserPw($pw);

    /**
     * get the user information.
     * 
     * @param string $id
     * @return mixed
     */
    public function getUserInfo($id);
}