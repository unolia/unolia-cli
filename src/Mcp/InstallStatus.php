<?php

declare(strict_types=1);

namespace Unolia\Cli\Mcp;

enum InstallStatus: string
{
    case Installed = 'installed';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
