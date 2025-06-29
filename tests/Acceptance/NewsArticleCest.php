<?php

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;
use Tests\Support\Data\ContentTypes;

/**
 * Class NewsArticleCest.
 * Tests for News Article content type permissions.
 */
class NewsArticleCest extends BaseContentTypeCest
{
    /**
     * Get the content type being tested.
     *
     * @return string
     */
    protected function getContentType(): string
    {
        return ContentTypes::NEWS_ARTICLE;
    }

    /**
     * Test create form submission for News Article.
     *
     * @param AcceptanceTester $I
     * @param string $contentType
     */
    protected function testCreateForm(AcceptanceTester $I, $contentType): void
    {
        // Fill in required fields for News Article
        $I->fillField('[name="title[0][value]"]', 'Test title for creating News Article content');
        $I->fillField('[name="body[0][value]"]', 'Test summary for News Article');
        
        // Submit the form
        $I->click('#edit-submit');
        
        // Verify successful submission
        $I->dontSeeResponseCodeIs(403);
        $I->dontSee('Access denied');
        
        // After successful submission, we should be redirected to the created content page
        // Check that we're not on the form page anymore
        $I->dontSee('Create News article');
        
        // Check that the title is visible on the content page
        $I->see('Test title for creating News Article content');
    }
} 