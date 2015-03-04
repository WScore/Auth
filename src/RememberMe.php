<?php
namespace WScore\Auth;

class RememberMe
{

    /**
     * verifies if the $id and token are in remembrance.
     *
     * @param string $id
     * @param string $token
     * @return bool
     */
    public function verifyRemember($id, $token)
    {
        return true;
    }

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
    public function rememberMe($id)
    {
        return true;
    }
}

