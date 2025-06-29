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

    /**
     * Get URL template for specific action.
     *
     * @param string $action
     * @return string
     */
    public static function getActionUrlTemplate($action): string
    {
        switch ($action) {
            case self::ACTION_CREATE:
                return '/node/add/{contentType}';
            case self::ACTION_VIEW:
                return '/node/{nodeId}';
            case self::ACTION_EDIT:
                return '/node/{nodeId}/edit';
            case self::ACTION_DELETE:
                return '/node/{nodeId}/delete';
            default:
                throw new \InvalidArgumentException("Unknown action: $action");
        }
    }

    /**
     * Get create URL for content type.
     *
     * @param string $contentType
     * @return string
     */
    public static function getCreateUrl($contentType): string
    {
        return str_replace('{contentType}', $contentType, self::getActionUrlTemplate(self::ACTION_CREATE));
    }

    /**
     * Get view URL template.
     *
     * @return string
     */
    public static function getViewUrlTemplate(): string
    {
        return self::getActionUrlTemplate(self::ACTION_VIEW);
    }

    /**
     * Get edit URL template.
     *
     * @return string
     */
    public static function getEditUrlTemplate(): string
    {
        return self::getActionUrlTemplate(self::ACTION_EDIT);
    }

    /**
     * Get delete URL template.
     *
     * @return string
     */
    public static function getDeleteUrlTemplate(): string
    {
        return self::getActionUrlTemplate(self::ACTION_DELETE);
    }

    // Content types for testing.
    public const TESTING_CONTENT_TYPES = [
        self::BASIC_PAGE => [
            self::ACTION_VIEW => [
                'url' => '/node/{nodeId}',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_AUTHENTICATED,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_BASIC_PAGE,
                    Users::ROLE_CONTENT_EDITOR_DOCUMENT,
                    Users::ROLE_CONTENT_EDITOR_EVENT,
                    Users::ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE,
                    Users::ROLE_CONTENT_EDITOR_NEWS_ARTICLE,
                    Users::ROLE_CONTENT_EDITOR_WEBFORM,
                    Users::ROLE_USER_ACCOUNTS_MANAGER,
                ],
            ],
            self::ACTION_EDIT => [
                'url' => '/node/{nodeId}/edit',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_BASIC_PAGE,
                ],
            ],
            self::ACTION_DELETE => [
                'url' => '/node/{nodeId}/delete',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_BASIC_PAGE,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/page',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_BASIC_PAGE,
                ],
            ],
        ],
        self::DOCUMENT => [
            self::ACTION_VIEW => [
                'url' => '/node/{nodeId}',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_AUTHENTICATED,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_BASIC_PAGE,
                    Users::ROLE_CONTENT_EDITOR_DOCUMENT,
                    Users::ROLE_CONTENT_EDITOR_EVENT,
                    Users::ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE,
                    Users::ROLE_CONTENT_EDITOR_NEWS_ARTICLE,
                    Users::ROLE_CONTENT_EDITOR_WEBFORM,
                    Users::ROLE_USER_ACCOUNTS_MANAGER,
                ],
            ],
            self::ACTION_EDIT => [
                'url' => '/node/{nodeId}/edit',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_DOCUMENT,
                ],
            ],
            self::ACTION_DELETE => [
                'url' => '/node/{nodeId}/delete',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_DOCUMENT,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/document',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_DOCUMENT,
                ],
            ],
        ],
        self::EVENT => [
            self::ACTION_VIEW => [
                'url' => '/node/{nodeId}',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_AUTHENTICATED,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_BASIC_PAGE,
                    Users::ROLE_CONTENT_EDITOR_DOCUMENT,
                    Users::ROLE_CONTENT_EDITOR_EVENT,
                    Users::ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE,
                    Users::ROLE_CONTENT_EDITOR_NEWS_ARTICLE,
                    Users::ROLE_CONTENT_EDITOR_WEBFORM,
                    Users::ROLE_USER_ACCOUNTS_MANAGER,
                ],
            ],
            self::ACTION_EDIT => [
                'url' => '/node/{nodeId}/edit',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_EVENT,
                ],
            ],
            self::ACTION_DELETE => [
                'url' => '/node/{nodeId}/delete',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_EVENT,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/event',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_EVENT,
                ],
            ],
        ],
        self::KNOWLEDGE_BASE_PAGE => [
            self::ACTION_VIEW => [
                'url' => '/node/{nodeId}',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_AUTHENTICATED,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_BASIC_PAGE,
                    Users::ROLE_CONTENT_EDITOR_DOCUMENT,
                    Users::ROLE_CONTENT_EDITOR_EVENT,
                    Users::ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE,
                    Users::ROLE_CONTENT_EDITOR_NEWS_ARTICLE,
                    Users::ROLE_CONTENT_EDITOR_WEBFORM,
                    Users::ROLE_USER_ACCOUNTS_MANAGER,
                ],
            ],
            self::ACTION_EDIT => [
                'url' => '/node/{nodeId}/edit',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE,
                ],
            ],
            self::ACTION_DELETE => [
                'url' => '/node/{nodeId}/delete',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/knowledge_base_page',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE,
                ],
            ],
        ],
        self::NEWS_ARTICLE => [
            self::ACTION_VIEW => [
                'url' => '/node/{nodeId}',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_AUTHENTICATED,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_BASIC_PAGE,
                    Users::ROLE_CONTENT_EDITOR_DOCUMENT,
                    Users::ROLE_CONTENT_EDITOR_EVENT,
                    Users::ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE,
                    Users::ROLE_CONTENT_EDITOR_NEWS_ARTICLE,
                    Users::ROLE_CONTENT_EDITOR_WEBFORM,
                    Users::ROLE_USER_ACCOUNTS_MANAGER,
                ],
            ],
            self::ACTION_EDIT => [
                'url' => '/node/{nodeId}/edit',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_NEWS_ARTICLE,
                ],
            ],
            self::ACTION_DELETE => [
                'url' => '/node/{nodeId}/delete',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_NEWS_ARTICLE,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/article',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_NEWS_ARTICLE,
                ],
            ],
        ],
        self::WEBFORM => [
            self::ACTION_VIEW => [
                'url' => '/node/{nodeId}',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_AUTHENTICATED,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_BASIC_PAGE,
                    Users::ROLE_CONTENT_EDITOR_DOCUMENT,
                    Users::ROLE_CONTENT_EDITOR_EVENT,
                    Users::ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE,
                    Users::ROLE_CONTENT_EDITOR_NEWS_ARTICLE,
                    Users::ROLE_CONTENT_EDITOR_WEBFORM,
                    Users::ROLE_USER_ACCOUNTS_MANAGER,
                ],
            ],
            self::ACTION_EDIT => [
                'url' => '/node/{nodeId}/edit',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_WEBFORM,
                ],
            ],
            self::ACTION_DELETE => [
                'url' => '/node/{nodeId}/delete',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_WEBFORM,
                ],
            ],
            self::ACTION_CREATE => [
                'url' => '/node/add/webform',
                'allowed_roles' => [
                    Users::ROLE_ADMINISTRATOR,
                    Users::ROLE_CONTENT_EDITOR,
                    Users::ROLE_CONTENT_EDITOR_WEBFORM,
                ],
            ],
        ],
    ];
} 