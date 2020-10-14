<?php

namespace WScore\Auth;

interface RememberMeInterface
{
    /**
     * verifies if the $id and token are in remembrance.
     *
     * @param string|int $loginId
     * @param string $token
     * @return bool
     */
    public function verifyRemember($loginId, $token);

    /**
     * must return a remember token for the $id.
     *
     * a new token must be generated if $token is null.
     * otherwise, return original $token or *maybe* return the original token for reusing.
     *
     * @param string|int $loginId
     * @param string|null $token
     * @return bool|string
     */
    public function generateToken($loginId, $token);
}