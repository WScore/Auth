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
    public function getUserTypeId();

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
     * verifies if the remember token is valid for the user.
     *
     * @param string $token
     * @return bool
     */
    public function verifyRemember($token);

    /**
     * returns remember token. tokens maybe newly generated one
     * if this is the first time to remember, or return the
     * existing token saved from previous session.
     *
     * set false not to use remember-me.
     *
     * @return bool|string
     */
    public function getRememberToken();
}