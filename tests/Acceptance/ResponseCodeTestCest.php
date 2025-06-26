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
   * Node types machine names.
   */
  private $node_types;

  /**
   * @var array
   * Usernames to test.
   */
  private $users;

  /**
   * ResponseCodeTestCest constructor.
   * Initializes node types and users for smoke tests.
   */
  public function __construct() {
    $this->node_types = ['page', 'document', 'event', 'knowledge_base_page', 'article', 'webform'];
    $this->users = [
      'admin',
      'authenticated',
      'content_editor',
      'content_editor_basic_page',
      'content_editor_document',
      'content_editor_event',
      'content_editor_knowledge_base',
      'content_editor_news_article',
      'content_editor_webform',
      'user_accounts_manager',
    ];
  }

  /**
   * Provides a list of users for data-driven tests.
   *
   * @return array
   */
  public function userProvider() {
    $users = [];
    foreach ($this->users as $user) {
      $users[] = [$user];
    }
    return $users;
  }

  /**
   * Response code test for all users and all content types.
   *
   * @dataprovider userProvider
   * @param \Tests\Support\AcceptanceTester $I
   * @param \Codeception\Example $example
   * @throws \Exception
   */
  public function responseCodeTestForUser(AcceptanceTester $I, Example $example) {
    $username = $example[0];
    $I->loginAs($username);
    foreach ($this->node_types as $type) {
      $output = shell_exec("drush sql:query \"SELECT nid, langcode FROM node_field_data WHERE type = '$type' AND status = 1\"");
      $lines = explode("\n", trim($output));
      foreach ($lines as $line) {
        if (empty($line)) continue;
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 2 && is_numeric($parts[0])) {
          $url = '/node/' . $parts[0];
          try {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(200);
            $I->dontSee('The website encountered an unexpected error.');
            $I->dontSeeElement('.messages--error');
            print "Testing user: $username, type: $type, node: $url, status: pass\n";
          } catch (\Exception $e) {
            print "Testing user: $username, type: $type, node: $url, status: fail, error: " . $e->getMessage() . "\n";
            throw $e;
          }
        }
      }
    }
  }

}
