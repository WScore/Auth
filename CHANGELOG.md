# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.0.0] - unreleased

### Changed

- **Breaking:** Removed `Auth::{AUTH_NONE,AUTH_OK,AUTH_FAILED}`; use `isLogin()` / `user()` / `login()` return value instead.
- **Breaking:** Removed `Auth::getLoginUser()`; use `Auth::user()`.
- **Breaking:** `isLoginBy()` takes `AuthKind` (not `BY_*` string constants). Session / `getLoginInfo()` use `kind` (`AuthKind`) instead of `loginBy`.
- **Breaking:** Major redesign (v2). The pre-1.0 / beta-era API is not preserved.
- **Breaking:** `UserProviderInterface` is replaced with `findByIdentity` / `getUserId` / `findById` / `getProviderKey()` (`WScore\Auth\Contracts`).
- **Breaking:** Login entry is `Identity` + `Auth::login()`; use `loginWithPassword()` / `forceLogin()` for common cases.
- **Breaking:** Session persistence uses `AuthSessionStoreInterface` + `ArrayAuthSessionStore` (payload includes `userId`, `providerKey`, etc.).
- **Breaking:** PHP **8.2+** required.
- Contracts live under `WScore\Auth\Contracts\` (e.g. `UserProviderInterface`, `RememberMeInterface`, `AuthSessionStoreInterface`).

### Added

- `RememberCookie` moved to `WScore\Auth\RememberAdaptor\RememberCookie`. PDO sample renamed to `RememberMePdoSample` (replaces `RememberAdaptor\RememberMe`).
- `RememberCookie::forBrowser(int $rememberDays)`, `setRememberDays` / `getRememberDays`.
- **Breaking:** `Auth` constructor no longer takes Remember-me arguments; use `setRememberMe(?RememberMeInterface, ?RememberCookie, ?int $lifetimeDays)` only.
- `AuthKind` enum, `Identity` value object.
- `loginWithPassword()`, `user()`.

