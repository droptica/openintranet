<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class UserLogin extends Module
{
    /**
     * Logs in as admin user.
     *
     * @param string $username
     */
    public function loginAsAdmin($username = 'admin') {
        $this->loginAs($username);
    }

    /**
     * Logs in as authenticated user.
     *
     * @param string $username
     */
    public function loginAsAuthenticated($username = 'authenticated') {
        $this->loginAs($username);
    }
    
    /**
     * Logs in as content editor.
     *
     * @param string $username
     */
    public function loginAsContentEditor($username = 'content_editor') {
        $this->loginAs($username);
    }

    /**
     * Logs in as content editor for basic page.
     *
     * @param string $username
     */
    public function loginAsContentEditorBasicPage($username = 'content_editor_basic_page') {
        $this->loginAs($username);
    }

    /**
     * Logs in as content editor for document.
     *
     * @param string $username
     */
    public function loginAsContentEditorDocument($username = 'content_editor_document') {
        $this->loginAs($username);
    }

    /**
     * Logs in as content editor for event.
     *
     * @param string $username
     */
    public function loginAsContentEditorEvent($username = 'content_editor_event') {
        $this->loginAs($username);
    }

    /**
     * Logs in as content editor for knowledge base.
     *
     * @param string $username
     */
    public function loginAsContentEditorKnowledgeBase($username = 'content_editor_knowledge_base') {
        $this->loginAs($username);
    }

    /**
     * Logs in as content editor for news article.
     *
     * @param string $username
     */
    public function loginAsContentEditorNewsArticle($username = 'content_editor_news_article') {
        $this->loginAs($username);
    }

    /**
     * Logs in as content editor for webform.
     *
     * @param string $username
     */
    public function loginAsContentEditorWebform($username = 'content_editor_webform') {
        $this->loginAs($username);
    }
    
    /**
     * Logs in as user accounts manager.
     *
     * @param string $username
     */
    public function loginAsUserAccountsManager($username = 'user_accounts_manager') {
        $this->loginAs($username);
    }

    /**
     * Logs in as any user by username and password.
     *
     * @param string $username
     * @param string $password
     */
    public function loginAs($username, $password = '123') {
        /** @var \Codeception\Module\PhpBrowser $I */
        $I = $this->getModule('PhpBrowser');
        $I->amOnPage('/user/login');
        $I->fillField('[name="name"]', $username);
        $I->fillField('[name="pass"]', $password);
        $I->click('#edit-submit');
    }
}
