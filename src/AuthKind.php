<?php

declare(strict_types=1);

namespace WScore\Auth;

enum AuthKind
{
    /** ID + password (or equivalent) submitted for this request */
    case Password;
    /** Admin / support path without credential check (gate in-app code) */
    case ForceLogin;
    case OAuth;
    case OneTimeToken;
    /** Established via remember-me cookie (not a fresh credential submission) */
    case Remember;
}
