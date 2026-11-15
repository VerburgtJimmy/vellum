<?php

declare(strict_types=1);

// The pages shown when a page is missing or cannot be built.
return [
    'back' => 'Back to docs',

    'not_found' => [
        'title' => 'Page not found',
        'text' => 'The page you are looking for does not exist or may have been moved.',
    ],

    'not_rendered' => [
        'title' => 'This page could not be rendered',
        'text' => 'Something in the Markdown for this page stopped Vellum from building it. The rest of the documentation is unaffected.',
        'detail' => 'What went wrong',
        'debug' => 'This detail is shown because :setting is on. It is hidden in production.',
    ],

    // A static export's page at an old version's address.
    'redirect' => [
        'title' => 'Redirecting',
        'link' => 'Continue to the latest version',
    ],
];
