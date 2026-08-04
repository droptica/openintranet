# Open Intranet Forum

Modern forum/discussion board for Open Intranet with social feed features, reactions, categories, and search.

## Features

- **Forum Posts**: Rich content type with categories, tags, and reactions
- **Threaded Replies**: Comment system with threading support
- **Reactions**: Like, helpful, and insightful reactions on posts and replies
- **Categories & Tags**: Organize posts with hierarchical categories and flexible tags
- **Search**: Full-text search powered by Search API
- **Trending Posts**: Algorithm-based trending feed (reactions + views + recency)
- **Bookmarks & Follow**: Flag posts to bookmark or follow for notifications
- **Statistics**: View counts, reply counts, and activity tracking
- **Engagement Tracking**: Integration with Open Intranet Engagement module
- **Permissions**: Granular permissions with moderator role support

## Requirements

- Drupal 10.3+ or Drupal 11+
- PHP 8.1+

### Required Modules

- Node (core)
- Taxonomy (core)
- Comment (core)
- User (core)
- VotingAPI
- VotingAPI Reaction
- Flag
- Search API
- Statistics
- Iconify Field

### Optional Integrations

- Open Intranet Engagement (track forum activity in RFV scoring)
- Open Intranet Messenger (in-app notifications)
- Search API Solr (enhanced search)

## Installation

### Option 1: Using Recipe (Recommended)

```bash
# Apply the forum recipe
cd web
php core/scripts/drupal recipe recipes/forum
drush cr
```

### Option 2: Manual Installation

```bash
# Enable the module
drush en openintranet_forum -y

# Clear cache
drush cr

# Import default configuration
drush cim -y
```

## Configuration

1. **Forum Settings**: `/admin/config/openintranet/forum`
   - Posts per page
   - Trending posts settings
   - Notification preferences
   - Threading options

2. **Permissions**: `/admin/people/permissions`
   - Configure who can create, edit, delete posts
   - Assign moderator permissions

3. **Categories**: `/admin/structure/taxonomy/manage/forum_category`
   - Create and organize forum categories
   - Add icons to categories

4. **Reactions**: `/admin/structure/votingapi-reaction`
   - Configure reaction types (Like, Helpful, Insightful)

5. **Flags**: `/admin/structure/flags`
   - Configure bookmark and follow flags

## Usage

### For Users

- **View Forum**: Navigate to `/forum/feed`
- **Create Post**: Click "Add new post" button
- **Reply to Post**: Click on a post and add a comment
- **React to Content**: Click reaction buttons on posts/replies
- **Bookmark Post**: Click bookmark icon to save for later
- **Follow Post**: Click follow icon to receive notifications
- **Search**: Use search box in sidebar

### For Moderators

- Edit any post/reply
- Delete inappropriate content
- Pin important posts to top
- Manage categories and tags

## Support

- **Project page**: https://www.drupal.org/project/openintranet_forum
- **Issue queue**: https://www.drupal.org/project/issues/openintranet_forum
