#!/bin/bash

# Check if this site has WP installed first
wpCore=null;
echo 'Checking for Wordpress...'
if wp core is-installed; then
    # WP is installed. Let's do some things we should only do in a confirmed WP environment.
    echo 'Wordpress is installed'
    wpCore=true;
else
    # Fallback if WP is not installed.
    echo 'You are in the wrong directory. Move in to your WordPress directory and try running the script again.'
    wpCore=false;
fi

# If WP is installed, proceed
if wpCore=true; then
  # Check if working tree is clean
  if [[ `git status --porcelain` ]]; then
    echo 'Commit, stash, or discard unstaged changes, then run again.'
  else

    # Cut plugin branch from active branch
    echo "Input the Jira ticket number to be included the branch name:"
    read ticketNum
    git branch feature/$ticketNum-relias-plugins
    git checkout feature/$ticketNum-relias-plugins

    # Declare plugins to update
    # NOTE: Modify this array if you want to add or remove plugins (not all plugins on relias.com are listed here)
    plugins=(
      "acf-hide-layout"
      "advanced-custom-fields"
      "autoptimize"
      "classic-editor"
      "wordpress-seo"
    )

    echo "Checking for updates..."
    for plugin in "${plugins[@]}"; do
        echo "Updating $plugin..."
        wp plugin update $plugin
        git add .
        git commit -m "$ticketNum: updates plugin - $plugin"
    done

    # Show updated list for any missed plugins
    wp plugin list
    echo "Check above to see if any plugins were not updated. You'll need to update these manually."
  fi
fi
