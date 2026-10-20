<?php

return [
    // Linux is case-sensitive; the React starter uses lowercase resources/js/pages.
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['tsx'],
    ],
];
