<?php

namespace Unolia\UnoliaCLI\Mcp;

enum InstallStatus
{
    case Installed;
    case Skipped;
    case Failed;
}
