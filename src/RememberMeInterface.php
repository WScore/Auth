<?php
namespace WScore\Auth;

interface RememberMeInterface
{
    /**
     * verifies if the $id and token are in remembrance.
     *
     * @param string $id
     * @param string $token
     * @return bool
     */
    public function verifyRemember($id, $token);

    /**
     * must return a remember token for the $id.
     *
     * a new token must be generated if $token is null. 
     * otherwise, return original $token or *maybe* return the original token for reusing. 
     *
     * @param string      $id
     * @param string|null $token
     * @return bool|string
     */
    public function generateToken($id, $token);
}