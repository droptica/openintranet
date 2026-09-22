# User Engagement Analytics

Drupal module for tracking and analyzing user engagement using the RFV (Recency, Frequency, Value) scoring model. Designed for intranets and community sites to understand user adoption and identify engagement patterns.

## Features

### RFV Scoring Model
Track user activity and calculate engagement scores based on:
- **Recency (R)**: How recently a user was active
- **Frequency (F)**: How often a user performs actions
- **Value (V)**: How valuable are user's contributions (weighted by action type)

### Automatic User Segmentation
Users are automatically classified into segments:
- **Champion** - Most engaged power users
- **Loyal** - Regular users with stable engagement
- **At Risk** - Previously active users showing decline
- **Dormant** - Inactive users needing reactivation
- **New** - Recently registered users
- **Regular** - Standard users

### Generic Entity Tracking
- Track any content entity type (nodes, comments, media, custom entities)
- Configurable point values per entity type and operation (create, view, update, delete)
- Automatic detection of available entity types

### Executive Reports
- KPI dashboard with key metrics
- User adoption rate tracking
- Segment distribution visualization
- Top contributors report
- Content performance analysis
- Department/group analysis
- Activity patterns (hourly, daily)

### Data Export
- CSV export for all reports
- Filterable by segment, date range
- Compatible with external BI tools

### Drush Commands
Full CLI support for automation and cron jobs.

## Requirements

- Drupal 10.3+ or Drupal 11+
- PHP 8.1+

## Installation

```bash
composer require drupal/openintranet_engagement
drush en openintranet_engagement
```

## Configuration

1. Navigate to **Administration > Configuration > User Engagement** (`/admin/config/openintranet/engagement`)
2. Enable tracking and configure RFV thresholds
3. Navigate to **Entity Types** tab to configure which entities to track and their point values

## Usage

### Admin Reports

| Report | Path | Description |
|--------|------|-------------|
| Dashboard | `/admin/reports/engagement` | Overview with KPIs and segments |
| Users | `/admin/reports/engagement/users` | User list with scores |
| Executive | `/admin/reports/engagement/executive` | Summary for management |
| Content | `/admin/reports/engagement/content` | Content performance |
| Contributors | `/admin/reports/engagement/contributors` | Top contributors |
| Patterns | `/admin/reports/engagement/patterns` | Activity patterns |
| Departments | `/admin/reports/engagement/departments` | Group analysis |
| Export | `/admin/reports/engagement/export` | CSV export |

### Drush Commands

```bash
# Recalculate all user scores
drush engagement:recalculate

# Show segment distribution
drush engagement:segments

# Show executive summary
drush engagement:summary

# Show top 20 engaged users
drush engagement:top 20

# Clean old event data (older than retention period)
drush engagement:cleanup

# Export to CSV
drush engagement:export /tmp/users.csv --segment=at_risk
```

## Permissions

| Permission | Description |
|------------|-------------|
| `administer openintranet engagement` | Access module settings |
| `view engagement dashboard` | View analytics dashboard |
| `view engagement scores` | View all user engagement scores |
| `view own engagement score` | Users can view their own score |
| `export engagement data` | Export engagement data to CSV |

## API

### Tracking Custom Events

```php
$tracker = \Drupal::service('openintranet_engagement.tracker');
$tracker->trackEvent($user_id, 'custom_action', $entity_type, $entity_id, $points);
```

### Getting User Score

```php
$calculator = \Drupal::service('openintranet_engagement.calculator');
$score = $calculator->calculateScore($user);
// Returns: ['recency' => 5, 'frequency' => 3, 'value' => 4, 'total' => 12]
```

### Getting User Segment

```php
$segmenter = \Drupal::service('openintranet_engagement.segmenter');
$segment = $segmenter->getSegment($user);
// Returns: 'champion', 'loyal', 'at_risk', 'dormant', 'new', or 'regular'
```

## Extending

The module is designed to be extensible:
- Implement custom entity type discovery via `EntityTypeDiscoveryInterface`
- Add custom event types via hook or event subscriber
- Override segmentation logic via service decoration

## Maintainers

- [Droptica](https://www.drupal.org/droptica)

## Supporting Organizations

- [Droptica](https://www.drupal.org/droptica)
