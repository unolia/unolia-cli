<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Renderers;

use Symfony\Component\Yaml\Yaml;

final class YamlRenderer
{
    public function document(mixed $data): string
    {
        return rtrim(Yaml::dump($data, 6, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK), "\n");
    }
}
