# Open Intranet - Drupal-based Intranet Starter Kit

## About Open Intranet

Open Intranet is a comprehensive Drupal-based starter kit designed for companies wanting to create an internal portal for their organization. It provides ready-to-use functionality commonly needed in corporate environments, saving significant development time and resources.

The maintainer of Open Intranet is [Droptica](https://www.droptica.com).

## Key Features

- **News & Announcements**: Share company updates and important information
- **Events Calendar**: Schedule and manage company events
- **Knowledge Base**: Create and organize internal documentation
- **Document Management**: Store and share company documents
- **Employee Directory**: Searchable listing of staff with profiles
- **User Management**: Integration with LDAP and role-based access control
- **Responsive Design**: Works across desktop and mobile devices

## Pre-requisites

To install Open Intranet you need [DDEV](https://ddev.com) installed on your machine.

## Installation

1. Clone this repository
2. `cd` into the project directory
3. Run the command `./launch-intranet.sh`

or

```
git clone https://git.drupalcode.org/project/openintranet.git openintranet
cd openintranet
./launch-intranet.sh
```

### Installation options

After the script finishes you can choose to run the Open Intranet installation via the browser or the command line.

Type `ddev launch` to open the Open Intranet installation in your browser.

#### To install Open Intranet via the command line:

Type `ddev drush site-install openintranet` to install the Open Intranet profile.

## Customization

Open Intranet comes with a starter theme located in `web/themes/custom/`. You can customize this theme to match your company's branding.

## Issues

If you encounter any issues, please [create an issue in the Open Intranet project](https://www.drupal.org/project/openintranet).


