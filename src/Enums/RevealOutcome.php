<?php

namespace Arzcode\SharedSecrets\Enums;

enum RevealOutcome
{
    case Revealed;
    case PassphraseRequired;
    case PassphraseInvalid;
    case Throttled;
    case LockedOut;
    case Unavailable;
}
