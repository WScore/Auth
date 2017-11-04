WScore.Auth
===========

A simple authentication package.

### License

MIT License

### PSR 

PSR-1, PSR-2, and PSR-4.

### Installation

```sh
composer require "wscore/auth: ^0.3"
```

Getting Started
--------------

`Auth` requires `UserProviderInterface` object to access to user information. 

```php
$auth = new Auth(new UserProvider);
```

To authenticate a user, get user-id (`$id`) and user-password (`$pw`) from a login form, and 

```php
if ($auth->login($id, $pw)) {
    echo 'login success!';
}
```

to check for login later on, 

```php
$auth->isLogin();
```

You can retrieve login information such as;

```php
$auth->getUser(); // login user entity returned by UserProvider's getUserInfo() method.
$auth->getUserId(); // get login user's id. 
$auth->isLoginBy(Auth::BY_FORCED); // check for login method. 
```


UserProvider
------------

The `Auth` requires a user provider object implementing `UserProviderInterface`. 
The interface has 4 APIs; that are

* `getUserById($id)`: for retrieving a user entity for `$id` (a login user). 
* `getUserByIdAndPw($id, $pw)`: for retrieving a user entity for `$id` and valid `$pw`. 
* `getUserType()`: for retrieving a key-string to identify the user-provider. 

Remember-Me Option
------------------

To use Rmember-me option, construct `$auth` with 

* `$remember` object implementing `RememberMeInterface`, 
* `RememberCookie` object, 

then, when login, supply 3rd argument when `login` as
 
```php
$auth->login($id, $pw, true);
```

to save the `$id` and a remember-me token to cookie if login is successful. 
