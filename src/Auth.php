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

    // +----------------------------------------------------------------------+
    //  get the state of the auth
    // +----------------------------------------------------------------------+
    /**
     * @param UserProviderInterface    $user
     * @param null|RememberMeInterface $remember
     * @param null|RememberCookie      $cookie
     */
    public function __construct($user, $remember = null, $cookie = null)
    {
        $this->userProvider = $user;
        $this->status       = self::AUTH_NONE;
        $this->loginInfo    = array();
        if ($remember) {
            $this->rememberMe     = $remember;
            $this->rememberCookie = $cookie ?: new RememberCookie();
        }
    }

    /**
     * @param null $session
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
            return array_key_exists($saveId, $_SESSION) ? $_SESSION[$saveId] : [];
        }
        return array_key_exists($saveId, $this->session) ? $this->session[$saveId] : [];
    }

    /**
     * @param array $save
     */
    private function setSessionData($save)
    {
        $saveId = $this->getSaveId();
        if (is_null($this->session)) {
            $_SESSION[$saveId] = $save;
        } else {
            $this->session[$saveId] = $save;
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
     * @return array
     */
    public function getUserId()
    {
        return $this->getLoginInfo('id');
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->userProvider->getUserInfo($this->getUserId());
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
        if (!$this->userProvider->verifyUserId($id)) {
            $this->status = self::AUTH_FAILED;
            return false;
        }
        if (!$this->userProvider->verifyUserPw($id, $pw)) {
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
        if ($this->userProvider->verifyUserId($id)) {
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
        if (!$this->userProvider->verifyUserId($id)) {
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
        $id = $session['id'];
        if ($this->userProvider->verifyUserId($id)) {
            return $this->saveOk($id, $session['by']);
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