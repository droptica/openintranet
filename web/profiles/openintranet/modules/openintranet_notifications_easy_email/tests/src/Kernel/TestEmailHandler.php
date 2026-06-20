<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications_easy_email\Kernel;

use Drupal\easy_email\Entity\EasyEmailInterface;
use Drupal\easy_email\Service\EmailHandlerInterface;

/**
 * In-memory Easy Email handler double for channel send-mapping assertions.
 *
 * Driving Easy Email's real send path in a kernel test pulls in the easy_email
 * mail plugin, the token/user/attachment evaluators and the body theme hooks;
 * the channel only needs to delegate create+send and classify the outcome, so
 * this double records the created email and returns a scripted send result.
 * The live Easy Email send is covered by manual QA.
 */
final class TestEmailHandler implements EmailHandlerInterface {

  /**
   * The email created by the channel under test.
   */
  public ?EasyEmailInterface $createdEmail = NULL;

  /**
   * The values passed to createEmail().
   *
   * @var array
   */
  public array $createValues = [];

  /**
   * The email passed to sendEmail().
   */
  public ?EasyEmailInterface $sentEmail = NULL;

  /**
   * Constructs a TestEmailHandler.
   *
   * @param \Drupal\easy_email\Service\EmailHandlerInterface $inner
   *   The real handler, used to mint genuine easy_email entities.
   * @param string $mode
   *   How sendEmail() behaves: 'sent', 'unsent', 'false', 'duplicate' or
   *   'throw'. In 'duplicate' mode sendEmail() short-circuits to FALSE and
   *   duplicateExists() reports TRUE, modelling a suppressed duplicate.
   */
  public function __construct(
    private readonly EmailHandlerInterface $inner,
    private readonly string $mode = 'sent',
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createEmail($values = []) {
    $this->createValues = $values;
    $this->createdEmail = $this->inner->createEmail($values);
    return $this->createdEmail;
  }

  /**
   * {@inheritdoc}
   */
  public function duplicateExists(EasyEmailInterface $email) {
    // Mirrors EmailHandler: a suppressed duplicate is reported as existing.
    return $this->mode === 'duplicate';
  }

  /**
   * {@inheritdoc}
   */
  public function sendEmail(EasyEmailInterface $email, $params = [], $send_duplicate = FALSE, $save_email_entity = FALSE) {
    $this->sentEmail = $email;
    return match ($this->mode) {
      // Mirrors EmailHandler: a sent message stamps the entity's sent time.
      'sent' => [(clone $email)->setSentTime(1)],
      // A returned-but-unsent entity models a backend failure.
      'unsent' => [$email],
      // The handler short-circuits and returns FALSE: a genuine failure with no
      // duplicate ('false'), or a suppressed duplicate ('duplicate').
      'false', 'duplicate' => FALSE,
      'throw' => throw new \RuntimeException('boom'),
      default => [$email],
    };
  }

  /**
   * {@inheritdoc}
   */
  public function preview(EasyEmailInterface $email, $params = []) {
    return [];
  }

}
