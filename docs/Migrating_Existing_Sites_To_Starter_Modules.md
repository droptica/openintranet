# Migrating an existing site to the starter-modules layout

Applies to sites installed from Open Intranet releases **before** the
starter model, where the feature modules lived inside the install profile
(`web/profiles/openintranet/modules/`). In the starter model the modules
live in `web/modules/openintranet_custom_modules/openintranet_core/` and are
the site owner's code.

If you update your codebase to a starter-model release without this
migration, Drupal will no longer find the openintranet_* modules (they are
gone from the profile) and the site will break.

## One-time migration

Run from the project root **before** deploying the updated codebase to any
environment, in the same commit as the code update:

```bash
# 1. Take ownership of the module code at its new location.
mkdir -p web/modules/openintranet_custom_modules
cp -R starter-modules/openintranet_core web/modules/openintranet_custom_modules/

# 2. Make sure the old location is gone (the release removes it; verify).
#    A module MUST NOT exist in two discovered locations at once.
test ! -d web/profiles/openintranet/modules || rm -rf web/profiles/openintranet/modules

# 3. Track the code as your own.
git add web/modules/openintranet_custom_modules
git commit -m "Adopt Open Intranet starter modules"

# 4. On each environment after deploying, rebuild caches:
drush cr
```

Drupal identifies modules by machine name, not by path, so no database
changes are needed — the extension system picks the modules up from the new
location on the next cache rebuild.

## After migrating

- The modules are yours: modify them freely, there is no upstream update
  path anymore (see the "Open Intranet is a starter" section in README.md).
- The `openintranet_core` module (new in this layout) provides the
  /admin/openintranet menu structure, the Flag views relationships used by
  the must-read report, and the `openintranet_core:import-book-structure`
  Drush command. Enable it: `drush en openintranet_core`.
