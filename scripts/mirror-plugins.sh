#!/usr/bin/env bash
# Publishes the bundled Kimai plugins to their own GitHub repositories. Each
# repository is a read-only mirror of one folder in kimai/bundles/: this script
# extracts the folder's history with git subtree split and pushes it to the
# mirror's main branch. When the version in the plugin's composer.json has no
# tag in the mirror yet, it tags that version and, with the GitHub CLI (gh),
# creates a release with a zip that unzips straight into Kimai's var/plugins/.
#
# Mirrors are named after the package in composer.json, under the owner of
# this repository: falisedotcom/kimai-timerbar-bundle becomes
# github.com/<owner>/kimai-timerbar-bundle. Create each one empty on GitHub
# before its first run. Pushes use your normal Git login.
#
# Usage: scripts/mirror-plugins.sh [bundle...]
#   bundle  Folder name in kimai/bundles/, such as TimerBarBundle. Defaults to all.

set -euo pipefail

REPO_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
readonly REPO_ROOT
readonly BUNDLES_DIR=kimai/bundles
readonly BRANCH=main
readonly CHANGELOG_URL_PATH=blob/main/kimai/CHANGELOG.md
readonly VERSION_PATTERN='^[0-9]+\.[0-9]+\.[0-9]+$'
readonly GH_WINDOWS='/c/Program Files/GitHub CLI/gh.exe'

fatal()
{
  echo "ERROR: $*" >&2
  exit 1
}

# The GitHub owner of this repository, from the origin remote.
repo_owner()
{
  git -C "$REPO_ROOT" remote get-url origin | sed -n 's|.*github\.com[:/]\([^/]*\)/.*|\1|p'
}

# The GitHub address of this repository, for links in release notes.
repo_url()
{
  git -C "$REPO_ROOT" remote get-url origin | sed 's|^git@github\.com:|https://github.com/|; s|\.git$||'
}

# Reads a top-level string field from a plugin's composer.json.
composer_field()
{
  sed -n "s|^  \"$2\": *\"\([^\"]*\)\".*|\1|p" "$REPO_ROOT/$BUNDLES_DIR/$1/composer.json" | head -n 1
}

# The commit a tag points to in a remote repository, or nothing.
remote_tag()
{
  git ls-remote --tags "$1" "refs/tags/$2" | cut -f 1
}

# The GitHub CLI: from PATH, or from its default Windows install folder, which
# terminals opened before the install do not have on their PATH yet.
find_gh()
{
  if command -v gh > /dev/null 2>&1
  then
    command -v gh
  elif [ -x "$GH_WINDOWS" ]
  then
    echo "$GH_WINDOWS"
  fi
}

GH=$(find_gh)
readonly GH

has_gh()
{
  [ -n "$GH" ]
}

# Creates a GitHub release with a zip whose top folder is the bundle name, so
# it unzips into var/plugins/ as Kimai expects.
create_release()
{
  local bundle="$1"
  local repo="$2"
  local version="$3"
  local commit="$4"
  local title="$5"

  if "$GH" release view "$version" --repo "$repo" > /dev/null 2>&1
  then
    return
  fi

  local workdir
  workdir=$(mktemp -d)
  local zip="$workdir/$bundle-$version.zip"

  git -C "$REPO_ROOT" archive --format=zip --prefix="$bundle/" -o "$zip" "$commit"
  "$GH" release create "$version" "$zip" --repo "$repo" --title "$title $version" \
    --notes "Unzip into Kimai's \`var/plugins/\` folder and run \`bin/console kimai:reload --env=prod\`. Changes are listed in the [changelog]($(repo_url)/$CHANGELOG_URL_PATH)."
  rm -rf "$workdir"
  echo "  Released $version."
}

mirror_bundle()
{
  local bundle="$1"
  local owner="$2"
  local prefix="$BUNDLES_DIR/$bundle"

  [ -f "$REPO_ROOT/$prefix/composer.json" ] || fatal "$prefix/composer.json not found."

  local package version title
  package=$(composer_field "$bundle" name)
  version=$(composer_field "$bundle" version)
  # The display name sits in the "kimai" block under "extra".
  title=$(sed -n '/"kimai":/,/}/ s|^ *"name": *"\([^"]*\)".*|\1|p' "$REPO_ROOT/$prefix/composer.json" | head -n 1)

  [[ "$version" =~ $VERSION_PATTERN ]] || fatal "$bundle: version '$version' in composer.json is not like 1.2.0."

  local repo="$owner/${package#*/}"
  local url="https://github.com/$repo.git"

  echo "$bundle -> $repo"
  git ls-remote "$url" > /dev/null 2>&1 || fatal "$repo does not exist or cannot be reached. Create it empty on GitHub first."

  local commit
  commit=$(git -C "$REPO_ROOT" subtree split --prefix="$prefix" HEAD 2> /dev/null)

  # No force: a mirror that was changed directly stops the push instead of losing that work.
  git -C "$REPO_ROOT" push --quiet "$url" "$commit:refs/heads/$BRANCH"
  echo "  Pushed $BRANCH."

  local tagged
  tagged=$(remote_tag "$url" "$version")
  if [ -z "$tagged" ]
  then
    git -C "$REPO_ROOT" push --quiet "$url" "$commit:refs/tags/$version"
    echo "  Tagged $version."
  elif [ "$tagged" != "$commit" ]
  then
    echo "  Note: changed since $version. Raise the version in $prefix/composer.json to release the changes."
  fi

  if has_gh
  then
    create_release "$bundle" "$repo" "$version" "$(remote_tag "$url" "$version")" "$title"
  fi
}

main()
{
  cd "$REPO_ROOT"

  local owner
  owner=$(repo_owner)
  [ -n "$owner" ] || fatal "The origin remote is not a GitHub repository."

  # Only committed work is published; a half-finished change must not reach a mirror.
  if [ -n "$(git status --porcelain -- "$BUNDLES_DIR")" ]
  then
    fatal "$BUNDLES_DIR has uncommitted changes. Commit or stash them first."
  fi

  local bundles=( "$@" )
  if [ ${#bundles[@]} -eq 0 ]
  then
    local dir
    for dir in "$BUNDLES_DIR"/*/
    do
      bundles+=( "$(basename "$dir")" )
    done
  fi

  local bundle
  for bundle in "${bundles[@]}"
  do
    mirror_bundle "$bundle" "$owner"
  done

  if ! has_gh
  then
    echo
    echo "The GitHub CLI (gh) is not installed, so no release zips were created."
    echo "Install it (winget install GitHub.cli), run gh auth login, and run this script again."
  fi
}

main "$@"
