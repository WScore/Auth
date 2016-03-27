<?php
namespace WScore\Auth;

interface UserProviderInterface
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
     * used for forcedLogin, but also for session/remember-me login 
     * if the $id is still valid user ID. 
     *
     * @param string $id
     * @return bool
     */
    public function verifyUserId($id);

    /**
     * verifies if the $id and $pw is valid user ID and a password.
     * 
     * used for login. 
     *
     * @param string $id
     * @param string $pw
     * @return bool
     */
    public function verifyUserPw($id, $pw);

    /**
     * get the user information.
     * 
     * @param string $id
     * @return mixed
     */
    public function getUserInfo($id);
}