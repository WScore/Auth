<?php
namespace tests\Auth;

use tests\Auth\mocks\SimpleUserList;
use WScore\Auth\Auth;

require_once( dirname( __DIR__ ) . '/autoloader.php' );

class Auth_Test extends \PHPUnit_Framework_TestCase
{
    var $idList = array();

    /**
     * @var SimpleUserList
     */
    var $user;

    /**
     * @var Auth
     */
    var $auth;

    var $session = array();

    var $user_save_id;

    function setup()
    {
        $this->idList       = array(
            'test' => 'test-PW',
            'more' => 'more-PW',
        );
        $this->user         = new SimpleUserList($this->idList);
        $this->user_save_id = 'auth-' . str_replace('\\', '-', get_class($this->user));
        $this->auth         = new Auth($this->user);
        $this->auth->setSession($this->session);
    }

    function test0()
    {
        $this->assertEquals('tests\Auth\mocks\SimpleUserList', get_class($this->user));
        $this->assertEquals('WScore\Auth\Auth', get_class($this->auth));
        $this->assertEquals('tests\Auth\mocks\SimpleUserList', get_class($this->auth->getUserProvider()));
    }

    // +----------------------------------------------------------------------+
    // +----------------------------------------------------------------------+
    /**
     * @test
     */
    function login_successful_using_post_input()
    {
        $authOK = $this->auth->login('test', 'test-PW');

        // test auth status
        $this->assertEquals(true, $authOK);
        $this->assertEquals(true, $this->auth->isLogin());

        // get loginInfo
        $loginInfo = $this->auth->getLoginInfo();
        
        // test
        $this->assertTrue($this->auth->isLoginBy(Auth::BY_POST));
        $this->assertEquals('test', $this->auth->getUserId());

        // directly test the session data.
        $this->assertNotEmpty($loginInfo);
        $this->assertEquals('test', $loginInfo['id']);
        $this->assertArrayHasKey('time', $loginInfo);
        $this->assertEquals(Auth::BY_POST, $loginInfo['by']);
        $this->assertEquals('SimpleUserList', $loginInfo['type']);
        $this->assertEquals('test-PW', $this->auth->getUser());

        // test what's saved in the session.
        $this->assertNotEmpty($this->session);
        $this->assertArrayHasKey($this->user_save_id, $this->session[Auth::KEY]);
        $saved = $this->session[Auth::KEY][$this->user_save_id];
        $this->assertEquals('test', $saved['id']);
        $this->assertArrayHasKey('time', $saved);
        $this->assertEquals(Auth::BY_POST, $saved['by']);
        $this->assertEquals('SimpleUserList', $saved['type']);
        $this->assertEquals('test-PW', $this->auth->getUser());
    }

    /**
     * @test
     */
    function login_successful_if_remember_flag_is_true_but_no_rememberMe()
    {
        $authOK = $this->auth->login('test', 'test-PW', true);

        // test auth status
        $this->assertEquals(true, $authOK);
        $this->assertEquals(true, $this->auth->isLogin());

        // get loginInfo
        $loginInfo = $this->auth->getLoginInfo();
        $this->assertNotEmpty($loginInfo);
        $this->assertEquals('test', $loginInfo['id']);
        $this->assertArrayHasKey('time', $loginInfo);
        $this->assertEquals(Auth::BY_POST, $loginInfo['by']);
        $this->assertEquals('SimpleUserList', $loginInfo['type']);
        $this->assertEquals('test-PW', $this->auth->getUser());

        // test what's saved in the session.
        $this->assertNotEmpty($this->session);
        $this->assertArrayHasKey($this->user_save_id, $this->session[Auth::KEY]);
        $saved = $this->session[Auth::KEY][$this->user_save_id];
        $this->assertEquals('test', $saved['id']);
        $this->assertArrayHasKey('time', $saved);
        $this->assertEquals(Auth::BY_POST, $saved['by']);
        $this->assertEquals('SimpleUserList', $saved['type']);
        $this->assertEquals('test-PW', $this->auth->getUser());
    }

    /**
     * @test
     */
    function login_fails_for_bad_id()
    {
        $authOK = $this->auth->login('bad', 'bad-PW');

        // test auth status
        $this->assertEquals(false, $authOK);
        $this->assertEquals(false, $this->auth->isLogin());

        // get loginInfo
        $loginInfo = $this->auth->getLoginInfo();
        $this->assertEmpty($loginInfo);

        // test what's saved in the session.
        $this->assertEquals(0, count($this->session));
    }

    /**
     * @test
     */
    function login_fails_for_bad_pw()
    {
        $authOK = $this->auth->login('test', 'bad-PW');

        // test auth status
        $this->assertEquals(false, $authOK);
        $this->assertEquals(false, $this->auth->isLogin());

        // get loginInfo
        $loginInfo = $this->auth->getLoginInfo();
        $this->assertEmpty($loginInfo);

        // test what's saved in the session.
        $this->assertEquals(0, count($this->session));
    }

    /**
     * @test
     */
    function isLoggedIn_authenticates_using_session_data()
    {
        // login with valid input (id/pw), and get the loginInfo.
        $this->auth->login('test', 'test-PW');
        $loginInfo = $this->auth->getLoginInfo();

        // set the loginInfo into the session.
        $session = [
            Auth::KEY => [$this->user_save_id => $loginInfo],
        ];
        $auth    = new Auth($this->user);
        $auth->setSession($session);

        // OK. now let's login without input.
        $authOK = $auth->isLogin();

        // test auth status
        $this->assertEquals(true, $authOK);
        $this->assertEquals(true, $auth->isLogin());


        // get loginInfo
        $loginInfo = $auth->getLoginInfo();
        $this->assertNotEmpty($loginInfo);
        $this->assertEquals('test', $loginInfo['id']);
        $this->assertArrayHasKey('time', $loginInfo);
        $this->assertEquals('test', $loginInfo['id']);
        $this->assertEquals(Auth::BY_POST, $loginInfo['by']);
        $this->assertEquals('SimpleUserList', $loginInfo['type']);
        $this->assertEquals('test-PW', $this->auth->getUser());
    }

    /**
     * @test
     */
    function forceLogin_successfully()
    {
        $authOK = $this->auth->forceLogin('test');
        // test auth status
        $this->assertEquals(true, $authOK);
        $this->assertEquals(true, $this->auth->isLogin());


        // get loginInfo
        $loginInfo = $this->auth->getLoginInfo();
        $this->assertNotEmpty($loginInfo);
        $this->assertEquals('test', $loginInfo['id']);
        $this->assertArrayHasKey('time', $loginInfo);
        $this->assertEquals(Auth::BY_FORCED, $loginInfo['by']);
        $this->assertEquals('SimpleUserList', $loginInfo['type']);
        $this->assertEquals('test-PW', $this->auth->getUser());
    }

    /**
     * @test
     */
    function forceLogin_fails_for_bad_id()
    {
        $authOK = $this->auth->forceLogin('bad');
        // test auth status
        $this->assertEquals(false, $authOK);
        $this->assertEquals(false, $this->auth->isLogin());
        $loginInfo = $this->auth->getLoginInfo();
        $this->assertEmpty($loginInfo);
    }

    /**
     * @test
     */
    function logout_()
    {
        $authOK = $this->auth->login('test', 'test-PW');

        // test auth status
        $this->assertEquals(true, $authOK);
        $this->assertEquals(true, $this->auth->isLogin());

        $this->auth->logout();
        $this->assertEquals(false, $this->auth->isLogin());
        $this->assertEmpty($this->auth->getLoginInfo());
        $this->assertEmpty($this->auth->getUser());
    }
}