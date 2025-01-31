<?php

namespace Unolia\UnoliaCLI\Commands;

use Exception;
use LaravelZero\Framework\Commands\Command;
use Saloon\Exceptions\Request\Statuses\UnprocessableEntityException;
use Unolia\UnoliaCLI\Helpers\Config;
use Unolia\UnoliaCLI\Helpers\Helpers;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\CurrentAuthenticated;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\CurrentToken;
use Unolia\UnoliaCLI\Http\Integrations\Unolia\Requests\Login;

use function Laravel\Prompts\form;
use function Laravel\Prompts\text;

class LoginCommand extends Command
{
    protected $signature = 'login {--token= : Authenticate with a token}';

    protected $description = 'Authenticate with the unolia.com';

    public function handle()
    {
        if ($tokenOption = $this->option('token')) {
            $connector = Helpers::getApiConnector($tokenOption);

            try {
                $tokenVerify = $connector->send(new CurrentToken);
                $tokenVerify->throw();

                $userResponse = $connector->send(new CurrentAuthenticated);
                $userResponse->throw();

                $user = $userResponse->json('data');
                Config::set('api.token', $tokenOption);
                $this->info('Successfully logged in with token. User: '.$user['name']);

                return;
            } catch (Exception $e) {
                $this->error('Invalid token: '.$e->getMessage());

                return;
            }
        }

        // Existing email/password flow
        $token = Helpers::getApiToken();
        $connector = Helpers::getApiConnector($token ?: '');

        if (! empty($token)) {
            try {
                $verify = $connector->send(new CurrentToken);
                $verify->throw();

                $this->line('You are already logged in.');

                return;
            } catch (Exception $e) {
                \Laravel\Prompts\info('A token is already set, but it is expired. Please login again.');
            }
        }

        $token_name = 'Token for '.get_current_user().'@'.gethostname();

        $host = parse_url(config('settings.api.auth_url'), PHP_URL_HOST);
        $responses = form()
            ->text('Email',
                required: true,
                hint: 'Please provide your login credentials to '.$host,
                name: 'email'
            )
            ->password('Password', required: true, name: 'password')
            ->submit();

        $connector_auth = Helpers::getAuthConnector();

        try {
            $login = $connector_auth->send(new Login(
                email: $responses['email'],
                password: $responses['password'],
                token_name: $token_name,
            ));

            $login->throw();

        } catch (UnprocessableEntityException $e) {

            $two_fa_error = $e->getResponse()->json('errors.two_factor_code');

            if (! empty($two_fa_error)) {
                $two_factor_code = text('Two factor authentication code');

                $login = $connector_auth->send(new Login(
                    email: $responses['email'],
                    password: $responses['password'],
                    two_factor_code: $two_factor_code,
                    token_name: $token_name,
                ));

                try {
                    $login->throw();
                } catch (Exception $e) {
                    $this->error($e->getMessage());

                    return;
                }
            } else {
                $this->error($e->getMessage());

                return;
            }

        } catch (Exception $e) {
            $this->error($e->getMessage());

            return;
        }

        $token = $login->json('data.token');

        $connector = Helpers::getApiConnector($token);
        try {
            $response = $connector->send(new CurrentToken);
            $response->throw();
        } catch (\Exception $e) {
            $this->error('Failed to authenticate with the API: '.$e->getMessage());

            return;
        }

        $current_token = $response->json('data');

        $response = $connector->send(new CurrentAuthenticated);

        if ($response->failed()) {
            $this->error('Failed to fetch user details: '.($response->json('message') ?: 'Unknown error'));

            return;
        }

        $user = $response->json('data');

        Config::set('api.token', $token);

        $this->info('You are now logged in with '.$current_token['tokenable_type'].': '.$user['name'].'.');
    }
}
