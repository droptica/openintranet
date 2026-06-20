<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications_smsapi\Kernel;

use Drupal\smsapi\Services\SmsapiServiceInterface;
use Smsapi\Client\Feature\Mfa\Data\Mfa;
use Smsapi\Client\Feature\Profile\Data\Profile;
use Smsapi\Client\Feature\Sms\Data\Sms;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test double for the smsapi.service so no real SMS / network call is made.
 *
 * The sendSms() method returns a constructed Sms (carrying the configured id)
 * on success or NULL on failure, driven by the flags set on the instance —
 * mirroring the real service which returns NULL (and logs internally) when a
 * send fails.
 */
final class SmsApiServiceDouble implements SmsapiServiceInterface {

  /**
   * The provider message id to set on a returned Sms.
   */
  public string $smsId = 'PROV-1';

  /**
   * Whether sendSms() succeeds (returns an Sms) or fails (returns NULL).
   */
  public bool $shouldSucceed = TRUE;

  /**
   * Captures the arguments of the last sendSms() call.
   *
   * @var array<string, string>
   */
  public array $lastSend = [];

  /**
   * {@inheritdoc}
   */
  public function checkIfConnected(string $api_token = ''): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function sendSms(string $phone_number, string $message, string $sender = ''): ?Sms {
    $this->lastSend = [
      'phone' => $phone_number,
      'message' => $message,
      'sender' => $sender,
    ];
    if (!$this->shouldSucceed) {
      return NULL;
    }
    $sms = new Sms();
    $sms->id = $this->smsId;
    return $sms;
  }

  /**
   * {@inheritdoc}
   */
  public function sendSmsWithTemplate(string $phone_number, string $template_name, array $values, string $sender = ''): ?Sms {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function sendMultipleTemplateSms(array $phone_numbers, string $template_name, array $values, string $sender = ''): ?array {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function sendMultipleSms(array $phone_numbers, string $message, string $sender = ''): ?array {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSenders(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getProfileData(): ?Profile {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function writeCallbackIntoLogs(Request $request): void {
  }

  /**
   * {@inheritdoc}
   */
  public function sendVerificationCode(string $phone_number, string $template_name = '', array $tokens = [], string $sender = ''): ?Mfa {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function checkVerificationCode(string $code, string $phone_number): array {
    return ['status' => FALSE, 'message' => 'not-valid'];
  }

}
