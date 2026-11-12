<?php

return [
    // Page size used by list endpoints when the client does not send per_page.
    'default_per_page' => (int) env('PAGINATION_DEFAULT_PER_PAGE', 25),

    'max_per_page' => (int) env('PAGINATION_MAX_PER_PAGE', 100),
];
