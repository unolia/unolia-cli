<?php

return [
    'api' => [

        /*
        |--------------------------------------------------------------------------
        | Unolia API Token
        |--------------------------------------------------------------------------
        |
        | You can use a user token or a team token.
        | Tokens are created on the Unolia dashboard.
        | https://app.unolia.com/api
        |
        */

        'token' => env('UNOLIA_API_TOKEN'),

        /*
        |--------------------------------------------------------------------------
        | Unolia API URL
        |--------------------------------------------------------------------------
        |
        | The base URL for the Unolia API.
        |
        */
        'url' => env('UNOLIA_API_URL', 'https://app.unolia.com/api/v1/'),
        'auth_url' => env('UNOLIA_AUTH_URL', 'https://app.unolia.com/api/'),
    ],

    'mcp' => [

        /*
        |--------------------------------------------------------------------------
        | Unolia MCP Server URL
        |--------------------------------------------------------------------------
        |
        | The team MCP connector endpoint (Streamable HTTP). Clients sign in
        | through the browser via OAuth on first connect - no token needed.
        |
        */
        'url' => env('UNOLIA_MCP_URL', 'https://app.unolia.com/mcp/team'),
    ],
];
