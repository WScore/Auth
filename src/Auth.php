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

    /**
     * @var RememberCookie
     */
    private $rememberCookie;

    // +----------------------------------------------------------------------+
    //  get the state of the auth
    // +----------------------------------------------------------------------+
    /**
     * @param UserInterface $user
     * @param RememberMe    $remember
     */
    public function __construct($user, $remember = null)
    {
        $this->user       = $user;
        $this->status     = self::AUTH_NONE;
        $this->loginInfo  = array();
        if($remember) {
            $this->rememberMe = $remember;
            $this->rememberCookie = new RememberCookie();
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
        if (!$this->user->verifyUserPw($pw)) {
            $this->status = self::AUTH_FAILED;
            return false;
        }
        $this->saveOk($id, self::BY_POST);
        if ($remember) {
            $this->rememberMe($id);
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
        $this->rememberMe($id);
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
    protected function getSaveId()
    {
        if ($this->saveId) {
            return $this->saveId;
        }
        $class = get_called_class();
        return str_replace('\\', '-', $class);
    }

    /**
     * @param        $id
     * @param string $by
     * @return bool
     */
    protected function saveOk($id, $by = self::BY_POST)
    {
        $this->status           = self::AUTH_OK;
        $save                   = [
            'id'   => $id,
            'time' => date('Y-m-d H:i:s'),
            'by'   => $by,
            'type' => $this->user->getUserType(),
        ];
        $this->loginInfo        = $save;
        $saveId                 = $this->getSaveId();
        $this->session[$saveId] = $save;
        return true;
    }

    /**
     * @param $id
     */
    protected function rememberMe($id)
    {
        if (!$this->rememberMe) {
            return;
        }
        if ($token = $this->rememberMe->rememberMe($id)) {
            $this->rememberCookie->save($id, $token);
        }
    }

    // +----------------------------------------------------------------------+
}