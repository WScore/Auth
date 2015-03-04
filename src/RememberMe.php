<?php
namespace WScore\Auth;

class RememberMe
{
    protected $name_id = 'remember-id';

    protected $token_id = 'remember-me';

    protected $cookie = array();

    protected $rememberDays = 7;

    protected $setCookie = 'setcookie';

    /**
     * @param array $cookie
     */
    public function __construct(&$cookie = array())
    {
        if ($cookie) {
            $this->cookie = &$cookie;
        } else {
            $this->cookie = &$_COOKIE;
        }
    }

    /**
     * @param null|string $setter
     */
    public function setSetCookie($setter = null)
    {
        $this->setCookie = $setter;
    }

    /**
     * @return null|string
     */
    public function getId()
    {
        return $this->get($this->name_id);
    }

    /**
     * @return null|string
     */
    public function getToken()
    {
        return $this->get($this->token_id);
    }

    /**
     * @param string $id
     * @param string $token
     */
    public function set($id, $token)
    {
        $time = time() + 60 * 60 * 24 * $this->rememberDays;
        $func = $this->setCookie;
        $func($this->name_id, $id, $time, '/', true);
        $func($this->token_id, $token, $time, '/', true);
    }

    /**
     * @param string $name
     * @return null|string
     */
    protected function get($name)
    {
        return array_key_exists($name, $this->cookie) ? $this->cookie[$name] : null;
    }
}

