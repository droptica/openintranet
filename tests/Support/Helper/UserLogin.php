<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module;
use Tests\Support\Data\Users;

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
    public function loginAsAdmin(
        $username = Users::TESTING_USERS[Users::ROLE_ADMINISTRATOR]['username'],
        $password = Users::DEFAULT_PASSWORD
    ) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as authenticated user.
     *
     * @param string $username
     */
    public function loginAsAuthenticated(
        $username = Users::TESTING_USERS[Users::ROLE_AUTHENTICATED]['username'],
        $password = Users::DEFAULT_PASSWORD
    ) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor.
     *
     * @param string $username
     */
    public function loginAsContentEditor(
        $username = Users::TESTING_USERS[Users::ROLE_CONTENT_EDITOR]['username'],
        $password = Users::DEFAULT_PASSWORD
    ) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for basic page.
     *
     * @param string $username
     */
    public function loginAsContentEditorBasicPage(
        $username = Users::TESTING_USERS[Users::ROLE_CONTENT_EDITOR_BASIC_PAGE]['username'],
        $password = Users::DEFAULT_PASSWORD
    ) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for document.
     *
     * @param string $username
     */
    public function loginAsContentEditorDocument(
        $username = Users::TESTING_USERS[Users::ROLE_CONTENT_EDITOR_DOCUMENT]['username'],
        $password = Users::DEFAULT_PASSWORD
    ) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for event.
     *
     * @param string $username
     */
    public function loginAsContentEditorEvent(
        $username = Users::TESTING_USERS[Users::ROLE_CONTENT_EDITOR_EVENT]['username'],
        $password = Users::DEFAULT_PASSWORD
    ) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for knowledge base.
     *
     * @param string $username
     */
    public function loginAsContentEditorKnowledgeBase(
        $username = Users::TESTING_USERS[Users::ROLE_CONTENT_EDITOR_KNOWLEDGE_BASE]['username'],
        $password = Users::DEFAULT_PASSWORD
    ) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for news article.
     *
     * @param string $username
     */
    public function loginAsContentEditorNewsArticle(
        $username = Users::TESTING_USERS[Users::ROLE_CONTENT_EDITOR_NEWS_ARTICLE]['username'],
        $password = Users::DEFAULT_PASSWORD
    ) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as content editor for webform.
     *
     * @param string $username
     */
    public function loginAsContentEditorWebform(
        $username = Users::TESTING_USERS[Users::ROLE_CONTENT_EDITOR_WEBFORM]['username'],
        $password = Users::DEFAULT_PASSWORD
    ) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as user accounts manager.
     *
     * @param string $username
     */
    public function loginAsUserAccountsManager(
        $username = Users::TESTING_USERS[Users::ROLE_USER_ACCOUNTS_MANAGER]['username'],
        $password = Users::DEFAULT_PASSWORD
    ) {
        $this->loginWithOptionalPassword($username, $password);
    }

    /**
     * Logs in as any user by username and password.
     *
     * @param string $username
     * @param string $password
     */
    public function loginAs(
        $username,
        $password = Users::DEFAULT_PASSWORD
    ) {
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
