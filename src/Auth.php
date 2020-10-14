<?php
namespace WScore\Auth;

class Auth
{
    const KEY = 'WS-Auth';
    
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
     * @var UserProviderInterface
     */
    private $userProvider;

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

    /**
     * @var string|int
     */
    private $id;

    /**
     * @var mixed
     */
    private $user;

    // +----------------------------------------------------------------------+
    //  get the state of the auth
    // +----------------------------------------------------------------------+
    /**
     * @param UserProviderInterface    $userProvider
     */
    public function __construct($userProvider)
    {
        $this->userProvider = $userProvider;
        $this->status       = self::AUTH_NONE;
        $this->loginInfo    = array();
    }

    public function setRememberMe(RememberMeInterface $rememberMe, $cookie = null)
    {
        $this->rememberMe     = $rememberMe;
        $this->rememberCookie = $cookie ?: new RememberCookie();}

    /**
     * @param null|array $session
     */
    public function setSession(&$session = null)
    {
        $this->session = &$session;
    }

    /**
     * @return array
     */
    private function getSessionData()
    {
        $saveId = $this->getSaveId();
        if (is_null($this->session)) {
            if (array_key_exists(self::KEY, $_SESSION) && array_key_exists($saveId, $_SESSION[self::KEY])) {
                return $_SESSION[self::KEY][$saveId];
            }
            return [];
        }
        if (array_key_exists(self::KEY, $this->session) && array_key_exists($saveId, $this->session[self::KEY])) {
            return $this->session[self::KEY][$saveId];
        }
        return [];
    }

    /**
     * @param array $save
     */
    private function setSessionData($save)
    {
        $saveId = $this->getSaveId();
        if (is_null($this->session)) {
            $_SESSION[self::KEY][$saveId] = $save;
        } else {
            $this->session[self::KEY][$saveId] = $save;
        }
    }

    /**
     * @return bool
     */
    public function isLogin()
    {
        if ($this->status === self::AUTH_OK) {
            return true;
        }
        if ($this->checkSession()) {
            return true;
        }
        if ($this->checkRemembered()) {
            return true;
        }
        return false;
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
     * @return UserProviderInterface
     */
    public function getUserProvider()
    {
        return $this->userProvider;
    }

    /**
     * @return string|int
     */
    public function getUserId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param null|string $key
     * @return array|mixed
     */
    public function getLoginInfo($key = null)
    {
        if (is_null($key)) {
            return $this->loginInfo;
        }
        return array_key_exists($key, $this->loginInfo) ? $this->loginInfo[$key] : null;
    }

    /**
     * logout if logged in.
     */
    public function logout()
    {
        $this->id        = null;
        $this->user      = null;
        $this->status    = self::AUTH_NONE;
        $this->loginInfo = array();
        $this->setSessionData([]);
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
        if (!$this->user = $this->userProvider->getUserByIdAndPw($id, $pw)) {
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
     * @param $id
     * @return bool|mixed
     */
    public function forceLogin($id)
    {
        if ($this->user = $this->userProvider->getUserById($id)) {
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
        if (!$this->user = $this->userProvider->getUserById($id)) {
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
        $session = $this->getSessionData();
        if (!$session) {
            return false;
        }
        if (!isset($session['type'])) {
            return false;
        }
        if ($session['type'] !== $this->userProvider->getUserType()) {
            return false;
        }
        $this->id = $session['id'];
        if ($this->user = $this->userProvider->getUserById($this->id)) {
            return $this->saveOk($this->id, $session['by']);
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
        $class = get_class($this->userProvider);
        return 'auth-' . str_replace('\\', '-', $class);
    }

    /**
     * @param        $id
     * @param string $by
     * @return bool
     */
    private function saveOk($id, $by = self::BY_POST)
    {
        $this->id        = $id;
        $this->status    = self::AUTH_OK;
        $save            = [
            'id'   => $id,
            'time' => date('Y-m-d H:i:s'),
            'by'   => $by,
            'type' => $this->userProvider->getUserType(),
        ];
        $this->loginInfo = $save;
        $this->setSessionData($save);
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