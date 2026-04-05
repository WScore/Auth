<?php

declare(strict_types=1);

namespace WScore\Auth;

enum AuthKind
{
    case Password;
    case ForceLogin;
    case OAuth;
    case OneTimeToken;
}
