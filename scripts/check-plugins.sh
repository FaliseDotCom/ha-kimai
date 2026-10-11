#!/usr/bin/env bash
# Checks the bundled Kimai plugins for mistakes that would expose a route.
#
# Kimai's access_control rule for /{_locale}/ never matches, because Symfony does
# not expand the placeholder, so a plugin route is only protected by its
# controller's own #[IsGranted]. This script fails when a controller class has no
# class-level #[IsGranted] attribute. scripts/mirror-plugins.sh runs it before
# publishing.
#
# Usage: scripts/check-plugins.sh

set -euo pipefail

REPO_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
readonly REPO_ROOT
readonly BUNDLES_DIR=kimai/bundles

# Prints whether a PHP file has an #[IsGranted] attribute among the lines
# directly above its class declaration.
has_class_level_is_granted()
{
  awk '
    /^#\[/ { attributes = attributes $0 "\n"; next }
    /^(final |abstract )?class / { if ( attributes ~ /#\[IsGranted\(/ ) { found = 1 } exit }
    { attributes = "" }
    END { exit !found }
  ' "$1"
}

main()
{
  cd "$REPO_ROOT"

  local failed=0
  local controller
  for controller in "$BUNDLES_DIR"/*/Controller/*.php
  do
    if ! has_class_level_is_granted "$controller"
    then
      echo "ERROR: $controller has no class-level #[IsGranted], so its routes are public." >&2
      failed=1
    fi
  done

  if [ "$failed" -ne 0 ]
  then
    exit 1
  fi

  echo "All plugin controllers require a permission."
}

main "$@"
