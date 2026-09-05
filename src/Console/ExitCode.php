<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

enum ExitCode: int
{
    case Ok = 0;
    case RemoteFailure = 1;
    case Usage = 2;
    case Auth = 3;
    case NotFound = 4;
    case Forbidden = 5;
    case Timeout = 6;
    case AwaitingInput = 7;
    case Interrupted = 130;
}
