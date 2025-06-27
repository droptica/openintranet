<?php

namespace Tests\Support\Data;

class Users {
    // Passwords
    public const DEFAULT_PASSWORD = '123';

    // Roles
    public const ROLE_ADMINISTRATOR = 'administrator';
    public const ROLE_AUTHENTICATED = 'authenticated';
    public const ROLE_CONTENT_EDITOR = 'content_editor';
    public const ROLE_CONTENT_EDITOR_BASIC_PAGE = 'content_editor_basic_page';
    public const ROLE_CONTENT_EDITOR_DOCUMENT = 'content_editor_document';
    public const ROLE_CONTENT_EDITOR_EVENT = 'content_editor_event';
    public const ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE = 'content_editor_knowledge_base';
    public const ROLE_CONTENT_EDITOR_NEWS_ARTICLE = 'content_editor_news_article';
    public const ROLE_CONTENT_EDITOR_WEBFORM = 'content_editor_webform';
    public const ROLE_USER_ACCOUNTS_MANAGER = 'user_accounts_manager';


    // User data arrays
    public const TESTING_USERS = [
        self::ROLE_ADMINISTRATOR => [
            'username' => 'admin',
            'password' => self::DEFAULT_PASSWORD,
        ],
        self::ROLE_AUTHENTICATED => [
            'username' => 'authenticated',
            'password' => self::DEFAULT_PASSWORD,
        ],
        self::ROLE_CONTENT_EDITOR => [
            'username' => 'content_editor',
            'password' => self::DEFAULT_PASSWORD,
        ],
        self::ROLE_CONTENT_EDITOR_BASIC_PAGE => [
            'username' => 'content_editor_basic_page',
            'password' => self::DEFAULT_PASSWORD,
        ],
        self::ROLE_CONTENT_EDITOR_DOCUMENT => [
            'username' => 'content_editor_document',
            'password' => self::DEFAULT_PASSWORD,
        ],
        self::ROLE_CONTENT_EDITOR_EVENT => [
            'username' => 'content_editor_event',
            'password' => self::DEFAULT_PASSWORD,
        ],
        self::ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE => [
            'username' => 'content_editor_knowledge_base',
            'password' => self::DEFAULT_PASSWORD,
        ],
        self::ROLE_CONTENT_EDITOR_NEWS_ARTICLE => [
            'username' => 'content_editor_news_article',
            'password' => self::DEFAULT_PASSWORD,
        ],
        self::ROLE_CONTENT_EDITOR_WEBFORM => [
            'username' => 'content_editor_webform',
            'password' => self::DEFAULT_PASSWORD,
        ],
        self::ROLE_USER_ACCOUNTS_MANAGER => [
            'username' => 'user_accounts_manager',
            'password' => self::DEFAULT_PASSWORD,
        ],
    ];
} 