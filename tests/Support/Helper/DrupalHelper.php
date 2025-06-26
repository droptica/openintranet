<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class DrupalHelper extends \Codeception\Module
{
    /**
     * @param $var
     * this will only run if you run codeception with -d
     * Otherwise this is silent
     */
    public function seeVar($var)
    {
        $this->debug($var);
    }

    /**
     * Go to edit page.
     */
    public function goEditPage(\Tests\Support\JsCapableTester $I): void
    {
        $I->waitForElement($editLink = '//li[contains(@class, "nav-item")]/a[contains(@class, "nav-link") and contains(., "Edit")]');
        $editUrl = $I->grabAttributeFrom($editLink, 'href');
        $I->comment("Edit url: " . $editUrl);
        $I->amOnPage($editUrl);
    }
}
