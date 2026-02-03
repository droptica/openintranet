#!/usr/bin/env bash

# Parse command line arguments
# -y/--yes: Skip interactive prompts (for automated/CI usage)
AUTO_YES=false
if [ "$1" = "-y" ] || [ "$1" = "--yes" ]; then
    AUTO_YES=true
fi

if [ "$AUTO_YES" = false ]; then
    echo "WARNING! This script will remove untracked files (except the .ddev directory) and DROP the database."
    echo "Are you sure you want to proceed? [y/n]"
    read user_choice

    if [ "$user_choice" != "y" ]; then
        echo "Aborting."
        exit 1
    fi
fi

echo "Cleaning untracked files (excluding .ddev)..."
git clean -fdx --exclude=.ddev

echo "Running launch-intranet.sh..."
if [ "$AUTO_YES" = true ]; then
    ./launch-intranet.sh -y
else
    ./launch-intranet.sh
fi

echo "Dropping the database..."
ddev drush sql:drop -y

echo "Opening the project in your browser..."
ddev launch
