<?php

namespace WScore\Auth\Contracts;

interface RememberMeInterface
{
    /**
     * verifies if the $id and token are in remembrance.
     *
     * @param int|string $loginId
     * @param string $token
     * @return bool
     */
    public function verifyRemember(int|string $loginId, string $token): bool;

    /**
     * must return a remember-token for the $id.
     *
     * a new token must be generated if $token is null.
     * otherwise, return the original $token or *maybe* return the original token for reusing.
     *
     * @param int|string $loginId
     * @param string|null $token
     * @return bool|string
     */
    public function generateToken(int|string $loginId, ?string $token): bool|string;

    /**
     * removes the remember-token for the $id.
     *
     * @param int|string $loginId
     * @return void
     */
    public function removeToken(int|string $loginId): void;
}
