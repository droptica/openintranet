<?php

namespace Tests\Acceptance;

use Codeception\Example;
use Tests\Support\AcceptanceTester;
use Tests\Support\Data\Users;
use Tests\Support\Data\ContentTypes;

/**
 * Base class for content type permission tests.
 */
abstract class BaseContentTypeCest
{
    /**
     * Get the content type being tested.
     *
     * @return string
     */
    abstract protected function getContentType(): string;

    /**
     * Provides test data for all users and actions.
     *
     * @return array
     */
    public function userActionProvider()
    {
        $data = [];
        $contentType = $this->getContentType();

        foreach (Users::TESTING_USERS as $role => $userData) {
            foreach ([
                ContentTypes::ACTION_VIEW,
                ContentTypes::ACTION_CREATE,
                ContentTypes::ACTION_EDIT,
                ContentTypes::ACTION_DELETE
            ] as $action) {
                $data[] = [
                    'role' => $role,
                    'userData' => $userData,
                    'contentType' => $contentType,
                    'action' => $action,
                ];
            }
        }

        return $data;
    }

    /**
     * Test view access.
     *
     * @dataprovider userActionProvider
     * @param AcceptanceTester $I
     * @param Example $example
     */
    public function testViewAccess(AcceptanceTester $I, Example $example)
    {
        if ($example['action'] !== ContentTypes::ACTION_VIEW) {
            return;
        }

        $this->testActionAccess($I, $example);
    }

    /**
     * Test create access.
     *
     * @dataprovider userActionProvider
     * @param AcceptanceTester $I
     * @param Example $example
     */
    public function testCreateAccess(AcceptanceTester $I, Example $example)
    {
        if ($example['action'] !== ContentTypes::ACTION_CREATE) {
            return;
        }

        $this->testActionAccess($I, $example);
    }

    /**
     * Test edit access.
     *
     * @dataprovider userActionProvider
     * @param AcceptanceTester $I
     * @param Example $example
     */
    public function testEditAccess(AcceptanceTester $I, Example $example)
    {
        if ($example['action'] !== ContentTypes::ACTION_EDIT) {
            return;
        }

        $this->testActionAccess($I, $example);
    }

    /**
     * Test delete access.
     *
     * @dataprovider userActionProvider
     * @param AcceptanceTester $I
     * @param Example $example
     */
    public function testDeleteAccess(AcceptanceTester $I, Example $example)
    {
        if ($example['action'] !== ContentTypes::ACTION_DELETE) {
            return;
        }

        $this->testActionAccess($I, $example);
    }

    /**
     * Generic method to test action access.
     *
     * @param AcceptanceTester $I
     * @param array $example
     */
    protected function testActionAccess(AcceptanceTester $I, $example)
    {
        $role = $example['role'];
        $userData = $example['userData'];
        $contentType = $example['contentType'];
        $action = $example['action'];

        // Login as the tested user
        $I->loginAs($userData['username'], $userData['password']);
        $I->dontSeeElement('.user-login-form');

        // Get node ID for view/edit/delete actions
        $nodeId = null;
        if (in_array($action, [ContentTypes::ACTION_VIEW, ContentTypes::ACTION_EDIT, ContentTypes::ACTION_DELETE])) {
            $nodeId = $this->getNodeId($I, $contentType);
            if (!$nodeId) {
                $I->markTestSkipped("No existing $contentType node found for testing");
                return;
            }
        }

        // Build URL
        $url = $this->getActionUrl($contentType, $action, $nodeId);

        // Check if user should have access
        $shouldHaveAccess = $this->shouldUserHaveAccess($role, $contentType, $action);

        // Test access
        $I->amOnPage($url);

        if ($shouldHaveAccess) {
            $I->dontSeeResponseCodeIs(403);
            $I->dontSee('Access denied');
            $I->dontSee('Forbidden');

            // For create action, test form submission
            if ($action === ContentTypes::ACTION_CREATE) {
                $this->testCreateForm($I, $contentType);
            }
        } else {
            $I->seeResponseCodeIs(403);
            $I->see('Access denied');
        }

        print "Testing $role -> $contentType -> $action: " . ($shouldHaveAccess ? 'PASS' : 'BLOCKED') . "\n";
    }

    /**
     * Get existing node ID for content type.
     *
     * @param AcceptanceTester $I
     * @param string $contentType
     * @return int|null
     */
    protected function getNodeId(AcceptanceTester $I, $contentType)
    {
        $output = shell_exec("drush sql:query \"SELECT nid FROM node_field_data WHERE type = '$contentType' AND status = 1 LIMIT 1\"");
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (!empty($line) && is_numeric(trim($line))) {
                return (int)trim($line);
            }
        }
        return null;
    }

    /**
     * Get URL for specific action.
     *
     * @param string $contentType
     * @param string $action
     * @param int|null $nodeId
     * @return string
     */
    protected function getActionUrl($contentType, $action, $nodeId = null)
    {
        $urlTemplate = ContentTypes::TESTING_CONTENT_TYPES[$contentType][$action]['url'];

        // Replace placeholders with actual values
        if ($nodeId !== null) {
            $urlTemplate = str_replace('{nodeId}', (string)$nodeId, $urlTemplate);
        }

        return $urlTemplate;
    }

    /**
     * Check if user should have access to specific action.
     *
     * @param string $role
     * @param string $contentType
     * @param string $action
     * @return bool
     */
    protected function shouldUserHaveAccess($role, $contentType, $action)
    {
        $permissions = ContentTypes::TESTING_CONTENT_TYPES[$contentType][$action]['allowed_roles'] ?? [];
        return in_array($role, $permissions);
    }

    /**
     * Test create form submission.
     * Override this method in specific content type Cest classes.
     *
     * @param AcceptanceTester $I
     * @param string $contentType
     */
    abstract protected function testCreateForm(AcceptanceTester $I, $contentType): void;
}
