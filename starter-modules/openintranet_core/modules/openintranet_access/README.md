# Open Intranet Access

Group-based access control module for Open Intranet Drupal distribution.

## Features

- **Hierarchical Groups**: Create groups with parent-child relationships (e.g., Company → Department → Team)
- **Group Memberships**: Assign users to one or more groups
- **Entity Access Control**: Restrict access to content (nodes, documents, folders) by groups or individual users
- **Access Inheritance**: 
  - Users in child groups can access content restricted to parent groups
  - Documents can inherit access restrictions from their parent folders
- **Plugin System**: Extensible architecture for supporting additional entity types

## Installation

```bash
drush en openintranet_access -y
drush cr
```

## Configuration

### Module Settings

Navigate to **Configuration → People → Open Intranet Access** (`/admin/config/people/openintranet-access`).

Configure:
- Which content types should have access control
- Whether documents/folders are enabled
- Behavior settings (owner access, folder inheritance)

### Managing Groups

Navigate to **People → Groups** (`/admin/people/oi-groups`).

- Create groups with hierarchical structure
- Add/remove members
- View subgroups and member counts

## Usage

### Setting Access on Content

1. Navigate to any node: `/node/{nid}`
2. Click the **Access** tab
3. Select groups and/or individual users who should have access
4. Save

Content without restrictions uses standard Drupal permissions.

### Access Logic

When content has restrictions:
- User must be a member of one of the allowed groups, OR
- User must be explicitly listed as an individual user, OR
- User is the content owner (if "owner always has access" is enabled), OR
- User has the "bypass openintranet access" permission

Group membership includes hierarchy:
- If user is in "Sales Team" (child of "Sales Department"), they can access content restricted to "Sales Department"

## Permissions

| Permission | Description |
|------------|-------------|
| `administer openintranet access` | Configure module settings |
| `administer oi_group` | Full admin access to groups |
| `manage oi groups` | Create, edit, delete groups |
| `manage group members` | Add/remove users from groups |
| `view oi groups` | View group listings |
| `bypass openintranet access` | Access all content regardless of restrictions |
| `set entity access` | Configure access on content |

## API

### Services

```php
// Group management
$groupManager = \Drupal::service('openintranet_access.group_manager');

// Get user's groups (direct membership)
$groups = $groupManager->getUserGroups($account);

// Get user's groups including ancestors (for access checking)
$groups = $groupManager->getUserGroupsWithAncestors($account);

// Add user to group
$groupManager->addMember($group, $account);

// Check membership
$isMember = $groupManager->isMember($group, $account);
```

```php
// Access checking
$checker = \Drupal::service('openintranet_access.checker');

// Check if entity has restrictions
$hasRestrictions = $checker->hasRestrictions($entity);

// Check user access
$result = $checker->checkEntityAccess($entity, $account, 'view');
// Returns: TRUE (allowed), FALSE (denied), NULL (no restrictions)

// Get groups with access
$groups = $checker->getAccessGroups($entity);
```

```php
// Access management
$accessManager = \Drupal::service('openintranet_access.access_manager');

// Set groups with access
$accessManager->setAccessGroups($entity, $groups);

// Set users with access
$accessManager->setAccessUsers($entity, $userIds);

// Clear all restrictions
$accessManager->clearAccessRestrictions($entity);
```

## Database Tables

- `oi_group`: Group entity storage
- `oi_group_membership`: User-group membership relations
- `oi_access_record`: Entity access restrictions

## Hooks

The module provides:
- `hook_entity_access()`: Enforces group-based access
- `hook_node_grants()` / `hook_node_access_records()`: Integrates with Drupal's node access system

## Extending

### Custom Entity Type Plugin

Create a plugin in `src/Plugin/OiAccessEntity/`:

```php
/**
 * @OiAccessEntityPlugin(
 *   id = "my_entity",
 *   label = @Translation("My Entity"),
 *   entity_type = "my_entity"
 * )
 */
class MyEntityAccessPlugin extends OiAccessEntityPluginBase {
  // Override methods as needed
}
```

## Requirements

- Drupal 10.3+ or Drupal 11
- PHP 8.1+

## Testing

The module includes PHPUnit kernel tests covering:

- **OiGroupTest**: Group entity CRUD, hierarchy, status
- **OiGroupMembershipTest**: Add/remove members, get members/groups
- **OiAccessCheckerTest**: Access checking, group/user access, inheritance
- **OiAccessManagerTest**: Set/add/remove access groups and users
- **OiGroupHierarchyTest**: Children, descendants, ancestors

### Running Tests

**Prerequisites:**

Install dev dependencies (from Drupal project root):

```bash
composer require --dev phpunit/phpunit drupal/core-dev --with-all-dependencies
```

**Run all module tests:**

```bash
# Using DDEV
ddev exec "cd /var/www/html/web/modules/custom/openintranet_access && /var/www/html/vendor/bin/phpunit"

# Without DDEV (from Drupal project root)
cd web/modules/custom/openintranet_access
../../../../vendor/bin/phpunit
```

**Run specific test class:**

```bash
ddev exec "cd /var/www/html/web/modules/custom/openintranet_access && /var/www/html/vendor/bin/phpunit --filter OiGroupTest"
```

**Run tests with verbose output:**

```bash
ddev exec "cd /var/www/html/web/modules/custom/openintranet_access && /var/www/html/vendor/bin/phpunit --testdox"
```

## License

GPL-2.0-or-later
