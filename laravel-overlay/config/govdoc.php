<?php

return [
    'ai_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
    'ai_token' => env('AI_SERVICE_TOKEN', ''),
    'ai_timeout' => (int) env('AI_SERVICE_TIMEOUT', 180),
    'max_upload_kb' => (int) env('GOVDOC_MAX_UPLOAD_KB', 20480),
    'seed_process' => (bool) env('GOVDOC_SEED_PROCESS', false),

    // Prototype categories - not an official government classification standard.
    'categories' => [
        'Circular', 'Official Notice', 'Project Report', 'Budget or Financial Document',
        'Policy or Guideline', 'Meeting Minutes', 'Administrative Order', 'Other',
    ],

    'roles' => [
        'super_admin' => 'Super Admin',
        'org_admin' => 'Organization Admin',
        'dept_admin' => 'Department Admin',
        'officer' => 'Officer',
        'reviewer' => 'Reviewer',
        'viewer' => 'Viewer',
    ],

    // Role-permission matrix (enforced on the server by middleware + policies).
    'permissions' => [
        'super_admin' => ['*'],
        'org_admin' => [
            'dashboard.view', 'documents.view', 'documents.upload', 'documents.edit', 'documents.delete',
            'documents.reprocess', 'search.use', 'chat.use', 'reviews.view', 'reviews.submit',
            'users.manage', 'departments.manage', 'audit.view',
        ],
        'dept_admin' => [
            'dashboard.view', 'documents.view', 'documents.upload', 'documents.edit', 'documents.delete',
            'documents.reprocess', 'search.use', 'chat.use', 'reviews.view', 'reviews.submit', 'users.manage',
        ],
        'officer' => [
            'dashboard.view', 'documents.view', 'documents.upload', 'documents.edit', 'documents.reprocess',
            'search.use', 'chat.use', 'reviews.submit',
        ],
        'reviewer' => [
            'dashboard.view', 'documents.view', 'search.use', 'chat.use', 'reviews.view', 'reviews.submit',
            'reviews.decide',
        ],
        'viewer' => ['dashboard.view', 'documents.view', 'search.use', 'chat.use'],
    ],
];
