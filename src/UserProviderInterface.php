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
     * returns user data based on user $id. 
     * must return NULL if no $id exists for login. 
     * 
     * @param string|int $loginId
     * @return mixed|null
     */
    public function getUserById($loginId);
    
    /**
     * returns user data based on user $id with 
     * valid $pw (password). 
     * must return NULL if no $id exists or $pw is invalidated. 
     * 
     * @param string|int $loginId
     * @param string $password
     * @return mixed|null
     */
    public function getUserByIdAndPw($loginId, $password);
}