# Open Intranet Development Guide

## How to Release a New Version of Open Intranet 1.0.x

When preparing a new release of Intranet 1.0.x, follow these steps:



1. **Test the Release**
   - Perform manual testing of key features (you can use testing_reinstall.sh)
   - (not ready yet) Run tests with playwright (you can use tests/run-playwright-tests.sh)

2. **Update Version Numbers**
   - Update the version number in `composer.json`:
     ```json
     {
       "name": "droptica/openintranet",
       "version": "dev-1.0.x" // Change from "dev-1.0.x" to specific version like "1.0.1"
     }
     ```
   - Update the version in `web/profiles/openintranet/openintranet.info.yml`:
     ```yaml
     name: Open Intranet 1.0
     type: profile
     core_version_requirement: ^10.3 || ^11
     description: 'Open Intranet 1.0 profile.'
     version: 'dev-1.0.x' // Update to new version number like "1.0.1"
     ```

4. **Create Release**
   - Tag the release on drupal.org with the new version number
   - Ensure the tag follows semantic versioning (e.g., `1.0.1`)
   - Create a new release on drupal.org/project/openintranet -> Edit -> Releases -> Add new release

5. **Post-Release**
   - Update development branch (`1.0.x`) version numbers back to development versions:
     - `dev-1.0.x` in `composer.json`
     - `dev-1.0.x` in `openintranet.info.yml`

> **Note**: Always follow semantic versioning principles when choosing new version numbers.
