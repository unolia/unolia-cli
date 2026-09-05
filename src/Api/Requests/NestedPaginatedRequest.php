<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests;

use Saloon\PaginationPlugin\Contracts\Paginatable;

/**
 * A list endpoint hanging off one record, such as the deployments of a website.
 */
abstract class NestedPaginatedRequest extends ResourceRequest implements Paginatable {}
