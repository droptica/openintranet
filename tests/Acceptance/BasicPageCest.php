<?php

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;
use Tests\Support\Data\ContentTypes;

/**
 * Class BasicPageCest.
 * Tests for Basic Page content type permissions.
 */
class BasicPageCest extends BaseContentTypeCest
{
    /**
     * Get the content type being tested.
     *
     * @return string
     */
    protected function getContentType(): string
    {
        return ContentTypes::BASIC_PAGE;
    }

    /**
     * Test create form submission for Basic Page.
     *
     * @param AcceptanceTester $I
     * @param string $contentType
     */
    protected function testCreateForm(AcceptanceTester $I, $contentType): void
    {
        // Fill in required fields for Basic Page
        $I->fillField('[name="title[0][value]"]', 'Test title for creating Basic Page content');
        $I->fillField('[name="body[0][value]"]', 'Test content for Basic Page');
        
        // Submit the form
        $I->click('#edit-submit');
        
        // Verify successful submission
        $I->dontSeeResponseCodeIs(403);
        $I->dontSee('Access denied');
        
        // After successful submission, we should be redirected to the created content page
        // Check that we're not on the form page anymore
        $I->dontSee('Create Basic page');
        
        // Check that the title is visible on the content page
        $I->see('Test title for creating Basic Page content');
    }
} 