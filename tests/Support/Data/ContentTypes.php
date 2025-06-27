<?php

namespace Tests\Support\Data;

use Tests\Support\Data\Users;

class ContentTypes {

    // Actions.
    public const ACTION_VIEW = 'view';
    public const ACTION_EDIT = 'edit';
    public const ACTION_DELETE = 'delete';
    public const ACTION_CREATE = 'create';

    // Content type machine names.
    public const BASIC_PAGE = 'page';
    public const DOCUMENT = 'document';
    public const EVENT = 'event';
    public const KNOWLEDGE_BASE_PAGE = 'knowledge_base_page';
    public const NEWS_ARTICLE = 'article';
    public const WEBFORM = 'webform';

    // Content types for testing.
    public const TESTING_CONTENT_TYPES = [
        self::BASIC_PAGE => [
            self::ACTION_VIEW => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_EDIT => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_DELETE => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/page',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
        ],
        self::DOCUMENT => [
            self::ACTION_VIEW => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_EDIT => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_DELETE => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/document',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
        ],
        self::EVENT => [
            self::ACTION_VIEW => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_EDIT => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_DELETE => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/event',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
        ],
        self::KNOWLEDGE_BASE_PAGE => [
            self::ACTION_VIEW => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_EDIT => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_DELETE => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/knowledge_base_page',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
        ],
        self::NEWS_ARTICLE => [
            self::ACTION_VIEW => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_EDIT => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_DELETE => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/article',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
        ],
        self::WEBFORM => [
            self::ACTION_VIEW => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_EDIT => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_DELETE => [
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/webform',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                ],
            ],
        ],
    ];
} 