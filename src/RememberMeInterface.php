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
     * returns remember token.
     *
     * a new token will be generated if $token is null. 
     * otherwise, original $token *maybe* reused or a new 
     * token maybe generated. 
     *
     * @param string      $id
     * @param string|null $token
     * @return bool|string
     */
    public function rememberMe($id, $token);
}