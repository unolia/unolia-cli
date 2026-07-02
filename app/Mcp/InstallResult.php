<?php

namespace Unolia\UnoliaCLI\Mcp;

final class InstallResult
{
    private function __construct(
        public readonly InstallStatus $status,
        public readonly string $detail,
    ) {}

    public static function installed(string $detail): self
    {
        return new self(InstallStatus::Installed, $detail);
    }

    public static function skipped(string $detail): self
    {
        return new self(InstallStatus::Skipped, $detail);
    }

    public static function failed(string $detail): self
    {
        return new self(InstallStatus::Failed, $detail);
    }
}
