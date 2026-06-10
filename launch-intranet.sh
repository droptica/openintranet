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

if ! command -v ddev >/dev/null; then
  echo "DDEV needs to be installed. Visit https://ddev.com/get-started for instructions."
  exit 1
fi

NAME=$(basename "$PWD" | tr '_' '-')

# If there are any other DDEV projects in this system with this name, add a numeric suffix.
declare -i n=$(ddev list | grep --count "$NAME")
if [ $n -gt 0 ]; then
  NAME=$NAME-$(expr $n + 1)
fi

# Configure DDEV if not already done. Check for the config file, not the
# directory: a clone may contain a .ddev/ directory without a config.yaml,
# and skipping `ddev config` then makes `ddev start` fail.
test -f .ddev/config.yaml || ddev config --project-type=drupal10 --docroot=web --php-version=8.3 --ddev-version-constraint=">=1.24.0" --project-name="$NAME"

# Prepare all project files BEFORE `ddev start`: with Mutagen (the default on
# macOS) the container only sees host files after a sync cycle, so files
# copied after the start may not be inside the container yet when the first
# `drush site-install` runs ("openintranet_theme" is not a known module or
# theme). `ddev start` waits for the initial sync, so anything created here
# is guaranteed to be visible in the container.

# Copy the DDEV commands to the project.
cp -r ddev_commands/* .ddev/commands/

# Copy the starter theme to the project. The parent directory does not exist
# on a fresh clone (composer creates web/themes later), so create it first.
mkdir -p web/themes/custom
cp -R starter-theme/openintranet_theme web/themes/custom/

# Set up the private file system BEFORE any drush run. The install profile
# patches settings.php too, but editing the file mid-install does not affect
# the already-running `drush site-install` process (single bootstrap), so the
# private:// stream wrapper never registers and the demo content import fails
# copying demo.pdf. Patching the file here, before any Drupal process starts,
# makes the first `drush site-install` work.
SETTINGS_FILE=web/sites/default/settings.php
if [ -f "$SETTINGS_FILE" ] && grep -q "^# \$settings\['file_private_path'\] = '';" "$SETTINGS_FILE"; then
  sed "s|^# \$settings\['file_private_path'\] = '';|\$settings['file_private_path'] = 'sites/private_files';|" "$SETTINGS_FILE" > "$SETTINGS_FILE.tmp" \
    && mv "$SETTINGS_FILE.tmp" "$SETTINGS_FILE"
fi
mkdir -p web/sites/private_files

# Start your engines.
ddev start
# Install dependencies if not already done.
test -f composer.lock || ddev composer install

ask_yes_no() {
    while true; do
        read -p "$1 [y/n]: " yn
        case $yn in
            [Yy]* ) return 0;;
            [Nn]* ) return 1;;
            * ) echo "Please answer yes (y) or no (n).";;
        esac
    done
}

# Ask about removing installation files (skip if -y flag, keep files for development)
if [ "$AUTO_YES" = true ]; then
    echo "Keeping installation files for open source development (auto mode)."
elif ask_yes_no "Would you like to remove installation files and directories (.git, ddev_commands, starter-theme)?"; then
    echo "Removing installation files..."
    rm -rf .git
    rm -rf ddev_commands
    rm -rf starter-theme

    # Use project .gitignore template for new projects
    if [ -f .gitignore.project ]; then
        echo "Setting up .gitignore for new project..."
        cp .gitignore.project .gitignore
        rm -f .gitignore.project
        echo "Updated .gitignore for new project development (custom theme will be tracked)."
    fi

    # Only ask about git init if files were removed
    if ask_yes_no "Would you like to initialize a new git repository?"; then
        echo "Initializing new git repository..."
        git init
    fi
else
    echo "Keeping installation files for open source development."
    echo "Using default .gitignore configured for contributing to Open Intranet."
fi

#show the welcome message
echo -e "\nCongratulations, you’ve installed Open Intranet!
         Next steps:
         \u2022 Run “ddev launch” to install Open Intranet in a browser
         \u2022 Run “drush site-install openintranet install_configure_form.enable_demo_content=1” to install Open Intranet in a terminal
         \u2022 Get support: https://www.drupal.org/project/issues/openintranet  -> “Issues for Open Intranet”\n"
