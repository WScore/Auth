<?php

namespace WScore\Auth;

class Auth
{
    const KEY = 'WS-Auth';

    const AUTH_NONE = 0;
    const AUTH_OK = 1;
    const AUTH_FAILED = -1;

    const BY_POST = 'WITH_PWD';
    const BY_REMEMBER = 'REMEMBER';
    const BY_FORCED = 'FORCED';

    /**
     * @var int
     */
    private $status;

    /**
     * @var array
     */
    private $loginInfo;

    /**
     * @var UserProviderInterface
     */
    private $userProvider;

    /**
     * @var array
     */
    private $session = null;

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
    private $loginId;

    /**
     * @var mixed
     */
    private $loginUser;

    // +----------------------------------------------------------------------+
    //  get the state of the auth
    // +----------------------------------------------------------------------+
    /**
     * @param UserProviderInterface $userProvider
     * @param array|null $session
     */
    public function __construct(UserProviderInterface $userProvider, &$session = null)
    {
        $this->userProvider = $userProvider;
        $this->status = self::AUTH_NONE;
        $this->loginInfo = array();

        $this->setSession($session);
    }

    /**
     * set up remember-me option.
     *
     * @param RememberMeInterface $rememberMe
     * @param null $cookie
     */
    public function setRememberMe(RememberMeInterface $rememberMe, $cookie = null)
    {
        $this->rememberMe = $rememberMe;
        $this->rememberCookie = $cookie ?: new RememberCookie();
    }

    /**
     * @param null|array $session
     */
    public function setSession(&$session = null)
    {
        if ($session === null) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $this->session = &$_SESSION;
            } else {
                $this->session = [];
            }
            return;
        }
        $this->session = &$session;
    }

    /**
     * @return array
     */
    private function getSessionData()
    {
        $saveId = $this->getSaveId();
        if (array_key_exists(self::KEY, $this->session) && array_key_exists($saveId, $this->session[self::KEY])) {
            return $this->session[self::KEY][$saveId];
        }
        return [];
    }

    /**
     * @param array $save
     */
    private function setSessionData(array $save)
    {
        $saveId = $this->getSaveId();
        $this->session[self::KEY][$saveId] = $save;
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
    public function isLoginBy(string $by)
    {
        if (!$this->isLogin()) {
            return false;
        }
        return $by == $this->loginInfo['loginBy'];
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
    public function getLoginId()
    {
        return $this->loginId;
    }

    /**
     * @return mixed
     */
    public function getLoginUser()
    {
        return $this->loginUser;
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
        $this->loginId = null;
        $this->loginUser = null;
        $this->status = self::AUTH_NONE;
        $this->loginInfo = array();
        $this->setSessionData([]);
    }

    // +----------------------------------------------------------------------+
    //  authorization
    // +----------------------------------------------------------------------+
    /**
     * login with id and pw to be validated with userInterface.
     *
     * @param string $loginId
     * @param string $password
     * @param bool $remember
     * @return bool
     */
    public function login(string $loginId, string $password, $remember = false)
    {
        if (!$this->loginUser = $this->userProvider->getUserByIdAndPw($loginId, $password)) {
            $this->status = self::AUTH_FAILED;
            return false;
        }
        $this->createSessionData($loginId, self::BY_POST);
        if ($remember) {
            $this->rememberMe($loginId, null);
        }
        return true;
    }

    /**
     * @param string $loginId
     * @return bool
     */
    public function forceLogin(string $loginId)
    {
        if ($this->loginUser = $this->userProvider->getUserById($loginId)) {
            return $this->createSessionData($loginId, self::BY_FORCED);
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
        if (!$loginId = $this->rememberCookie->retrieveId()) {
            return false;
        }
        if (!$token = $this->rememberCookie->retrieveToken()) {
            return false;
        }
        if (!$this->rememberMe->verifyRemember($loginId, $token)) {
            return false;
        }
        if (!$this->loginUser = $this->userProvider->getUserById($loginId)) {
            return false;
        }

        $this->createSessionData($loginId, self::BY_REMEMBER);
        $this->rememberMe($loginId, $token);
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
        $this->loginId = $session['loginId'];
        if ($this->loginUser = $this->userProvider->getUserById($this->loginId)) {
            return $this->createSessionData($this->loginId, $session['loginBy']);
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
        return 'Type:' . $this->userProvider->getUserType();
    }

    /**
     * @param string $loginId
     * @param string $loginBy
     * @return bool
     */
    private function createSessionData(string $loginId, string $loginBy)
    {
        $this->loginId = $loginId;
        $this->status = self::AUTH_OK;
        $save = [
            'loginId' => $loginId,
            'time' => date('Y-m-d H:i:s'),
            'loginBy' => $loginBy,
            'type' => $this->userProvider->getUserType(),
        ];
        $this->loginInfo = $save;
        $this->setSessionData($save);
        return true;
    }

    /**
     * @param string $id
     * @param string|null $token
     */
    private function rememberMe(string $id, ?string $token)
    {
        if (!$this->rememberMe) {
            return;
        }
        $token = $this->rememberMe->generateToken($id, $token);
        if ($token) {
            $this->rememberCookie->save($id, $token);
        }
    }

    // +----------------------------------------------------------------------+
}