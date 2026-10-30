<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nexora Product Identity (Single Source of Truth)
    |--------------------------------------------------------------------------
    */
    'brand' => [
        'name' => env('NEXORA_BRAND_NAME', 'Nexora'),
        'tagline' => env('NEXORA_BRAND_TAGLINE', 'Next-generation organizational intelligence'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Service Configuration
    |--------------------------------------------------------------------------
    */
    'ai_service' => [
        'url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
        'token' => env('AI_SERVICE_TOKEN', 'development-token-secret-123456'),
        'timeout' => env('AI_SERVICE_TIMEOUT', 900), // 900s for long OCR tasks
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles Definition
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'super_admin' => 'Super Administrator (Platform Operations)',
        'org_admin' => 'Organization Administrator',
        'dept_admin' => 'Department Administrator',
        'officer' => 'Document Officer',
        'reviewer' => 'Human Reviewer',
        'viewer' => 'Document Viewer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Permissions Map
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'super_admin' => [
            'dashboard.view',
            'organizations.manage',
            'users.manage', // Only Org Admins
            'system.view',
            'security.manage',
            'audit.view', // All orgs
        ],
        'org_admin' => [
            'dashboard.view',
            'documents.view',
            'documents.upload',
            'documents.edit',
            'documents.delete',
            'search.use',
            'chat.use',
            'reviews.submit',
            'reviews.view',
            'users.manage',
            'departments.manage',
            'audit.view', // Own org
        ],
        'dept_admin' => [
            'dashboard.view',
            'documents.view',
            'documents.upload',
            'documents.edit',
            'documents.delete',
            'search.use',
            'chat.use',
            'reviews.submit',
            'reviews.view',
            'users.manage', // Own dept only
        ],
        'officer' => [
            'dashboard.view',
            'documents.view',
            'documents.upload',
            'documents.edit', // Own uploads
            'search.use',
            'chat.use',
            'reviews.submit',
        ],
        'reviewer' => [
            'dashboard.view',
            'documents.view',
            'search.use',
            'chat.use',
            'reviews.submit',
            'reviews.view',
            'reviews.decide', // Never own submissions
        ],
        'viewer' => [
            'dashboard.view',
            'documents.view',
            'search.use',
            'chat.use',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Categories
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'Circular',
        'Official Notice',
        'Project Report',
        'Budget or Financial Document',
        'Policy or Guideline',
        'Meeting Minutes',
        'Administrative Order',
        'Other',
    ],
];
