<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests;

use Saloon\PaginationPlugin\Contracts\Paginatable;

/**
 * A list endpoint. Paginatable so --paginate can walk every page.
 */
abstract class PaginatedRequest extends ApiRequest implements Paginatable {}
