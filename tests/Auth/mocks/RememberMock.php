<?php

namespace tests\Auth\mocks;

use WScore\Auth\Contracts\RememberMeInterface;

class RememberMock implements RememberMeInterface
{
    /**
     * index of [ $id => $token ]
     */
    public array $remembered = [];

    public function __construct(array $list = [])
    {
        $this->remembered = $list;
    }

    /**
     * verifies if the $id and token are in remembrance.
     *
     * @param int|string $loginId
     * @param string $token
     * @return bool
     */
    public function verifyRemember(int|string $loginId, string $token): bool
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
     * otherwise, the original $ token *maybe* reused or a new
     * token maybe generated.
     *
     * @param int|string $loginId
     * @param string|null $token
     * @return bool|string
     */
    public function generateToken(int|string $loginId, ?string $token): bool|string
    {
        if (array_key_exists($loginId, $this->remembered)) {
            return $this->remembered[$loginId];
        }
        $this->remembered[$loginId] = 'token-' . $loginId;
        return $this->remembered[$loginId];
    }

    public function removeToken(int|string $loginId): void
    {
        unset($this->remembered[$loginId]);
    }
}