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
    protected $status = self::AUTH_NONE;

    /**
     * name of session to save this auth state.
     *
     * @var string
     */
    protected $saveId;

    /**
     * @var array
     */
    protected $loginInfo = array();

    /**
     * @var UserInterface
     */
    protected $user;

    /**
     * @var array
     */
    protected $session = array();

    /**
     * @var RememberMe
     */
    protected $rememberMe;

    // +----------------------------------------------------------------------+
    //  get the state of the auth
    // +----------------------------------------------------------------------+
    /**
     * @param array $session
     */
    public function __construct(&$session = null)
    {
        if (is_null($session)) {
            $this->session = &$_SESSION;
        } else {
            $this->session = &$session;
        }
    }

    /**
     * @param RememberMe $remember
     */
    public function setRememberMe($remember)
    {
        $this->rememberMe = $remember;
    }

    /**
     * @param UserInterface $user
     */
    public function setUser($user)
    {
        $this->user      = $user;
        $this->status    = self::AUTH_NONE;
        $this->loginInfo = array();
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
     * @param string $id
     * @param string $pw
     * @param bool   $remember
     * @return bool
     */
    public function login($id, $pw, $remember = false)
    {
        if (!$this->user->verifyUserId($id)) {
            $this->status = self::AUTH_FAILED;
            return $this->isLogin();
        }
        if (!$this->user->verifyUserPw($pw)) {
            $this->status = self::AUTH_FAILED;
            return $this->isLogin();
        }
        $this->saveOk($id, self::BY_POST);
        if ($remember) {
            $this->rememberMe($id);
        }
        return $this->isLogin();
    }

    /**
     * @param $id
     * @return bool|mixed
     */
    public function forceLogin($id)
    {
        if ($this->user->verifyUserId($id)) {
            $this->saveOk($id, self::BY_FORCED);
        }
        return $this->isLogin();
    }

    /**
     * @return bool
     */
    public function getRemember()
    {
        if (!$this->rememberMe) {
            return false;
        }
        if (!$id = $this->rememberMe->getId()) {
            return false;
        }
        if (!$token = $this->rememberMe->getToken()) {
            return false;
        }
        if (!$this->user->verifyUserId($id)) {
            return false;
        }

        if ($this->user->verifyRemember($token)) {
            $this->saveOk($id, self::BY_REMEMBER);
            $this->rememberMe($id);
        }
        return $this->isLogin();
    }

    /**
     * @return bool
     */
    public function isLoggedIn()
    {
        $saveId = $this->getSaveId();
        if (!isset($this->session[$saveId])) {
            return false;
        }
        if (!isset($this->session[$saveId]['user'])) {
            return false;
        }
        if ($this->session[$saveId]['user'] !== $this->user->getUserTypeId()) {
            return false;
        }
        $id = $this->session[$saveId]['id'];
        if ($this->user->verifyUserId($id)) {
            $this->loginInfo = $this->session[$saveId];
            $this->status    = self::AUTH_OK;
        }
        return $this->isLogin();
    }

    // +----------------------------------------------------------------------+
    //  internal stuff
    // +----------------------------------------------------------------------+
    /**
     * @return mixed
     */
    protected function getSaveId()
    {
        if ($this->saveId) {
            return $this->saveId;
        }
        $class = get_called_class();
        return str_replace('\\', '-', $class);
    }

    /**
     *
     */
    protected function saveOk($id, $by = self::BY_POST)
    {
        $this->status           = self::AUTH_OK;
        $save                   = [
            'id'   => $id,
            'time' => date('Y-m-d H:i:s'),
            'by'   => $by,
            'user' => $this->user->getUserTypeId(),
        ];
        $this->loginInfo        = $save;
        $saveId                 = $this->getSaveId();
        $this->session[$saveId] = $save;
    }

    /**
     * @param $id
     */
    protected function rememberMe($id)
    {
        if (!$this->rememberMe) {
            return;
        }
        if ($token = $this->user->getRememberToken()) {
            $this->rememberMe->set($id, $token);
        }
    }

    // +----------------------------------------------------------------------+
}