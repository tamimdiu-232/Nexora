<?php

return [
    // Product identity (used by the layouts, so the name lives in exactly one place).
    'brand' => ['name' => 'Nexora', 'tagline' => 'Next-generation organizational intelligence'],

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
        // Platform operator: runs organizations, system health, security and recovery.
        // Deliberately has NO documents.*, search.use, chat.use or reviews.* permission,
        // so organization content (documents, search results, AI chats) is never reachable.
        'super_admin' => ['dashboard.view', 'organizations.manage', 'users.manage', 'system.view', 'security.manage', 'audit.view'],
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
