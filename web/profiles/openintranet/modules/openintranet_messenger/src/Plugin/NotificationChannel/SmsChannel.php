<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Plugin\NotificationChannel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_messenger\Channel\ChannelPluginBase;
use Drupal\openintranet_messenger\Channel\NotificationChannel;
use Drupal\openintranet_messenger\Exception\ChannelException;
use Drupal\openintranet_messenger\Recipient\RecipientInterface;
use Drupal\smsapi\Services\SmsapiServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * SMS notification channel plugin using SMSAPI.
 */
#[NotificationChannel(
  id: 'sms',
  label: new TranslatableMarkup('SMS'),
  description: new TranslatableMarkup('Send notifications via SMS using SMSAPI service.'),
  weight: 10,
)]
final class SmsChannel extends ChannelPluginBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The SMSAPI service (if available).
   *
   * @var \Drupal\smsapi\Services\SmsapiServiceInterface|null
   */
  protected ?SmsapiServiceInterface $smsapiService;

  /**
   * Constructs an SmsChannel object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\smsapi\Services\SmsapiServiceInterface|null $smsapi_service
   *   The SMSAPI service or NULL if not available.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelInterface $logger,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    ?SmsapiServiceInterface $smsapi_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $config_factory);
    $this->moduleHandler = $module_handler;
    $this->smsapiService = $smsapi_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $smsapi_service = NULL;
    if ($container->has('smsapi.smsapi_service')) {
      $smsapi_service = $container->get('smsapi.smsapi_service');
    }

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.openintranet_messenger'),
      $container->get('config.factory'),
      $container->get('module_handler'),
      $smsapi_service,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    // Check if SMSAPI module is installed and configured.
    if (!$this->moduleHandler->moduleExists('smsapi')) {
      return FALSE;
    }

    if ($this->smsapiService === NULL) {
      return FALSE;
    }

    // Check if SMSAPI has API token configured.
    $smsapi_config = $this->configFactory->get('smsapi.settings');
    $token = $smsapi_config->get('smsapi_api_token');

    return !empty($token);
  }

  /**
   * {@inheritdoc}
   */
  public function send(RecipientInterface $recipient, string $subject, string $message): bool {
    if (!$this->isAvailable()) {
      throw ChannelException::unavailable('sms');
    }

    $phone = $this->getRecipientAddress($recipient);

    if (empty($phone)) {
      throw ChannelException::missingAddress('sms', $recipient->getName());
    }

    // Truncate message if too long.
    $max_length = $this->configFactory->get('openintranet_messenger.settings')->get('sms_max_length') ?? 160;
    $sms_body = mb_substr($message, 0, $max_length);

    $this->logInfo('Messenger/SMS: Sending to @phone. Length: @length', [
      '@phone' => $phone,
      '@length' => mb_strlen($sms_body),
    ]);

    try {
      $result = $this->smsapiService->sendSms($phone, $sms_body);

      if ($result !== NULL) {
        $this->logInfo('Messenger/SMS: SMSAPI response for @phone - ID: @id', [
          '@phone' => $phone,
          '@id' => $result->id ?? 'N/A',
        ]);
        return TRUE;
      }

      throw ChannelException::sendFailed('sms', 'SMSAPI returned NULL response');
    }
    catch (\Exception $e) {
      $this->logError('Messenger/SMS: Failed to send to @phone. Error: @error', [
        '@phone' => $phone,
        '@error' => $e->getMessage(),
      ]);
      throw ChannelException::sendFailed('sms', $e->getMessage(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $config = $this->configFactory->get('openintranet_messenger.settings');

    $form['sms_max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum SMS length'),
      '#description' => $this->t('Maximum characters for SMS messages. Longer messages will be truncated.'),
      '#default_value' => $config->get('sms_max_length') ?? 160,
      '#min' => 50,
      '#max' => 1600,
    ];

    if (!$this->moduleHandler->moduleExists('smsapi')) {
      $form['smsapi_warning'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' . $this->t('The SMSAPI module is not installed. Install it to enable SMS notifications.') . '</div>',
        '#weight' => -100,
      ];
    }
    elseif (!$this->isAvailable()) {
      $form['smsapi_warning'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' . $this->t('SMSAPI is not configured. <a href=":url">Configure it</a> to enable SMS notifications.', [':url' => '/admin/smsapi/configuration']) . '</div>',
        '#weight' => -100,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory->getEditable('openintranet_messenger.settings');
    $config->set('sms_max_length', $form_state->getValue('sms_max_length'));
    $config->save();
  }

}
