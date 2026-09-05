<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Renderers;

use Unolia\Cli\Console\CliError;
use Unolia\Cli\Console\ExitCode;

final class JsonRenderer
{
    public function __construct(private readonly bool $pretty) {}

    public function document(mixed $data): string
    {
        return self::encode($data, $this->pretty);
    }

    public function line(mixed $data): string
    {
        return self::encode($data, false);
    }

    public static function encode(mixed $data, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($data, $flags);

        if ($json === false) {
            throw new CliError('encoding_failed', 'the response could not be encoded as JSON', ExitCode::RemoteFailure);
        }

        return $json;
    }

    /**
     * @return array<mixed>
     */
    public static function decode(string $json): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
