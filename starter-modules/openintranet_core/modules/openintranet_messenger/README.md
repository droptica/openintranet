# Open Intranet Messenger

Multi-channel notification system for Drupal users and external contacts (deskless workers).

## Features

- **Multi-channel notifications**: Email, SMS (via SMSAPI), with extensible plugin system
- **External contacts**: Manage contacts without Drupal accounts
- **User integration**: Send to Drupal users by role or selection
- **Notification logging**: Full history of sent notifications
- **Flexible recipient selection**: All contacts, by department, by role, or individual selection

## Requirements

- Drupal 10.2+ or 11.x
- PHP 8.1+
- Taxonomy module (core)

### Optional

- [SMSAPI](https://www.drupal.org/project/smsapi) module for SMS notifications
- [Symfony Mailer](https://www.drupal.org/project/symfony_mailer) for advanced email features

## Installation

```bash
composer require drupal/openintranet_messenger
drush en openintranet_messenger
```

## Configuration

1. Go to **Configuration > System > Messenger**
2. Configure enabled channels and default settings
3. If using SMS, configure SMSAPI module first at `/admin/smsapi/configuration`

## Usage

### Managing Contacts

1. Navigate to **Configuration > System > Messenger > Contacts**
2. Add external contacts with name, email, phone, and department
3. Set preferred notification channel for each contact

### Sending Notifications

1. Navigate to **Configuration > System > Messenger > Send Notification**
2. Select recipients (contacts and/or users)
3. Choose notification channel
4. Enter subject and message
5. Send!

### Viewing Logs

All sent notifications are logged at **Configuration > System > Messenger > Notification Log**

## Permissions

| Permission | Description |
|------------|-------------|
| Administer Messenger | Full access to configuration |
| Manage Messenger contacts | Create, edit, delete contacts |
| View Messenger contacts | View contact list |
| Send notifications | Send notifications to recipients |
| View notification log | View sent notification history |

## Extending

### Creating a Custom Channel Plugin

```php
<?php

namespace Drupal\my_module\Plugin\NotificationChannel;

use Drupal\openintranet_messenger\Channel\ChannelPluginBase;
use Drupal\openintranet_messenger\Channel\NotificationChannel;
use Drupal\openintranet_messenger\Recipient\RecipientInterface;

#[NotificationChannel(
  id: 'my_channel',
  label: new TranslatableMarkup('My Channel'),
  description: new TranslatableMarkup('Send via my custom service.'),
)]
class MyChannel extends ChannelPluginBase {

  public function isAvailable(): bool {
    return TRUE; // Check if your service is configured
  }

  public function send(RecipientInterface $recipient, string $subject, string $message): bool {
    // Your sending logic here
    return TRUE;
  }

}
```

## API

### Sending programmatically

```php
// Get the notification service.
$service = \Drupal::service('openintranet_messenger.notification_service');

// Get recipient resolver.
$resolver = \Drupal::service('openintranet_messenger.recipient_resolver');

// Resolve recipients.
$recipients = $resolver->resolveAllActiveContacts();

// Send bulk notification.
$result = $service->sendBulk($recipients, 'Subject', 'Message body');

// Check results.
echo "Sent: " . $result->getSentCount();
echo "Failed: " . $result->getFailedCount();
echo "Skipped: " . $result->getSkippedCount();
```

## License

GPL-2.0-or-later
