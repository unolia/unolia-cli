<?php

namespace Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class Login extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $two_factor_code = null,
        public readonly ?string $token_name = null,
    ) {
    }

    public function defaultBody(): array
    {
        return array_filter([
            'email' => $this->email,
            'password' => $this->password,
            'two_factor_code' => $this->two_factor_code,
            'token_name' => $this->token_name,
        ]);
    }

    public function resolveEndpoint(): string
    {
        return 'login';
    }
}
