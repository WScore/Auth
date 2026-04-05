# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.0.0] - unreleased

### Changed

- **Breaking:** Major redesign (v2). The pre-1.0 / beta-era API is not preserved.
- **Breaking:** `UserProviderInterface` is replaced with `findByIdentity` / `getUserId` / `findById` / `getProviderKey()` (`WScore\Auth\Contracts`).
- **Breaking:** Login entry is `Identity` + `Auth::login()`; use `loginWithPassword()` / `forceLogin()` for common cases.
- **Breaking:** Session persistence uses `AuthSessionStoreInterface` + `ArrayAuthSessionStore` (payload includes `userId`, `providerKey`, etc.).
- **Breaking:** PHP **8.2+** required.
- Contracts live under `WScore\Auth\Contracts\` (e.g. `UserProviderInterface`, `RememberMeInterface`, `AuthSessionStoreInterface`).

### Added

- `AuthKind` enum, `Identity` value object.
- `loginWithPassword()`, `user()` (and `getLoginUser()` as alias).

