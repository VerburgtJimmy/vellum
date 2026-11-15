<?php

declare(strict_types=1);

// The playground that sends a request from an operation's page. The script
// reads these from the page, so a change shows without a build.
return [
    'open' => 'Try :operation',
    'close' => 'Close',
    'send' => 'Send',
    'stop' => 'Stop',
    'sending' => 'Sending.',
    'server' => 'Server',
    'choose' => 'Choose',
    'not_set' => 'Not set',
    'list_example' => 'one, two',

    'sections' => [
        'authorization' => 'Authorization',
        'path' => 'Path',
        'query' => 'Query',
        'header' => 'Headers',
        'body' => 'Body',
    ],

    'auth' => [
        'token' => 'Token',
        'token_or_sign_in' => 'Paste one, or get one below',
        'username' => 'Username',
        'password' => 'Password',
    ],

    'body' => [
        'label' => 'Request body',
        'modes' => 'How to write the body',
        'form' => 'Form',
        'content_type' => 'Content type',
        'add_item' => 'Add item',
        'item' => 'Item :number',
        'remove' => 'Remove',
        'remove_item' => 'Remove item :number',
        'nothing' => 'This request takes nothing. Send it as it is.',
    ],

    'tabs' => [
        'response' => 'Response',
        'copy' => 'Copy',
        'copied' => 'Copied',
    ],

    'response' => [
        'headers' => 'Response headers',
        'headers_count' => 'Response headers (:count)',
        'no_headers' => 'None the browser may show.',
        'no_body' => 'No body.',
        'start_only' => 'Only the start of the response is shown.',
        'not_text' => ':type that is not text, so it is not shown.',
        'a_response' => 'A response',
        'image' => 'The image in the response',
        'download' => 'Download :size',
        'via' => 'Sent through this site',
        'via_whole' => 'Sent through this site, so it arrived all at once.',
        'via_truncated' => 'Sent through this site, so only what arrived in time is shown, all at once.',
    ],

    // A response that arrives a piece at a time. Each item is "one|many".
    'stream' => [
        'live' => 'Live',
        'ended' => 'Ended',
        'stopped' => 'Stopped',
        'cut_off' => 'Cut off',
        'count' => ':count :item',
        'waiting' => 'Waiting for the first :item.',
        'none' => 'No :items came.',
        'items' => [
            'event' => 'event|events',
            'line' => 'line|lines',
            'record' => 'record|records',
            'item' => 'item|items',
        ],
    ],

    'errors' => [
        'path' => 'Fill in the path first.',
        'server' => 'That server is not an address the browser can reach.',
        'file' => 'A file cannot be sent to another origin through this site. Use the command above.',
        'timeout' => 'The request took too long and was stopped.',
        'cors' => 'No response the browser could show. The request went to another origin, which has to allow this page to read its answer (CORS). If it does, the server may be down or the address wrong.',
        'no_response' => 'No response. The server may be down, or the address wrong.',
        'expired' => 'This page has been open too long to send from. Reload it and try again.',
        'throttled' => 'Too many requests from here for now. Wait a minute and try again.',
        'not_sent' => 'This site could not send the request.',
    ],

    // Getting a token from an OAuth provider.
    'oauth' => [
        'modes' => 'How to get a token',
        'sign_in' => 'Sign in',
        'client_credentials' => 'Client credentials',
        'client_id' => 'Client ID',
        'client_secret' => 'Client secret',
        'client_secret_optional' => 'Client secret, if the app has one',
        'scopes' => 'Scopes',
        'get_token' => 'Get token',
        'redirect' => 'Register this address with the provider as a redirect URI:',
        'need_client' => 'Fill in the client ID first.',
        'reading' => 'Reading the provider\'s settings…',
        'unreadable' => 'The provider\'s settings could not be read.',
        'https' => 'Signing in needs the page to be served over HTTPS.',
        'waiting' => 'Waiting for you to sign in…',
        'getting' => 'Getting the token…',
        'got' => 'Got a token.',
        'got_for' => 'Got a token, good for :minutes minutes.',
        'signed_in' => 'Signed in.',
        'signed_in_for' => 'Signed in. The token is good for :minutes minutes.',
        'unreachable' => 'The provider could not be reached from this page. It may not allow it (CORS).',
        'blocked' => 'The browser blocked the sign-in window. Allow pop-ups for this site and try again.',
        'closed' => 'The sign-in window was closed before it finished.',
        'bad_address' => 'The sign-in address in the spec is not a web address.',
    ],

    // The window the provider sends the reader back to.
    'callback' => [
        'title' => 'Signing in',
        'status' => 'Signing in. This window closes by itself.',
        'orphan' => 'Signing in only works from the playground that opened this window. Close it and try again from there.',
    ],

    // Why this site would not send a request for the playground.
    'proxy' => [
        'not_playground' => 'The request is not one the playground sends.',
        'too_large' => 'The body is too large to send through this site.',
        'unknown_server' => 'That address is not one of the servers this API reference names.',
        'no_client' => 'This site cannot send requests: its HTTP client is not installed.',
        'private' => 'That server is not one this site will send a request to: it is not at a public address.',
        'no_response' => 'No response. The server may be down, or the address wrong.',
    ],
];
