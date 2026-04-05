<?php

declare(strict_types=1);

namespace tests\Auth;

use ArrayObject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use tests\Auth\mocks\RememberMock;
use tests\Auth\mocks\SimpleUserList;
use WScore\Auth\Auth;
use WScore\Auth\AuthKind;
use WScore\Auth\RememberCookie;

require_once dirname(__DIR__) . '/autoloader.php';

class RememberMe_Test extends TestCase
{
    /** @var ArrayObject<string, string> */
    public ArrayObject $idList;

    public SimpleUserList $user;

    public Auth $auth;

    /** @var array<mixed> */
    public array $session = [];

    public string $user_save_id;

    /** @var array<string, string> */
    public array $remembered = [];

    public RememberMock $rememberMe;

    public RememberCookie $cookie;

    /** @var ArrayObject<string, string> */
    public ArrayObject $cookie_data;

    /** @var list<array<string, mixed>> */
    public array $cookie_saved = [];

    protected function setUp(): void
    {
        $this->idList = new ArrayObject(
            [
                'test' => 'test-PW',
                'more' => 'more-PW',
            ]
        );
        $this->remembered = [
            'remember' => 'its-me',
        ];
        $this->user = new SimpleUserList($this->idList);
        $this->user_save_id = 'auth-' . str_replace('\\', '-', $this->user::class);

        $this->rememberMe = new RememberMock($this->remembered);
        $this->cookie_data = new ArrayObject();
        $this->cookie = new RememberCookie($this->cookie_data);
        $this->cookie->setSetCookie([$this, 'setCookie']);

        $this->auth = new Auth($this->user);
        $this->auth->setRememberMe($this->rememberMe, $this->cookie);
        $this->auth->setSession($this->session);
    }

    /** @param mixed $time */
    public function setCookie($name, $value, $time, $path, $secure): void
    {
        $this->cookie_saved[] = compact('name', 'value', 'time', 'path', 'secure');
    }

    public function test0(): void
    {
        $this->assertEquals('tests\Auth\mocks\SimpleUserList', $this->user::class);
        $this->assertEquals('tests\Auth\mocks\RememberMock', $this->rememberMe::class);
        $this->assertEquals(Auth::class, $this->auth::class);
        $this->assertEquals(RememberCookie::class, $this->cookie::class);
        $this->assertEquals('tests\Auth\mocks\SimpleUserList', $this->auth->getUserProvider()::class);
    }

    #[Test]
    public function login_with_rememberMeFlag_saves_remembered_data(): void
    {
        $this->assertEmpty($this->cookie_saved);
        $authOK = $this->auth->loginWithPassword('test', 'test-PW', true);
        $this->assertTrue($authOK);
        $this->assertTrue($this->auth->isLogin());

        $this->assertArrayHasKey('test', $this->rememberMe->remembered);
        $this->assertEquals('token-test', $this->rememberMe->remembered['test']);

        $this->assertNotEmpty($this->cookie_saved);
        $savedCookie = $this->cookie_saved[0];
        $this->assertEquals('remember-id', $savedCookie['name']);
        $this->assertEquals('test', $savedCookie['value']);

        $savedCookie = $this->cookie_saved[1];
        $this->assertEquals('remember-me', $savedCookie['name']);
        $this->assertEquals('token-test', $savedCookie['value']);
    }

    #[Test]
    public function isLoggedIn_using_remembered_data_successful(): void
    {
        $this->cookie_data['remember-id'] = 'remember';
        $this->cookie_data['remember-me'] = 'its-me';
        $this->idList['remember'] = 'remember-PW';
        $authOK = $this->auth->isLogin();
        $this->assertTrue($authOK);
        $this->assertTrue($this->auth->isLogin());

        $loginInfo = $this->auth->getLoginInfo();
        $this->assertNotEmpty($loginInfo);
        $this->assertEquals('remember', $loginInfo['loginId']);
        $this->assertArrayHasKey('time', $loginInfo);
        $this->assertTrue($this->auth->isLoginBy(AuthKind::Remember));
        $this->assertEquals(AuthKind::Remember, $loginInfo['kind']);
        $this->assertEquals('SimpleUserList', $loginInfo['type']);
        $this->assertEquals('remember-PW', $this->auth->getLoginUser()->secret);
    }

    #[Test]
    public function isLoggedIn_with_bad_id_fails(): void
    {
        $this->cookie_data['remember-id'] = 'no-remember';
        $this->cookie_data['remember-me'] = 'its-me';
        $this->idList['remember'] = 'remember-PW';
        $this->assertFalse($this->auth->isLogin());
    }

    #[Test]
    public function isLoggedIn_with_bad_pw_fails(): void
    {
        $this->cookie_data['remember-id'] = 'remember';
        $this->cookie_data['remember-me'] = 'its-not-me';
        $this->idList['remember'] = 'remember-PW';
        $this->assertFalse($this->auth->isLogin());
    }

    #[Test]
    public function isLoggedIn_without_id_in_cookie_fails(): void
    {
        $this->cookie_data['remember-me'] = 'its-me';
        $this->idList['remember'] = 'remember-PW';
        $this->assertFalse($this->auth->isLogin());
    }

    #[Test]
    public function isLoggedIn_without_pw_in_cookie_fails(): void
    {
        $this->cookie_data['remember-id'] = 'remember';
        $this->idList['remember'] = 'remember-PW';
        $this->assertFalse($this->auth->isLogin());
    }

    #[Test]
    public function isLoggedIn_without_user_data_fails(): void
    {
        $this->cookie_data['remember-id'] = 'remember';
        $this->cookie_data['remember-me'] = 'its-me';
        $this->idList['no-remember'] = 'remember-PW';
        $this->assertFalse($this->auth->isLogin());
    }
}
