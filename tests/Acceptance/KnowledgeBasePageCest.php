<?php

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;
use Tests\Support\Data\ContentTypes;

/**
 * Class KnowledgeBasePageCest.
 * Tests for Knowledge Base Page content type permissions.
 */
class KnowledgeBasePageCest extends BaseContentTypeCest
{
    /**
     * Get the content type being tested.
     *
     * @return string
     */
    protected function getContentType(): string
    {
        return ContentTypes::KNOWLEDGE_BASE_PAGE;
    }

    /**
     * Test create form submission for Knowledge Base Page.
     *
     * @param AcceptanceTester $I
     * @param string $contentType
     */
    protected function testCreateForm(AcceptanceTester $I, $contentType): void
    {
        // Fill in required fields for Knowledge Base Page
        $I->fillField('[name="title[0][value]"]', 'Test title for creating Knowledge Base Page content');
        $I->fillField('[name="body[0][value]"]', 'Test summary for Knowledge Base Page');
        
        // Submit the form
        $I->click('#edit-submit');
        
        // Verify successful submission
        $I->dontSeeResponseCodeIs(403);
        $I->dontSee('Access denied');
        
        // After successful submission, we should be redirected to the created content page
        // Check that we're not on the form page anymore
        $I->dontSee('Create Knowledge Base Page');
        
        // Check that the title is visible on the content page
        $I->see('Test title for creating Knowledge Base Page content');
    }
} 