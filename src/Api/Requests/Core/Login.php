<?php

declare(strict_types=1);

namespace Unolia\Cli\Api\Requests\Core;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Unolia\Cli\Support\Arr;

/**
 * POST /api/login, unchanged from v1.
 */
final class Login extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $email,
        private readonly string $password,
        private readonly string $tokenName,
        private readonly ?string $twoFactorCode = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return 'login';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return Arr::filled([
            'email' => $this->email,
            'password' => $this->password,
            'token_name' => $this->tokenName,
            'two_factor_code' => $this->twoFactorCode,
        ]);
    }
}
