<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class UserLogin extends Module
{
    /**
     * Helper to log in with optional password.
     *
     * @param string $username
     * @param string|null $password
     */
    private function loginWithOptionalPassword($username, $password = null) {
        if ($password === null) {
            $this->loginAs($username);
        } else {
            $this->loginAs($username, $password);
        }
    }

    /**
     * Logs in as admin user.
     *
     * @param string $username
     */
    public function loginAsAdmin($username = 'admin', $password = null) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as authenticated user.
     *
     * @param string $username
     */
    public function loginAsAuthenticated($username = 'authenticated', $password = null) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor.
     *
     * @param string $username
     */
    public function loginAsContentEditor($username = 'content_editor', $password = null) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for basic page.
     *
     * @param string $username
     */
    public function loginAsContentEditorBasicPage($username = 'content_editor_basic_page', $password = null) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for document.
     *
     * @param string $username
     */
    public function loginAsContentEditorDocument($username = 'content_editor_document', $password = null) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for event.
     *
     * @param string $username
     */
    public function loginAsContentEditorEvent($username = 'content_editor_event', $password = null) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for knowledge base.
     *
     * @param string $username
     */
    public function loginAsContentEditorKnowledgeBase($username = 'content_editor_knowledge_base', $password = null) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for news article.
     *
     * @param string $username
     */
    public function loginAsContentEditorNewsArticle($username = 'content_editor_news_article', $password = null) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for webform.
     *
     * @param string $username
     */
    public function loginAsContentEditorWebform($username = 'content_editor_webform', $password = null) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as user accounts manager.
     *
     * @param string $username
     */
    public function loginAsUserAccountsManager($username = 'user_accounts_manager', $password = null) {
        $this->loginWithOptionalPassword($username, $password);
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
        $I->see($username);
    }

    /** 
     * Logs out the current user.
     */
    public function logout() {
        /** @var \Codeception\Module\PhpBrowser $I */
        $I = $this->getModule('PhpBrowser');
        $I->amOnPage('/user/logout');
        $I->click('#edit-submit');
        $I->seeInCurrentUrl('/user/login');
    }

}
