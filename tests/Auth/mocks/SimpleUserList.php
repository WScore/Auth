<?php

declare(strict_types=1);

namespace tests\Auth\mocks;

use WScore\Auth\UserAdaptor\UserList;

class SimpleUserList extends UserList
{
    public function getProviderKey(): string
    {
        return 'SimpleUserList';
    }
}
