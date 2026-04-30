<?php

declare(strict_types=1);

namespace WScore\Auth\UserAdaptor;

/**
 * User provider backed by Apache `.htpasswd` (or `.htaccess` via AuthUserFile).
 *
 * This class is functionally equivalent to {@see UserPasswd} but keeps a more explicit name.
 */
class HtpasswdUserList extends UserPasswd
{
    public function getProviderKey(): string
    {
        return 'htpasswd-user-list';
    }

    public static function fromHtpasswd(string $htpasswdPath): self
    {
        $list = parent::parseHtpasswdFile($htpasswdPath);

        return new self($list);
    }

    public static function fromHtaccess(string $htaccessPath, ?string $documentRoot = null): self
    {
        $authUserFile = parent::extractAuthUserFileFromHtaccess($htaccessPath);
        if ($authUserFile === null || $authUserFile === '') {
            throw new \RuntimeException('AuthUserFile directive not found in .htaccess: ' . $htaccessPath);
        }

        $resolved = $authUserFile;
        if (!parent::isAbsolutePath($resolved)) {
            $base = $documentRoot ?: dirname($htaccessPath);
            $resolved = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($resolved, DIRECTORY_SEPARATOR);
        }

        return self::fromHtpasswd($resolved);
    }
}
