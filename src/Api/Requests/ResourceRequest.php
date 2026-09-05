<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests;

/**
 * A request about one record, identified by an id, a ulid or a domain.
 */
abstract class ResourceRequest extends ApiRequest
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected readonly int|string $id,
        array $filters = [],
    ) {
        parent::__construct($filters);
    }
}
