<?php
/**
 * Created by PhpStorm.
 * User: asao
 * Date: 15/03/05
 * Time: 2:23
 */
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
     * returns remember token. tokens maybe newly generated one
     * if this is the first time to remember, or return the
     * existing token saved from previous session.
     *
     * set false not to use remember-me.
     *
     * @param string $id
     * @return bool|string
     */
    public function rememberMe($id);
}