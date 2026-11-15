<?php

declare(strict_types=1);

// The search box and its results.
return [
    'search' => 'Search',
    'trigger' => 'Search...',
    'dialog' => 'Search documentation',
    'close' => 'Close search',
    'results' => 'Search results',
    'loading' => 'Loading…',

    // :query is what the reader typed, :keys the keys to press.
    'no_results_for' => 'No results for “:query”',
    'hint_move' => ':keys to move',
    'hint_open' => ':keys to open',
    'hint_close' => ':keys to close',

    // Read by the script. The status is spoken to screen readers.
    'recent' => 'Recent',
    'status' => [
        'type' => 'Type to search',
        'recent' => 'Recent results',
        'loading' => 'Loading search index',
        'ready' => 'Search ready',
        'unavailable' => 'Search unavailable',
        'none' => 'No results',
        'count' => ':count result|:count results',
    ],
];
