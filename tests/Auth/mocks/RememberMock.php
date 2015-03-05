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
    public function __construct($list=[])
    {
        $this->remembered = $list;
    }
    
    /**
     * verifies if the $id and token are in remembrance.
     *
     * @param string $id
     * @param string $token
     * @return bool
     */
    public function verifyRemember($id, $token)
    {
        if (array_key_exists($id, $this->remembered)) {
            return $this->remembered[$id] === $token;
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
     * @param string      $id
     * @param string|null $token
     * @return bool|string
     */
    public function rememberMe($id, $token)
    {
        if (array_key_exists($id, $this->remembered)) {
            return $this->remembered[$id];
        }
        $this->remembered[$id] = 'token-'.$id; 
        return $this->remembered[$id];
    }
}