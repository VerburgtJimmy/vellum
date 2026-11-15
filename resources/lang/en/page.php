<?php

declare(strict_types=1);

// The page itself: the line under its title, its actions and its images.
return [
    'breadcrumb' => 'Breadcrumb',

    // :date is the date in the app's locale. The script swaps it for how
    // long ago that was when the change is recent.
    'updated' => 'Updated :date',
    'reading_time' => ':minutes min read',
    'changed' => 'Changed since your last visit',

    'actions' => [
        'copy_markdown' => 'Copy Markdown',
        'copied' => 'Copied',
        'open' => 'Open',
        'open_menu' => 'Open page',
        'open_in_chatgpt' => 'Open in ChatGPT',
        'open_in_claude' => 'Open in Claude',
        'view_markdown' => 'View raw Markdown',
        'edit_on_github' => 'Edit on GitHub',

        // What the assistant is asked, with :url the page's Markdown.
        'chatgpt_prompt' => 'Read :url so I can ask questions about it.',
        'claude_prompt' => 'Read :url',
    ],

    // Read by the script that opens an image larger.
    'zoom' => [
        'open' => 'View larger: :name',
        'image' => 'image',
        'close' => 'Close the larger image',
    ],
];
