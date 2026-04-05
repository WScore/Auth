<?php

declare(strict_types=1);

namespace tests\Auth;

use PHPUnit\Framework\TestCase;
use tests\Auth\mocks\SimpleUserList;
use WScore\Auth\Auth;

require_once dirname(__DIR__) . '/autoloader.php';

class Auth_Test extends TestCase
{
    /** @var array<string, string> */
    public array $idList = [];

    public SimpleUserList $user;

    public Auth $auth;

    /** @var array<mixed> */
    public array $session = [];

    public string $user_save_id;

    protected function setUp(): void
    {
        $this->idList = [
            'test' => 'test-PW',
            'more' => 'more-PW',
        ];
        $this->user = new SimpleUserList($this->idList);
        $this->user_save_id = $this->user->getProviderKey();
        $this->auth = new Auth($this->user);
        $this->auth->setSession($this->session);
    }

    public function test0(): void
    {
        $this->assertEquals('tests\Auth\mocks\SimpleUserList', $this->user::class);
        $this->assertEquals(Auth::class, $this->auth::class);
        $this->assertEquals('tests\Auth\mocks\SimpleUserList', $this->auth->getUserProvider()::class);
    }

    /**
     * @test
     */
    public function login_successful_using_post_input(): void
    {
        $authOK = $this->auth->loginWithPassword('test', 'test-PW');

        $this->assertTrue($authOK);
        $this->assertTrue($this->auth->isLogin());

        $loginInfo = $this->auth->getLoginInfo();

        $this->assertTrue($this->auth->isLoginBy(Auth::BY_POST));
        $this->assertEquals('test', $this->auth->getLoginId());

        $this->assertNotEmpty($loginInfo);
        $this->assertEquals('test', $loginInfo['loginId']);
        $this->assertArrayHasKey('time', $loginInfo);
        $this->assertEquals(Auth::BY_POST, $loginInfo['loginBy']);
        $this->assertEquals('SimpleUserList', $loginInfo['type']);
        $this->assertEquals('test-PW', $this->auth->getLoginUser()->secret);

        $this->assertNotEmpty($this->session);
        $this->assertArrayHasKey($this->user_save_id, $this->session[Auth::KEY]);
        $saved = $this->session[Auth::KEY][$this->user_save_id];
        $this->assertEquals('test', $saved['userId']);
        $this->assertArrayHasKey('time', $saved);
        $this->assertEquals(Auth::BY_POST, $saved['loginBy']);
        $this->assertEquals('SimpleUserList', $saved['providerKey']);
        $this->assertEquals('test-PW', $this->auth->getLoginUser()->secret);
    }

    /**
     * @test
     */
    public function login_successful_if_remember_flag_is_true_but_no_rememberMe(): void
    {
        $authOK = $this->auth->loginWithPassword('test', 'test-PW', true);

        $this->assertTrue($authOK);
        $this->assertTrue($this->auth->isLogin());

        $loginInfo = $this->auth->getLoginInfo();
        $this->assertNotEmpty($loginInfo);
        $this->assertEquals('test', $loginInfo['loginId']);
        $this->assertArrayHasKey('time', $loginInfo);
        $this->assertEquals(Auth::BY_POST, $loginInfo['loginBy']);
        $this->assertEquals('SimpleUserList', $loginInfo['type']);
        $this->assertEquals('test-PW', $this->auth->getLoginUser()->secret);

        $this->assertNotEmpty($this->session);
        $this->assertArrayHasKey($this->user_save_id, $this->session[Auth::KEY]);
        $saved = $this->session[Auth::KEY][$this->user_save_id];
        $this->assertEquals('test', $saved['userId']);
        $this->assertArrayHasKey('time', $saved);
        $this->assertEquals(Auth::BY_POST, $saved['loginBy']);
        $this->assertEquals('SimpleUserList', $saved['providerKey']);
        $this->assertEquals('test-PW', $this->auth->getLoginUser()->secret);
    }

    /**
     * @test
     */
    public function login_fails_for_bad_id(): void
    {
        $authOK = $this->auth->loginWithPassword('bad', 'bad-PW');

        $this->assertFalse($authOK);
        $this->assertFalse($this->auth->isLogin());

        $loginInfo = $this->auth->getLoginInfo();
        $this->assertEmpty($loginInfo);

        $this->assertEquals(0, count($this->session));
    }

    /**
     * @test
     */
    public function login_fails_for_bad_pw(): void
    {
        $authOK = $this->auth->loginWithPassword('test', 'bad-PW');

        $this->assertFalse($authOK);
        $this->assertFalse($this->auth->isLogin());

        $loginInfo = $this->auth->getLoginInfo();
        $this->assertEmpty($loginInfo);

        $this->assertEquals(0, count($this->session));
    }

    /**
     * @test
     */
    public function isLoggedIn_authenticates_using_session_data(): void
    {
        $this->auth->loginWithPassword('test', 'test-PW');
        $loginInfo = $this->auth->getLoginInfo();

        $session = [
            Auth::KEY => [
                $this->user_save_id => [
                    'userId' => 'test',
                    'providerKey' => 'SimpleUserList',
                    'loginKind' => 'Password',
                    'loginBy' => Auth::BY_POST,
                    'time' => $loginInfo['time'],
                ],
            ],
        ];
        $auth = new Auth($this->user);
        $auth->setSession($session);

        $authOK = $auth->isLogin();

        $this->assertTrue($authOK);
        $this->assertTrue($auth->isLogin());

        $loginInfo = $auth->getLoginInfo();
        $this->assertNotEmpty($loginInfo);
        $this->assertEquals('test', $loginInfo['loginId']);
        $this->assertArrayHasKey('time', $loginInfo);
        $this->assertEquals(Auth::BY_POST, $loginInfo['loginBy']);
        $this->assertEquals('SimpleUserList', $loginInfo['type']);
        $this->assertEquals('test-PW', $auth->getLoginUser()->secret);
    }

    /**
     * @test
     */
    public function forceLogin_successfully(): void
    {
        $authOK = $this->auth->forceLogin('test');
        $this->assertTrue($authOK);
        $this->assertTrue($this->auth->isLogin());

        $loginInfo = $this->auth->getLoginInfo();
        $this->assertNotEmpty($loginInfo);
        $this->assertEquals('test', $loginInfo['loginId']);
        $this->assertArrayHasKey('time', $loginInfo);
        $this->assertEquals(Auth::BY_FORCED, $loginInfo['loginBy']);
        $this->assertEquals('SimpleUserList', $loginInfo['type']);
        $this->assertEquals('test-PW', $this->auth->getLoginUser()->secret);
    }

    /**
     * @test
     */
    public function forceLogin_fails_for_bad_id(): void
    {
        $authOK = $this->auth->forceLogin('bad');
        $this->assertFalse($authOK);
        $this->assertFalse($this->auth->isLogin());
        $loginInfo = $this->auth->getLoginInfo();
        $this->assertEmpty($loginInfo);
    }

    /**
     * @test
     */
    public function logout_(): void
    {
        $authOK = $this->auth->loginWithPassword('test', 'test-PW');

        $this->assertTrue($authOK);
        $this->assertTrue($this->auth->isLogin());

        $this->auth->logout();
        $this->assertFalse($this->auth->isLogin());
        $this->assertEmpty($this->auth->getLoginInfo());
        $this->assertNull($this->auth->getLoginUser());
    }
}
