<?php

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;
use Tests\Support\Data\ContentTypes;

/**
 * Class EventCest.
 * Tests for Event content type permissions.
 */
class EventCest extends BaseContentTypeCest
{
    /**
     * Get the content type being tested.
     *
     * @return string
     */
    protected function getContentType(): string
    {
        return ContentTypes::EVENT;
    }

    /**
     * Test create form submission for Event.
     *
     * @param AcceptanceTester $I
     * @param string $contentType
     */
    protected function testCreateForm(AcceptanceTester $I, $contentType): void
    {
        // Fill in required fields for Event
        $I->fillField('[name="title[0][value]"]', 'Test title for creating Event content');
        $I->fillField('[name="body[0][value]"]', 'Test summary for Event');
        
        // Submit the form
        $I->click('#edit-submit');
        
        // Verify successful submission
        $I->dontSeeResponseCodeIs(403);
        $I->dontSee('Access denied');
        
        // After successful submission, we should be redirected to the created content page
        // Check that we're not on the form page anymore
        $I->dontSee('Create Event');
        
        // Check that the title is visible on the content page
        $I->see('Test title for creating Event content');
    }
} 