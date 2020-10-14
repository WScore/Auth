<?php

namespace tests\Auth\mocks;

use WScore\Auth\RememberMeInterface;

class RememberMock implements RememberMeInterface
{
    /**
     * index of [ $id => $token ]
     *
     * @var array
     */
    public $remembered = [];

    /**
     * @param array $list
     */
    public function __construct($list = [])
    {
        $this->remembered = $list;
    }

    /**
     * verifies if the $id and token are in remembrance.
     *
     * @param string $loginId
     * @param string $token
     * @return bool
     */
    public function verifyRemember($loginId, $token)
    {
        if (array_key_exists($loginId, $this->remembered)) {
            return $this->remembered[$loginId] === $token;
        }
        return false;
    }

    /**
     * returns remember token.
     *
     * a new token will be generated if $token is null.
     * otherwise, original $token *maybe* reused or a new
     * token maybe generated.
     *
     * @param string $loginId
     * @param string|null $token
     * @return bool|string
     */
    public function generateToken($loginId, $token)
    {
        if (array_key_exists($loginId, $this->remembered)) {
            return $this->remembered[$loginId];
        }
        $this->remembered[$loginId] = 'token-' . $loginId;
        return $this->remembered[$loginId];
    }
}