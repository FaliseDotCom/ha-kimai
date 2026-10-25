# shellcheck shell=bash
# Shared helpers for the scripts that work with the plugins' own GitHub
# repositories. Source it after setting REPO_ROOT and BUNDLES_DIR.
#
# Each folder in kimai/bundles/ has a repository named after the package in its
# composer.json, under the owner of this repository:
# falisedotcom/kimai-quick-timer-bar-bundle becomes
# github.com/<owner>/kimai-quick-timer-bar-bundle.

# The GitHub owner of this repository, from the origin remote.
repo_owner()
{
  git -C "$REPO_ROOT" remote get-url origin | sed -n 's|.*github\.com[:/]\([^/]*\)/.*|\1|p'
}

# The owner/name of this repository, from the origin remote.
this_repo()
{
  git -C "$REPO_ROOT" remote get-url origin | sed -n 's|.*github\.com[:/]\([^/]*/[^/]*\)$|\1|p' | sed 's|\.git$||'
}

# Reads a top-level string field from a plugin's composer.json.
composer_field()
{
  sed -n "s|^  \"$2\": *\"\([^\"]*\)\".*|\1|p" "$REPO_ROOT/$BUNDLES_DIR/$1/composer.json" | head -n 1
}

# The owner/name of a plugin's repository.
plugin_repo()
{
  local package
  package=$(composer_field "$1" name)
  echo "$2/${package#*/}"
}

# The folder names in kimai/bundles/, one per line.
plugin_folders()
{
  local dir
  for dir in "$REPO_ROOT/$BUNDLES_DIR"/*/
  do
    basename "$dir"
  done
}
