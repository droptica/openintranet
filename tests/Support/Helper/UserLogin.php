<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class UserLogin extends Module
{
    public function loginAsAdmin($username = 'admin') {
        $this->loginAs($username);
    }

    public function loginAsAuthenticated($username = 'authenticated') {
        $this->loginAs($username);
    }
    
    public function loginAsContentEditor($username = 'content_editor') {
        $this->loginAs($username);
    }

    public function loginAsContentEditorBasicPage($username = 'content_editor_basic_page') {
        $this->loginAs($username);
    }

    public function loginAsContentEditorDocument($username = 'content_editor_document') {
        $this->loginAs($username);
    }

    public function loginAsContentEditorEvent($username = 'content_editor_event') {
        $this->loginAs($username);
    }

    public function loginAsContentEditorKnowledgeBase($username = 'content_editor_knowledge_base') {
        $this->loginAs($username);
    }

    public function loginAsContentEditorNewsArticle($username = 'content_editor_news_article') {
        $this->loginAs($username);
    }

    public function loginAsContentEditorWebform($username = 'content_editor_webform') {
        $this->loginAs($username);
    }
    
    public function loginAsUserAccountsManager($username = 'user_accounts_manager') {
        $this->loginAs($username);
    }

    public function loginAs($username, $password = '123') {
        /** @var \Codeception\Module\PhpBrowser $I */
        $I = $this->getModule('PhpBrowser');
        $I->amOnPage('/user/login');
        $I->fillField('[name="name"]', $username);
        $I->fillField('[name="pass"]', $password);
        $I->click('#edit-submit');
    }
}
