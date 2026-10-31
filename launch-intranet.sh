#!/usr/bin/env bash

###
# Launches Open Intranet using DDEV.
#
# This requires that DDEV be installed and available in the PATH, and only works in
# Unix-like environments (Linux, macOS, or the Windows Subsystem for Linux). This will
# initialize DDEV configuration, start the containers, install dependencies, and open
# Open Intranet in the browser.
###

# Abort this entire script if any one command fails.
set -e

# Parse command line arguments
# -y/--yes: Skip interactive prompts (answers "no" to remove installation files)
AUTO_YES=false
while [[ "$#" -gt 0 ]]; do
    case $1 in
        -y|--yes) AUTO_YES=true ;;
    esac
    shift
done

echo ""
echo "Open Intranet launcher (DDEV)"
echo "============================="
echo "This installer runs Open Intranet on DDEV."
echo ""

if ! command -v ddev >/dev/null; then
  echo "DDEV needs to be installed. Visit https://ddev.com/get-started for instructions."
  exit 1
fi

NAME=$(basename "$PWD" | tr '_' '-')

# If another DDEV project on this system already has this name, add the first
# free numeric suffix. The names come from the JSON output: the table wraps
# long names ("openintran" / "et"), so grepping it missed existing projects
# and `ddev config` then refused the taken name.
EXISTING_NAMES=$(ddev list -j 2>/dev/null | grep -o '"name":"[^"]*"' | sed 's/^"name":"\(.*\)"$/\1/' || true)
if printf '%s\n' "$EXISTING_NAMES" | grep -qx "$NAME"; then
  SUFFIX=2
  while printf '%s\n' "$EXISTING_NAMES" | grep -qx "$NAME-$SUFFIX"; do
    SUFFIX=$((SUFFIX + 1))
  done
  NAME="$NAME-$SUFFIX"
fi

# Configure DDEV if not already done. Check for the config file, not the
# directory: a clone may contain a .ddev/ directory without a config.yaml,
# and skipping `ddev config` then makes `ddev start` fail.
if [ -f .ddev/config.yaml ]; then
  echo "==> DDEV already configured, reusing .ddev/config.yaml"
else
  echo "==> Configuring DDEV project \"$NAME\" (Drupal 11, PHP 8.5, docroot: web)"
  ddev config --project-type=drupal11 --docroot=web --php-version=8.5 --ddev-version-constraint=">=1.24.0" --project-name="$NAME"
fi

# Prepare all project files BEFORE `ddev start`: with Mutagen (the default on
# macOS) the container only sees host files after a sync cycle, so files
# copied after the start may not be inside the container yet when the first
# `drush site-install` runs ("openintranet_theme" is not a known module or
# theme). `ddev start` waits for the initial sync, so anything created here
# is guaranteed to be visible in the container.

