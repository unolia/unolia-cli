<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * A POST on one record. The body is sent as given, so dry_run: false stays in it.
 */
abstract class MutationRequest extends ResourceRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        int|string $id,
        protected readonly array $payload = [],
        array $filters = [],
    ) {
        parent::__construct($id, $filters);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
