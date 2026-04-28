> [!IMPORTANT]
> **Development happens at [drupal.org/project/openintranet](https://www.drupal.org/project/openintranet).**
> File issues and submit patches there. This GitHub repo is a read-only mirror for visibility — pull requests opened here are not merged.

<div align="center">

# Open Intranet

**Open Source Intranet You Actually Own.**

Drupal 11 distribution for internal communication, knowledge management, and employee engagement. No per-user fees. No vendor lock-in.

[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
[![Drupal 11](https://img.shields.io/badge/drupal-11-orange.svg)](https://www.drupal.org/project/openintranet)
[![Issue queue](https://img.shields.io/badge/issues-drupal.org-blue.svg)](https://www.drupal.org/project/issues/openintranet)
[![PHP 8.3+](https://img.shields.io/badge/php-8.3%2B-777BB4.svg)](https://www.php.net/)

[Website](https://open-intranet.com) · [Documentation](https://open-intranet.com/docs) · [Drupal.org project](https://www.drupal.org/project/openintranet) · [Issue queue](https://www.drupal.org/project/issues/openintranet) · [Request demo](https://www.droptica.com/products/intranet/)

</div>

---

![Open Intranet — Home dashboard with featured news, highlighted links, news feed and upcoming events](.github/assets/screenshot-home.png)

## Why Open Intranet

- **Own the source code, infrastructure, and your data.** GPL-2.0-or-later, deploy anywhere — on-prem, private cloud, or any PHP host.
- **No per-user fees.** Self-host without seat-based pricing or vendor lock-in.
- **Built on Drupal 11.** Battle-tested CMS used by governments, banks, universities, and Fortune 500 companies worldwide.
- **Production-ready.** Used in real enterprise and public sector deployments — from 50-person teams to 7,000+ user organizations.
- **Customizable to the bone.** Extend, theme, and integrate without limits. No "premium tier" walls.

## Features

### News & Communications

- **News & Announcements** — Share company updates with rich content, media, and scheduled publishing
- **Must-Read posts** — Mandatory content with read confirmations for compliance and policy rollouts
- **Targeted distribution** — Right message to the right people; no more email flooding
- **Events Calendar** — Schedule and manage company events with RSVPs
- **Comments & Reactions** — Built-in social interactions on every piece of content
- **Peer recognition (Kudos)** — Reward and celebrate teammates publicly
- **Multi-channel notifications** — Reach users via email and SMS, including external contacts

### Knowledge Management

- **Knowledge Base** — Hierarchical Book-style documentation with full revision history
- **Wiki pages** — Collaboratively edited pages with diff and rollback
- **AI-powered search** — RAG with vector search to surface answers, not just keywords
- **AI-assisted content** — Suggested text for news, articles, and announcements
- **Recently-read tracking** — Personalized continue-where-you-left-off views
- **Glossary & internal links** — Curate a single source of truth for company terminology

### Document Management

- **Document library** — Store, version, and share company documents
- **Granular file permissions** — Per-file and per-folder access control
- **Private files with download permissions** — Enforce ACLs on direct downloads
- **Media library** — Reusable images, videos, and embeds across content
- **CKEditor with media resize** — In-place editing of attached media

### People & Teams

- **Employee directory** — Searchable staff listing with rich profiles
- **Organization chart** — Interactive org tree showing reporting lines
- **Groups & spaces** — Departments, projects, and ad-hoc team areas
- **Frontend editing** — Edit content in place without admin UI roundtrips
- **Profile pages** — Skills, contact info, and personal updates per user

### Integrations

- **LDAP / Active Directory** — Sync users and groups from your directory
- **SAML / OpenID Connect (SSO)** — Single sign-on with Keycloak, Azure AD, Okta, Google Workspace
- **SMS gateway** — Send notifications via SMSAPI and compatible providers
- **Apache Solr** — Drop-in enterprise search backend
- **Calendars** — iCal feeds for Google, Microsoft 365, Apple Calendar
- **REST/JSON:API** — Build mobile apps, dashboards, and custom integrations
- **ECA workflow automation** — No-code event–condition–action automations

### Admin & Compliance

- **Role-based access control** — Drupal's mature permission system, refined for intranet use
- **Audit trail** — Track user actions and content changes for accountability
- **Adoption analytics** — RFV scoring, active users, and segment health out of the box
- **Auto-logout & session control** — Configurable idle timeouts
- **Backup & migrate** — Built-in tooling for scheduled backups
- **Masquerade** — Support staff can troubleshoot as another user safely
- **GDPR-friendly** — Data export, deletion, and consent flows
- **Multilingual** — Full i18n with translation workflows for 100+ languages

## Quick Start

```bash
composer create-project droptica/openintranet:^1 my-intranet
cd my-intranet
drush site:install openintranet
```

Or, if you already have DDEV installed, the fastest path is:

```bash
curl -sL https://intranet.new/install.sh | bash
```

Full installation guide: [open-intranet.com/docs/getting-started/installation](https://open-intranet.com/docs/getting-started/installation)

## Screenshots

<table>
  <tr>
    <td><img src=".github/assets/screenshot-news-feed.png" alt="News feed" /></td>
    <td><img src=".github/assets/screenshot-documents.png" alt="Document detail view with preview and access controls" /></td>
  </tr>
  <tr>
    <td><img src=".github/assets/screenshot-knowledge-base.png" alt="Knowledge Base / Book hierarchy with sidebar navigation" /></td>
    <td><img src=".github/assets/screenshot-ai-assistant.png" alt="AI Assistant generating content for a knowledge base page" /></td>
  </tr>
  <tr>
    <td colspan="2" align="center"><img src=".github/assets/screenshot-people-directory.png" alt="Employee directory" /></td>
  </tr>
</table>

## Tech stack

- **Drupal 11.3+** — Core CMS framework
- **PHP 8.3+** — With strict types and modern language features
- **MySQL 8 / MariaDB 10.6+ / PostgreSQL 16+** — Choose your database engine
- **Composer** — Dependency management and installation
- **Drush 13+** — Command-line interface
- **Apache Solr** (optional) — Enterprise search backend
- **DDEV** (optional) — Containerized local development

## Contributing

Development happens on **drupal.org**, not GitHub. To contribute:

1. **Issues** — Open and discuss tickets in the [drupal.org issue queue](https://www.drupal.org/project/issues/openintranet).
2. **Patches & merge requests** — Submit through [drupal.org issue forks](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/creating-issue-forks). GitHub pull requests are **not** merged.
3. **Discussions** — Join the conversation in the [Drupal Slack](https://www.drupal.org/slack), channel `#intranet`.
4. **Source repository** — [git.drupalcode.org/project/openintranet](https://git.drupalcode.org/project/openintranet)

This GitHub repository is a read-only mirror to give the project visibility on GitHub. All commits flow from drupal.org → GitHub one-way.

## Maintainers

Open Intranet is built and maintained by [**Droptica**](https://www.droptica.com) — a Drupal agency since 2011.

- Project page: [drupal.org/project/openintranet](https://www.drupal.org/project/openintranet)
- Full list of contributors: [drupal.org/project/openintranet/committers](https://www.drupal.org/project/openintranet/committers)

## License

[GNU GPL v2 or later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html) — see [LICENSE.txt](https://git.drupalcode.org/project/openintranet/-/blob/HEAD/LICENSE.txt) on drupal.org.

## About

Built and maintained by [**Droptica**](https://www.droptica.com) — a Drupal agency since 2011.

We build solid open source solutions for ambitious companies: corporations, SMEs, startups, universities, and government.

