<?php

declare(strict_types=1);

namespace Unolia\Cli\Context;

/**
 * How much context a command needs before it can run.
 */
enum Need
{
    case None;
    case Team;
    case Project;
    case Website;
}