# Copy the DDEV commands to the project.
echo "==> Copying project DDEV commands to .ddev/commands/"
cp -r ddev_commands/* .ddev/commands/

# Copy the starter theme to the project. The parent directory does not exist
# on a fresh clone (composer creates web/themes later), so create it first.
echo "==> Copying starter theme to web/themes/custom/openintranet_theme"
mkdir -p web/themes/custom
cp -R starter-theme/openintranet_theme web/themes/custom/

# Copy the starter modules to the project. Like the starter theme, the copies
# become the site owner's code: Open Intranet ships them as a starting point
# with no upgrade path (new releases apply to new installations only). They
# live in their own directory (Drupal discovers any web/modules subdirectory)
# so web/modules/custom stays free for the site's own modules.
echo "==> Copying starter modules to web/modules/openintranet_custom_modules/openintranet_core"
mkdir -p web/modules/openintranet_custom_modules
cp -R starter-modules/openintranet_core web/modules/openintranet_custom_modules/

# Stamp every copied extension with its Open Intranet source version so a
# site's vintage can always be identified and diffed against the right tag.
#
# The stamp also links the copies to the "openintranet" project on
# localize.drupal.org: without a project key the locale module skips them, so
# Open Intranet's interface translations were never downloaded. A development
# version ("dev-1.x") maps to the branch file ("openintranet-1.x.pl.po"),
# which the translation server provides.
OI_VERSION=$(sed -n "s/^version: '\{0,1\}\([^']*\)'\{0,1\}$/\1/p" web/profiles/openintranet/openintranet.info.yml | head -1)
OI_TRANSLATION_VERSION="${OI_VERSION#dev-}"
echo "==> Stamping copied starter code with source version: openintranet:$OI_VERSION"
STAMPED=0
while read -r info; do
  # Drop any version of the extension's own so the stamped one is the only key.
  grep -v '^version:' "$info" > "$info.tmp" && mv "$info.tmp" "$info"
  printf "\n# Starter code generated from Open Intranet %s - owned by this site,\n# not updated by Open Intranet releases.\nstarter_source: 'openintranet:%s'\n# Interface translations come from the openintranet project on\n# localize.drupal.org.\ninterface translation project: openintranet\nversion: '%s'\n" "$OI_VERSION" "$OI_VERSION" "$OI_TRANSLATION_VERSION" >> "$info"
  STAMPED=$((STAMPED + 1))
done < <(find web/modules/openintranet_custom_modules/openintranet_core web/themes/custom/openintranet_theme -name "*.info.yml" -not -path "*/tests/*" -not -path "*/node_modules/*")
echo "    Stamped $STAMPED extension(s). The copies are this site's code from now on."

# Start your engines.
echo "==> Starting DDEV containers (this waits for the initial file sync)"
ddev start
# Install dependencies if not already done.
if [ -f composer.lock ]; then
  echo "==> Composer dependencies already installed (composer.lock present), skipping"
else
  echo "==> Installing Composer dependencies (first run - this takes a few minutes)"
  ddev composer install
fi

# Set up the private file system BEFORE any drush site-install. The install
# profile patches settings.php too, but editing the file mid-install does not
# affect the already-running `drush site-install` process (single bootstrap),
# so the private:// stream wrapper never registers and the demo content
# import fails copying demo.pdf. This must run AFTER the composer scaffold /
# ddev start have created settings.php - on a fresh clone the file does not
# exist any earlier.
SETTINGS_FILE=web/sites/default/settings.php
if [ -f "$SETTINGS_FILE" ] && grep -q "^# \$settings\['file_private_path'\] = '';" "$SETTINGS_FILE"; then
  echo "==> Enabling the private file system (sites/private_files) in settings.php"
  sed "s|^# \$settings\['file_private_path'\] = '';|\$settings['file_private_path'] = 'sites/private_files';|" "$SETTINGS_FILE" > "$SETTINGS_FILE.tmp" \
    && mv "$SETTINGS_FILE.tmp" "$SETTINGS_FILE"
elif grep -q "file_private_path'\] = 'sites/private_files'" "$SETTINGS_FILE" 2>/dev/null; then
  echo "==> Private file system already configured in settings.php"
else
  echo "==> WARNING: could not configure the private file system (settings.php missing or unexpected)."
  echo "    Add \$settings['file_private_path'] = 'sites/private_files'; to settings.php before installing."
fi
mkdir -p web/sites/private_files

ask_yes_no() {
    while true; do
        # End of input counts as "no". With piped or closed input (ddev and
        # composer may have read the piped answers already), read fails on
        # every call and the question would repeat forever.
        if ! read -p "$1 [y/n]: " yn; then
            echo ""
            echo "No answer (end of input), assuming no."
            return 1
        fi
        case $yn in
            [Yy]* ) return 0;;
            [Nn]* ) return 1;;
            * ) echo "Please answer yes (y) or no (n).";;
        esac
    done
}

# Ask about removing the Open Intranet development files. There are two ways
# to use this codebase:
#  - Building YOUR OWN intranet: remove the development files (.git,
#    ddev_commands, starter-theme, starter-modules) and initialize a fresh
#    git repository. Your project starts here - the copied modules and theme
#    are your code, and Open Intranet's history is not your history.
#  - Contributing to Open Intranet itself: keep everything as is.
# The -y flag skips the prompt and keeps the files (development mode).
if [ "$AUTO_YES" = true ]; then
    REMOVE_DEV_FILES=false
else
    echo ""
    echo "How will you use this codebase?"
    echo ""
    echo "  * Building YOUR OWN intranet: answer YES below. The Open Intranet"
    echo "    development files (.git, ddev_commands, starter-theme, starter-modules)"
    echo "    will be removed and you can initialize a fresh git repository -"
    echo "    your project starts here, with the copied modules and theme as"
    echo "    your own code (Open Intranet is a starter, not an upgrade path)."
    echo ""
    echo "  * Contributing to Open Intranet itself: answer NO to keep everything."
    echo ""
    if ask_yes_no "Remove the Open Intranet development files and start your own project?"; then
        REMOVE_DEV_FILES=true
    else
        REMOVE_DEV_FILES=false
    fi
fi
if [ "$REMOVE_DEV_FILES" = true ]; then
    echo "Removing installation files (.git, ddev_commands, starter-theme, starter-modules)..."
    rm -rf .git
    rm -rf ddev_commands
    rm -rf starter-theme
    rm -rf starter-modules

    # Use project .gitignore template for new projects
    if [ -f .gitignore.project ]; then
        echo "Setting up .gitignore for new project..."
        cp .gitignore.project .gitignore
        rm -f .gitignore.project
        echo "Updated .gitignore for new project development (the starter modules and theme are tracked as your code)."
    fi

    # Only ask about git init if files were removed
    if ask_yes_no "Would you like to initialize a new git repository?"; then
        echo "Initializing new git repository..."
        git init
    fi
else
    echo "==> Keeping installation files for open source development."
    echo "    Using the default .gitignore configured for contributing to Open Intranet."
fi

#show the welcome message
echo -e "\nCongratulations, you’ve installed Open Intranet!
         Next steps:
         \u2022 Run “ddev launch” to install Open Intranet in a browser
         \u2022 Run “drush site-install openintranet install_configure_form.enable_demo_content=1” to install Open Intranet in a terminal
         \u2022 Get support: https://www.drupal.org/project/issues/openintranet  -> “Issues for Open Intranet”\n"
