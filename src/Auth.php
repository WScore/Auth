<?php
namespace WScore\Auth;

class Auth
{
    const AUTH_NONE = 0;
    const AUTH_OK = 1;
    const AUTH_FAILED = -1;

    const BY_POST = 'post';
    const BY_REMEMBER = 'remember';
    const BY_FORCED = 'forced';
    const BY_SECRET = 'secret';

    /**
     * @var int
     */
    private $status = self::AUTH_NONE;

    /**
     * @var array
     */
    private $loginInfo = array();

    /**
     * @var UserInterface
     */
    private $user;

    /**
     * @var array
     */
    private $session = array();

    /**
     * @var RememberMeInterface
     */
    private $rememberMe;

    /**
     * @var RememberCookie
     */
    private $rememberCookie;

    // +----------------------------------------------------------------------+
    //  get the state of the auth
    // +----------------------------------------------------------------------+
    /**
     * @param UserInterface            $user
     * @param null|RememberMeInterface $remember
     * @param null|RememberCookie      $cookie
     */
    public function __construct($user, $remember = null, $cookie = null)
    {
        $this->user       = $user;
        $this->status     = self::AUTH_NONE;
        $this->loginInfo  = array();
        if($remember) {
            $this->rememberMe = $remember;
            $this->rememberCookie = $cookie ?: new RememberCookie();
        }
    }
    
    /**
     * @param null $session
     */
    public function setSession(&$session = null)
    {
        if (is_null($session)) {
            $this->session = &$_SESSION;
        } else {
            $this->session = &$session;
        }
    }
    
    /**
     * @return bool
     */
    public function isLogin()
    {
        return $this->status === self::AUTH_OK;
    }

    /**
     * @param string $by
     * @return bool
     */
    public function isLoginBy($by)
    {
        if (!$this->isLogin()) {
            return false;
        }
        return $by == $this->loginInfo['by'];
    }

    /**
     * @return UserInterface
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return mixed
     */
    public function getUserInfo()
    {
        return $this->loginInfo['user'];
    }

    /**
     * @return array
     */
    public function getLoginInfo()
    {
        return $this->loginInfo;
    }

    /**
     * logout if logged in.
     */
    public function logout()
    {
        $saveId = $this->getSaveId();
        if (isset($this->session[$saveId])) {
            unset($this->session[$saveId]);
        }
    }

    // +----------------------------------------------------------------------+
    //  authorization
    // +----------------------------------------------------------------------+
    /**
     * login with id and pw to be validated with userInterface.
     * 
     * @param string $id
     * @param string $pw
     * @param bool   $remember
     * @return bool
     */
    public function login($id, $pw, $remember = false)
    {
        if (!$this->user->verifyUserId($id)) {
            $this->status = self::AUTH_FAILED;
            return false;
        }
        if (!$this->user->verifyUserPw($id, $pw)) {
            $this->status = self::AUTH_FAILED;
            return false;
        }
        $this->saveOk($id, self::BY_POST);
        if ($remember) {
            $this->rememberMe($id, null);
        }
        return true;
    }

    /**
     * checks if already logged in via session or remember-me.
     * 
     * @return bool
     */    
    public function isLoggedIn()
    {
        if ($this->checkSession()) {
            return true;
        }
        if ($this->checkRemembered()) {
            return true;
        }
        return false;
    }

    /**
     * @param $id
     * @return bool|mixed
     */
    public function forceLogin($id)
    {
        if ($this->user->verifyUserId($id)) {
            return $this->saveOk($id, self::BY_FORCED);
        }
        return false;
    }

    /**
     * @return bool
     */
    private function checkRemembered()
    {
        if (!$this->rememberMe) {
            return false;
        }
        if (!$id = $this->rememberCookie->retrieveId()) {
            return false;
        }
        if (!$token = $this->rememberCookie->retrieveToken()) {
            return false;
        }
        if (!$this->rememberMe->verifyRemember($id, $token)) {
            return false;
        }
        if (!$this->user->verifyUserId($id)) {
            return false;
        }

        $this->saveOk($id, self::BY_REMEMBER);
        $this->rememberMe($id, $token);
        return true;
    }

    /**
     * @return bool
     */
    private function checkSession()
    {
        $saveId = $this->getSaveId();
        if (!isset($this->session[$saveId])) {
            return false;
        }
        if (!isset($this->session[$saveId]['type'])) {
            return false;
        }
        if ($this->session[$saveId]['type'] !== $this->user->getUserType()) {
            return false;
        }
        $id = $this->session[$saveId]['id'];
        if ($this->user->verifyUserId($id)) {
            return $this->saveOk($id, $this->session[$saveId]['by']);
        }
        return false;
    }

    // +----------------------------------------------------------------------+
    //  internal stuff
    // +----------------------------------------------------------------------+
    /**
     * @return mixed
     */
    private function getSaveId()
    {
        $class = get_class($this->user);
        return 'auth-'.str_replace('\\', '-', $class);
    }

    /**
     * @param        $id
     * @param string $by
     * @return bool
     */
    private function saveOk($id, $by = self::BY_POST)
    {
        $this->status           = self::AUTH_OK;
        $save                   = [
            'id'   => $id,
            'time' => date('Y-m-d H:i:s'),
            'by'   => $by,
            'type' => $this->user->getUserType(),
            'user' => $this->user->getUserInfo($id),
        ];
        $this->loginInfo        = $save;
        $saveId                 = $this->getSaveId();
        $this->session[$saveId] = $save;
        return true;
    }

    /**
     * @param string $id
     * @param string $token
     */
    private function rememberMe($id, $token)
    {
        if (!$this->rememberMe) {
            return;
        }
        if ($token = $this->rememberMe->rememberMe($id, $token)) {
            $this->rememberCookie->save($id, $token);
        }
    }

    // +----------------------------------------------------------------------+
}