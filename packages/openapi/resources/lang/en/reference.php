<?php

declare(strict_types=1);

// The API reference's pages. They are built by vellum:build, in the app's
// locale at that moment.
return [
    // The reference's name when neither the config nor the spec gives one.
    'title' => 'API reference',
    'overview' => 'Overview',
    'webhooks' => 'Webhooks',
    'untagged' => 'Other',
    'version' => 'Version :version',

    // The headings of a page, which its table of contents lists too.
    'sections' => [
        'servers' => 'Servers',
        'authentication' => 'Authentication',
        'authorization' => 'Authorization',
        'parameters' => [
            'path' => 'Path parameters',
            'query' => 'Query parameters',
            'header' => 'Header parameters',
            'cookie' => 'Cookie parameters',
        ],
        'payload' => 'Payload',
        'request_body' => 'Request body',
        'responses' => 'Responses',
        'callbacks' => 'Callbacks',
    ],

    'webhook' => 'Webhook',
    'webhook_note' => 'The API sends this request to an address you give it. Your app receives it and answers.',
    'callbacks_note' => 'Requests the API sends back once this one is made, to the address shown.',
    'answer_with' => 'Answer with',
    'deprecated' => 'Deprecated',
    'required' => 'Required',
    'optional' => 'Optional',
    'try_it' => 'Try it',

    // The examples beside a page.
    'examples' => [
        'label' => 'Examples',
        'payload' => 'Payload',
        'request' => 'Request',
        'response' => 'Response',
        'example' => 'Example',
    ],

    // :name is a server variable, shown with its braces.
    'server_variable' => ':name is',
    'or' => 'or',

    // A field of a schema.
    'fields' => [
        'properties' => 'Properties',
        'always' => 'Always',
        'value_in' => 'Value in',
        'repeats' => 'Repeats :name',
        'the_schema_above' => 'the schema above',
        'discriminator' => ':property says which of these it is.',
        'default' => 'default',
        'option' => 'Option :number',
        'format' => 'Format',
        'range' => 'Range',
        'pattern' => 'Pattern',
        'holds' => 'Holds',
        'default_value' => 'Default',
    ],

    // A body sent a piece at a time. :item is one of the items below.
    'stream' => [
        'sent' => 'Sent as a stream, one :item at a time.',
        'each' => 'Each :item:',
        'items' => [
            'event' => 'event',
            'line' => 'line',
            'record' => 'record',
            'item' => 'item',
        ],
    ],

    // What a reader sends to authenticate, when the spec does not say.
    'security' => [
        'bearer' => 'Send a bearer token in the Authorization header.',
        'http' => 'HTTP :scheme in the Authorization header.',
        'api_key' => 'Send an API key in the :in :name.',
        'oauth2' => 'OAuth 2 access token.',
        'open_id_connect' => 'OpenID Connect token.',
        'other' => 'This endpoint requires authentication.',
    ],
];
