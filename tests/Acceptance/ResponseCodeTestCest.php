<?php
/**
 * @file
 * Response code test cest for Codeception acceptance tests.
 */
namespace Tests\Acceptance;

use Codeception\Example;
use Tests\Support\AcceptanceTester;
use Tests\Support\Data\Users;
use Tests\Support\Data\ContentTypes;

/**
 * @file
 * Response code test cest.
 */

/**
 * Class ResponseCodeTestCest.
 */
class ResponseCodeTestCest {

  /**
   * Provides a list of users for data-driven tests.
   *
   * @return array
   */
  public function userProvider() {
    $users = [];
    foreach (Users::TESTING_USERS as $role => $userData) {
      $users[] = [$role, $userData];
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
    $role = $example[0];
    $userData = $example[1];
    $username = $userData['username'];

    $I->loginAs($userData['username'], $userData['password']);

    foreach (array_keys(ContentTypes::TESTING_CONTENT_TYPES) as $type) {
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
            $I->dontSeeInCurrentUrl('/user/login');
            $I->dontSee('The website encountered an unexpected error.');
            $I->dontSeeElement('.messages--error');
            print "Testing user: $username content type: $type, node: $url, status: pass\n";
          } catch (\Exception $e) {
            print "Testing user: $username content type: $type, node: $url, status: fail, error: " . $e->getMessage() . "\n";
            throw $e;
          }
        }
      }
    }
  }

}
