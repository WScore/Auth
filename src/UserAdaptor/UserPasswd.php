<?php

declare(strict_types=1);

namespace WScore\Auth\UserAdaptor;

use RuntimeException;

/**
 * Sample user provider using ID/password verification.
 *
 * This implementation can read Apache `.htpasswd` (or `.htaccess` via AuthUserFile) and verifies:
 * - bcrypt / PHP password_hash: password_verify
 * - `$apr1$` (Apache MD5): APR1-MD5 recalculation
 * - `$1$` (MD5 crypt): MD5-CRYPT recalculation
 * - `{SHA}`: base64(sha1(password, true))
 *
 * Plain-text passwords are NOT accepted.
 */
class UserPasswd extends UserList
{
    public function getProviderKey(): string
    {
        return 'user-passwd';
    }

    /**
     * Create from `.htpasswd` file.
     *
     * @throws RuntimeException when file cannot be read
     */
    public static function fromHtpasswd(string $htpasswdPath): self
    {
        $list = self::parseHtpasswdFile($htpasswdPath);

        return new self($list);
    }

    /**
     * Create from `.htaccess` by resolving `AuthUserFile` directive.
     *
     * Notes:
     * - Common configs use absolute paths; relative paths are resolved against `$documentRoot` when provided,
     *   otherwise against the `.htaccess` directory.
     *
     * @throws RuntimeException when `.htaccess` is unreadable or AuthUserFile cannot be resolved/read
     */
    public static function fromHtaccess(string $htaccessPath, ?string $documentRoot = null): self
    {
        $authUserFile = self::extractAuthUserFileFromHtaccess($htaccessPath);
        if ($authUserFile === null || $authUserFile === '') {
            throw new RuntimeException('AuthUserFile directive not found in .htaccess: ' . $htaccessPath);
        }

        $resolved = $authUserFile;
        if (!self::isAbsolutePath($resolved)) {
            $base = $documentRoot ?: dirname($htaccessPath);
            $resolved = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($resolved, DIRECTORY_SEPARATOR);
        }

        return self::fromHtpasswd($resolved);
    }

    protected function verifyPassword(string|int $loginId, string $password): bool
    {
        $secret = $this->getSecret($loginId);

        // `{SHA}` (Apache "htpasswd -s")
        if (str_starts_with($secret, '{SHA}')) {
            $expected = substr($secret, 5);
            $actual = base64_encode(sha1($password, true));

            return hash_equals($expected, $actual);
        }

        // Apache MD5 (`htpasswd -m`)
        if (str_starts_with($secret, '$apr1$')) {
            $calc = self::md5Crypt($password, $secret, '$apr1$');

            return $calc !== null && hash_equals($secret, $calc);
        }

        // MD5-CRYPT (rare but possible)
        if (str_starts_with($secret, '$1$')) {
            $calc = self::md5Crypt($password, $secret, '$1$');

            return $calc !== null && hash_equals($secret, $calc);
        }

        // bcrypt / password_hash formats (`$2y$`, `$2b$`, `$argon2id$`, etc)
        if (str_starts_with($secret, '$')) {
            return password_verify($password, $secret);
        }

        // No plaintext fallback.
        return false;
    }

    /**
     * Verify `$apr1$` (Apache MD5) and `$1$` (md5-crypt) by re-calculating hash.
     *
     * @return string|null Calculated full hash (`$apr1$salt$...`) or null if format unsupported
     */
    protected static function md5Crypt(string $password, string $storedHash, string $magic): ?string
    {
        if (!str_starts_with($storedHash, $magic)) {
            return null;
        }

        $rest = substr($storedHash, strlen($magic));
        $salt = strtok($rest, '$');
        if ($salt === false) {
            return null;
        }
        $salt = substr($salt, 0, 8);

        $ctx = $password . $magic . $salt;
        $final = md5($password . $salt . $password, true);

        for ($pl = strlen($password); $pl > 0; $pl -= 16) {
            $ctx .= substr($final, 0, min(16, $pl));
        }
        for ($i = strlen($password); $i > 0; $i >>= 1) {
            $ctx .= ($i & 1) ? "\0" : $password[0];
        }

        $final = md5($ctx, true);

        for ($i = 0; $i < 1000; $i++) {
            $ctx1 = ($i & 1) ? $password : $final;
            if ($i % 3) {
                $ctx1 .= $salt;
            }
            if ($i % 7) {
                $ctx1 .= $password;
            }
            $ctx1 .= ($i & 1) ? $final : $password;
            $final = md5($ctx1, true);
        }

        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $rearranged = ''
            . self::to64((ord($final[0]) << 16) | (ord($final[6]) << 8) | ord($final[12]), 4, $itoa64)
            . self::to64((ord($final[1]) << 16) | (ord($final[7]) << 8) | ord($final[13]), 4, $itoa64)
            . self::to64((ord($final[2]) << 16) | (ord($final[8]) << 8) | ord($final[14]), 4, $itoa64)
            . self::to64((ord($final[3]) << 16) | (ord($final[9]) << 8) | ord($final[15]), 4, $itoa64)
            . self::to64((ord($final[4]) << 16) | (ord($final[10]) << 8) | ord($final[5]), 4, $itoa64)
            . self::to64(ord($final[11]), 2, $itoa64);

        return $magic . $salt . '$' . $rearranged;
    }

    protected static function to64(int $value, int $length, string $itoa64): string
    {
        $out = '';
        while (--$length >= 0) {
            $out .= $itoa64[$value & 0x3f];
            $value >>= 6;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    protected static function parseHtpasswdFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('htpasswd file not readable: ' . $path);
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('failed to read htpasswd file: ' . $path);
        }

        $list = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $user = substr($line, 0, $pos);
            $hash = substr($line, $pos + 1);
            if ($user === '') {
                continue;
            }
            $list[$user] = $hash;
        }

        return $list;
    }

    protected static function extractAuthUserFileFromHtaccess(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('.htaccess not readable: ' . $path);
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('failed to read .htaccess: ' . $path);
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            // Accept: AuthUserFile /abs/path OR AuthUserFile "path with spaces"
            if (preg_match('/^AuthUserFile\s+(.+)\s*$/i', $line, $m) !== 1) {
                continue;
            }
            $value = trim($m[1]);
            $value = trim($value, "\"'");

            return $value;
        }

        return null;
    }

    protected static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        // Unix: /foo, Windows: C:\foo or \\server\share
        return ($path[0] === DIRECTORY_SEPARATOR)
            || (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1)
            || (str_starts_with($path, '\\\\'));
    }
}

