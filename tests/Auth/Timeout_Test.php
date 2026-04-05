<?php

declare(strict_types=1);

namespace tests\Auth;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use tests\Auth\mocks\SimpleUserList;
use WScore\Auth\Auth;
use ArrayObject;

require_once dirname(__DIR__) . '/autoloader.php';

class Timeout_Test extends TestCase
{
    /** @var array<mixed> */
    public array $session = [];
    public SimpleUserList $user;
    public Auth $auth;

    protected function setUp(): void
    {
        $idList = new ArrayObject(['test' => 'test-PW']);
        $this->user = new SimpleUserList($idList);
        $this->auth = new Auth($this->user);
        $this->auth->setSession($this->session);
    }

    #[Test]
    public function absolute_timeout_invalidates_session(): void
    {
        $this->auth->setAbsoluteTimeout(3600); // 1 hour
        $this->auth->loginWithPassword('test', 'test-PW');
        $this->assertTrue($this->auth->isLogin());

        // Simulate time passage by modifying session payload
        $payload = $this->session[Auth::KEY]['SimpleUserList'];
        $payload['time'] = date('Y-m-d H:i:s', time() - 3601);
        $this->session[Auth::KEY]['SimpleUserList'] = $payload;

        // Verify with the same instance first (this tests the logic of isLogin refreshing/checking)
        $this->assertFalse($this->auth->isLogin());
        $this->assertEmpty($this->session);
    }

    #[Test]
    public function activity_timeout_invalidates_session(): void
    {
        $this->auth->setActivityTimeout(300); // 5 minutes
        $this->auth->loginWithPassword('test', 'test-PW');
        $this->assertTrue($this->auth->isLogin());

        // Simulate inactivity
        $payload = $this->session[Auth::KEY]['SimpleUserList'];
        $payload['lastActivity'] = time() - 301;
        $this->session[Auth::KEY]['SimpleUserList'] = $payload;

        $this->assertFalse($this->auth->isLogin());
        $this->assertEmpty($this->session);
    }

    #[Test]
    public function activity_timeout_is_refreshed_on_access(): void
    {
        $this->auth->setActivityTimeout(300);
        $this->auth->loginWithPassword('test', 'test-PW');
        $initialActivity = $this->session[Auth::KEY]['SimpleUserList']['lastActivity'];

        // Wait a bit (simulate small delay)
        sleep(1);

        $this->assertTrue($this->auth->isLogin());
        $refreshedActivity = $this->session[Auth::KEY]['SimpleUserList']['lastActivity'];

        $this->assertGreaterThan($initialActivity, $refreshedActivity);
    }
}
