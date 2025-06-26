<?php
/**
 * @file
 * Response code test cest for Codeception acceptance tests.
 */
namespace Tests\Acceptance;

use Codeception\Example;
use Tests\Support\AcceptanceTester;

/**
 * @file
 * Response code test cest.
 */

/**
 * Class ResponseCodeTestCest.
 */
class ResponseCodeTestCest {
  /**
   * @var array
   * node types machine names
   */
  private $node_types;

  public function __construct() {
    $this->node_types = ['page', 'document', 'event', 'knowledge_base_page', 'article', 'webform'];
  }

  /**
   * @return array
   */
  public function pageProvider() {
    $vars = [];
    foreach ($this->node_types as $type) {
      $output = shell_exec("drush sql:query \"SELECT nid, langcode FROM node_field_data WHERE type = '$type' AND status = 1\"");
      $lines = explode("\n", trim($output));
      foreach ($lines as $line) {
          if (empty($line)) continue;
          $parts = preg_split('/\s+/', trim($line));
          if (count($parts) >= 2 && is_numeric($parts[0])) {
              $vars[] = ['url' => $parts[0], 'langcode' => $parts[1], 'type' => $type];
          }
      }
    }
    return $vars;
}

  /**
   * Response code test.
   *
   * @dataprovider pageProvider
   * @param \Tests\Support\AcceptanceTester $I
   */
  public function responseCodeTest(AcceptanceTester $I, Example $example) {
    $I->wantTo('Response Code Test on page: /node/' . $example['url'] . $example['langcode']);
    $I->amOnPage('/node/' . $example['url']);
    $I->seeResponseCodeIs(200);
    $I->dontSee('The website encountered an unexpected error.');
    $I->dontSeeElement('.messages--error');
  }

}
