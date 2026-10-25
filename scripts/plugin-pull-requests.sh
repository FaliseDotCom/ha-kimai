#!/usr/bin/env bash
# Brings pull requests on the plugins' own repositories into this repository.
# Those repositories only receive code from here (see mirror-plugins.sh), so a
# pull request there is applied here instead of merged there.
#
# import  For each open pull request on a plugin repository that has neither
#         the imported nor the import-failed label: applies its commits to the
#         plugin's folder with git am, which keeps their authors, pushes them to
#         a plugin-pr/<repository>-<number> branch, opens a pull request here,
#         and labels and answers the original. A pull request that does not
#         apply gets the import-failed label, and the script fails, so the
#         workflow run sends a notification.
# finish  After a plugin-pr/ pull request here is closed: closes the original
#         with a comment that says how it ended, and deletes the branch.
#
# The Plugin pull requests workflow runs both. Needs the GitHub CLI (gh), with
# write access to this repository and the plugin repositories, and a Git
# identity for the commits that git am writes.
#
# Usage: scripts/plugin-pull-requests.sh import
#        scripts/plugin-pull-requests.sh finish <branch> <merged: true|false> <url>

set -euo pipefail

REPO_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
readonly REPO_ROOT
readonly BUNDLES_DIR=kimai/bundles
readonly BRANCH=main
readonly BRANCH_PREFIX=plugin-pr/
readonly LABEL_IMPORTED=imported
readonly LABEL_FAILED=import-failed

# shellcheck source-path=SCRIPTDIR source=plugin-repos.sh
source "$REPO_ROOT/scripts/plugin-repos.sh"

fatal()
{
  echo "ERROR: $*" >&2
  exit 1
}

# Creates the two labels in a plugin repository, or updates them.
ensure_labels()
{
  local app
  app=$(this_repo)
  gh label create "$LABEL_IMPORTED" --repo "$1" --force --color 0e8a16 \
    --description "Applied in $app" > /dev/null
  gh label create "$LABEL_FAILED" --repo "$1" --force --color d93f0b \
    --description "Could not be applied in $app automatically" > /dev/null
}

# The text of the pull request opened here for one from a plugin repository.
import_body()
{
  local url="$1"
  local author="$2"
  local description="$3"

  printf '%s\n' \
    "Imported from $url by @$author, with its commits applied to \`$BUNDLES_DIR/$4/\`." \
    '' \
    'Merge with **Rebase and merge** or **Create a merge commit**, so the commits keep their' \
    'author. Closing this pull request closes the original too.' \
    '' \
    '## Original description' \
    '' \
    "$description"
}

# Labels a pull request that does not apply, and tells its author.
mark_failed()
{
  local repo="$1"
  local number="$2"

  gh pr edit "$number" --repo "$repo" --add-label "$LABEL_FAILED" > /dev/null
  gh pr comment "$number" --repo "$repo" --body "Thanks! This plugin is developed in [$(this_repo)](https://github.com/$(this_repo)), where this pull request could not be applied automatically. It will be taken over by hand." > /dev/null
}

# Applies one pull request from a plugin repository on its own branch here and
# opens a pull request for it. Returns 1 when its commits do not apply.
import_pull_request()
{
  local repo="$1"
  local bundle="$2"
  local number="$3"
  local branch="$BRANCH_PREFIX${repo#*/}-$number"

  if [ -n "$(git -C "$REPO_ROOT" ls-remote --heads origin "$branch")" ]
  then
    echo "  #$number: $branch already exists."
    return 0
  fi

  git -C "$REPO_ROOT" switch --quiet --force-create "$branch" "origin/$BRANCH"
  if ! gh pr diff "$number" --repo "$repo" --patch | git -C "$REPO_ROOT" am --quiet --directory="$BUNDLES_DIR/$bundle"
  then
    git -C "$REPO_ROOT" am --abort 2> /dev/null || true
    mark_failed "$repo" "$number"
    echo "  #$number: does not apply; labelled $LABEL_FAILED." >&2
    return 1
  fi

  git -C "$REPO_ROOT" push --quiet origin "$branch"

  local title url author description created
  title=$(gh pr view "$number" --repo "$repo" --json title --jq .title)
  url=$(gh pr view "$number" --repo "$repo" --json url --jq .url)
  author=$(gh pr view "$number" --repo "$repo" --json author --jq .author.login)
  description=$(gh pr view "$number" --repo "$repo" --json body --jq .body)
  created=$(gh pr create --repo "$(this_repo)" --base "$BRANCH" --head "$branch" --title "$title" \
    --body "$(import_body "$url" "$author" "$description" "$bundle")")

  gh pr edit "$number" --repo "$repo" --add-label "$LABEL_IMPORTED" > /dev/null
  gh pr comment "$number" --repo "$repo" --body "Thanks! This plugin is developed in [$(this_repo)](https://github.com/$(this_repo)), so this pull request continues in $created, with your commits and authorship. This one is closed when that one is." > /dev/null
  echo "  #$number: $created"
}

# Imports every open pull request on the plugin repositories that has not been
# handled yet. Fails when one of them does not apply.
import_all()
{
  [ -z "$(git -C "$REPO_ROOT" status --porcelain)" ] || fatal "Commit or stash your changes first."

  local owner start
  owner=$(repo_owner)
  [ -n "$owner" ] || fatal "The origin remote is not a GitHub repository."
  start=$(git -C "$REPO_ROOT" symbolic-ref --quiet --short HEAD || git -C "$REPO_ROOT" rev-parse HEAD)
  git -C "$REPO_ROOT" fetch --quiet origin "$BRANCH"

  local failed=0
  local bundle repo number
  for bundle in $(plugin_folders)
  do
    repo=$(plugin_repo "$bundle" "$owner")
    echo "$repo"
    ensure_labels "$repo"
    for number in $(gh pr list --repo "$repo" --state open --search "-label:$LABEL_IMPORTED -label:$LABEL_FAILED" --json number --jq '.[].number')
    do
      import_pull_request "$repo" "$bundle" "$number" || failed=1
    done
  done

  git -C "$REPO_ROOT" checkout --quiet "$start"
  return "$failed"
}

# Closes the original of a closed plugin-pr/ pull request and deletes its branch.
finish()
{
  local branch="$1"
  local merged="$2"
  local url="$3"
  local rest="${branch#"$BRANCH_PREFIX"}"
  local number="${rest##*-}"
  local name="${rest%-*}"

  [[ "$branch" == "$BRANCH_PREFIX"* && "$number" =~ ^[0-9]+$ && -n "$name" ]] || fatal "$branch is not a $BRANCH_PREFIX<repository>-<number> branch."

  local message="Closed without merging in $url; see the discussion there."
  if [ "$merged" = true ]
  then
    message="Merged in $url. It reaches this repository's main branch within minutes, and the plugin's next release."
  fi

  local repo
  repo="$(repo_owner)/$name"
  # The author may have closed the original already.
  if [ "$(gh pr view "$number" --repo "$repo" --json state --jq .state)" = OPEN ]
  then
    gh pr close "$number" --repo "$repo" --comment "$message" > /dev/null
    echo "Closed $repo#$number."
  fi
  gh api --method DELETE "repos/$(this_repo)/git/refs/heads/$branch" > /dev/null
}

main()
{
  case "${1:-}" in
    import)
      import_all
      ;;
    finish)
      [ $# -eq 4 ] || fatal "Usage: $0 finish <branch> <merged: true|false> <url>"
      finish "$2" "$3" "$4"
      ;;
    *)
      fatal "Usage: $0 import | finish <branch> <merged: true|false> <url>"
      ;;
  esac
}

main "$@"
