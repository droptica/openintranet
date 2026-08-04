# Open Intranet Documents

A hierarchical document management system for Drupal 10/11 with folder organization, file uploads, and search functionality.

> **Note**: This module is developed as part of the [Open Intranet](https://www.drupal.org/project/openintranet) distribution. It has not been tested with standard Drupal core installations or other distributions. While it may work independently, full compatibility is only guaranteed within the Open Intranet ecosystem.

## Features

- **Folder Management**: Create nested folder structures to organize documents
- **Document Upload**: Upload files with title, description, and folder assignment
- **File Browser**: Visual browser with breadcrumb navigation
- **Search**: Full-text search across document titles, descriptions, and filenames
- **Modal Forms**: Add folders and upload documents via AJAX modal dialogs
- **File Type Icons**: Automatic icons for PDF, Word, Excel, PowerPoint, images, and more
- **Download**: Direct file download with proper headers
- **Bootstrap 5**: Modern, responsive UI using Bootstrap components

## Requirements

- Drupal 10.x or 11.x
- PHP 8.1+
- [Alpine.js](https://www.drupal.org/project/alpine_js) module

## Installation

1. Install via Composer:

```bash
composer require drupal/openintranet_documents
```

2. Enable the module:

```bash
drush en openintranet_documents
```

3. Clear caches:

```bash
drush cr
```

## Usage

### Accessing the Document Browser

Navigate to `/documents` to access the main document browser interface.

### Creating Folders

1. Click the folder icon in the toolbar
2. Enter folder name and optional description
3. Select parent folder (optional)
4. Save

### Uploading Documents

1. Click the upload icon in the toolbar
2. Enter document title and optional description
3. Select the file to upload
4. Choose destination folder (optional)
5. Save

### Searching

Use the search field in the toolbar or navigate to `/documents/search` to search across:
- Document titles
- Document descriptions
- Filenames

## Entities

### OI Folder (`oi_folder`)

Custom entity for folder management with fields:
- `name`: Folder name
- `description`: Optional description
- `parent`: Reference to parent folder (for nesting)

### OI Document (`oi_document`)

Custom entity for documents with fields:
- `title`: Document title
- `description`: Optional description
- `file`: File attachment
- `folder`: Reference to containing folder

## Routes

| Route | Path | Description |
|-------|------|-------------|
| `openintranet_documents.browser` | `/documents` | Main browser |
| `openintranet_documents.folder.view` | `/documents/folder/{oi_folder}` | View folder |
| `entity.oi_document.canonical` | `/documents/document/{oi_document}` | View document |
| `openintranet_documents.search` | `/documents/search` | Search page |
| `openintranet_documents.download` | `/documents/document/{oi_document}/download` | Download file |

## Services

| Service | Class | Description |
|---------|-------|-------------|
| `openintranet_documents.folder_manager` | `OiFolderManager` | Folder operations |
| `openintranet_documents.document_manager` | `OiDocumentManager` | Document operations |

## Permissions

- `administer oi_folder entities`: Manage folders
- `administer oi_document entities`: Manage documents
- `view oi_folder`: View folders
- `view oi_document`: View documents
- `create oi_folder`: Create folders
- `create oi_document`: Upload documents
- `edit oi_folder`: Edit folders
- `edit oi_document`: Edit documents
- `delete oi_folder`: Delete folders
- `delete oi_document`: Delete documents

## Theming

The module provides several Twig templates that can be overridden in your theme:

- `oi-documents-browser.html.twig`: Main browser view
- `oi-document-view.html.twig`: Document detail page
- `oi-documents-search.html.twig`: Search results page

### CSS Classes

Key CSS classes for styling:
- `.oi-documents-browser`: Main browser container
- `.oi-folder-card`: Folder card in grid view
- `.oi-document-row`: Document row in list view
- `.oi-breadcrumb-nav`: Breadcrumb navigation
- `.oi-search-result`: Search result item

## Configuration

No additional configuration required. The module works out of the box after installation.

## API

### OiFolderManager

```php
// Get folder manager service
$folderManager = \Drupal::service('openintranet_documents.folder_manager');

// Get root folders
$rootFolders = $folderManager->getRootFolders();

// Get folder children
$children = $folderManager->getChildren($folder);

// Get breadcrumbs
$breadcrumbs = $folderManager->getBreadcrumbs($folder);

// Search folders
$results = $folderManager->search('query', 20, 0);
```

### OiDocumentManager

```php
// Get document manager service
$documentManager = \Drupal::service('openintranet_documents.document_manager');

// Get documents in folder
$documents = $documentManager->getDocumentsInFolder($folder);

// Search documents
$results = $documentManager->search('query', 20, 0);
```

## Development

### Running Tests

```bash
# PHPUnit tests
./vendor/bin/phpunit modules/custom/openintranet_documents
```

### Code Standards

```bash
# Check coding standards
./vendor/bin/phpcs --standard=Drupal modules/custom/openintranet_documents
```

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Submit a merge request

Report issues at: https://www.drupal.org/project/issues/openintranet_documents

## License

This project is licensed under the GNU General Public License v2.0 or later.

## Credits

Developed by [Droptica](https://www.droptica.com) as part of the [Open Intranet](https://www.drupal.org/project/openintranet) project.

## Changelog

### 1.0.0

- Initial release
- Folder and document entity management
- File browser with grid/list views
- Search functionality
- Modal forms for add/upload
- Bootstrap 5 responsive UI
