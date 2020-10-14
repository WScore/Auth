<?php

namespace tests\Auth\mocks;

use WScore\Auth\UserAdaptor\UserList;

class SimpleUserList extends UserList
{
    /**
     * returns user type token string to identify the
     * user when using multiple user object.
     *
     * @return string
     */
    public function getUserType()
    {
        return 'SimpleUserList';
    }
}