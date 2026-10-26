# Releasing

The app version always equals the Kimai version it contains, so users see the Kimai version
in Home Assistant.

## Updating to a new Kimai release

This repository does not contain Kimai's source code. The app is built from the official
Kimai Docker image, so updating Kimai means pointing the app at a newer image tag.

### With the update script

From the repository root, in Git Bash or any other Bash shell, with Docker running:

```bash
scripts/update-kimai.sh          # latest Kimai release
scripts/update-kimai.sh 2.68.0   # a specific release
```

The script checks that the image exists for both architectures, refuses downgrades,
updates the version in all files, adds a changelog entry and builds the image. It does not
commit anything. Then do steps 2, 5 and 6 below: read the release notes, test the upgrade,
and commit, tag and push.

### By hand

1. Check the new release on [Docker Hub](https://hub.docker.com/r/kimai/kimai2/tags), for
   example `2.68.0`, and confirm it lists both `linux/amd64` and `linux/arm64`:

   ```bash
   docker buildx imagetools inspect kimai/kimai2:2.68.0
   ```

2. Read the [Kimai release notes](https://github.com/kimai/kimai/releases) for changes to
   the Docker image, especially its `/entrypoint.sh`, environment variables, base Debian
   version or bundled PHP version.
3. Update the version in three places:
   - the `FROM` line in `kimai/Dockerfile`
   - `version` in `kimai/config.yaml`
   - the Kimai badge in `README.md`
4. Add an entry at the top of `kimai/CHANGELOG.md` that links to the Kimai release notes.
5. Test as described in [Development](development.md). Always test an upgrade from the
   previous release, not only a fresh install: start the old version with data, then the new
   version on the same `test/data` folder.
6. Commit, tag the commit with the version (`git tag 2.68.0`), and push both. Home Assistant
   offers the update to users the next time it checks the repository.

## App-only changes

For a change to the app itself without a new Kimai release, add a fourth version segment:
`2.67.0` becomes `2.67.0.1`, then `2.67.0.2`. The next Kimai release resets it.

## Publishing the plugins

The plugins in `kimai/bundles/` are developed only in this repository. Each one is also
published to its own GitHub repository, a mirror of its folder, so it can be installed in
any Kimai installation and listed in the
[Kimai Store](https://www.kimai.org/documentation/store.html). Each mirror has its own issue
tracker, and accepts pull requests that are then applied here (see
[Pull requests on a mirror](#pull-requests-on-a-mirror)):

| Folder | Mirror |
| --- | --- |
| `ReportingGraphsBundle` | [kimai-reporting-graphs-bundle](https://github.com/FaliseDotCom/kimai-reporting-graphs-bundle) |
| `QuickTimerBarBundle` | [kimai-quick-timer-bar-bundle](https://github.com/FaliseDotCom/kimai-quick-timer-bar-bundle) |
| `ShortTimeEntriesBundle` | [kimai-short-time-entries-bundle](https://github.com/FaliseDotCom/kimai-short-time-entries-bundle) |
| `InlineTimesheetEditBundle` | [kimai-inline-timesheet-edit-bundle](https://github.com/FaliseDotCom/kimai-inline-timesheet-edit-bundle) |
| `TimesheetExtrasBundle` | [kimai-timesheet-extras-bundle](https://github.com/FaliseDotCom/kimai-timesheet-extras-bundle) |
| `OdtTemplatesBundle` | [kimai-odt-templates-bundle](https://github.com/FaliseDotCom/kimai-odt-templates-bundle) |
| `ExportExtrasBundle` | [kimai-export-extras-bundle](https://github.com/FaliseDotCom/kimai-export-extras-bundle) |

The mirror's name is the package name in the plugin's `composer.json`, which also points
its `homepage` and `support` links at the mirror. Never commit to a mirror, also not by
merging a pull request there: the next publish would refuse to overwrite it.

1. When a plugin changes, raise its `version` in `composer.json` (`1.2.0` to `1.3.0` for new
   features, `1.2.1` for fixes) in the same release as the app.
2. Push. The **Publish plugins** workflow (`.github/workflows/publish-plugins.yml`) runs
   `scripts/mirror-plugins.sh` on every push to `main` that changes `kimai/bundles/`.

For each plugin, the script uses `git subtree split` to extract the folder's history, pushes
it to the mirror's `main` branch, and tags the `composer.json` version when the mirror does
not have it yet. With the [GitHub CLI](https://cli.github.com/) it also creates a GitHub
release for a new version, with a zip whose top folder is the plugin's folder name, so it
unzips straight into Kimai's `var/plugins/`. It stops when `scripts/check-plugins.sh` finds a
controller without a permission check. When a plugin changed since its last tag but its
version was not raised, the script pushes `main` and points out that the changes are not
released yet.

To publish by hand, start the workflow from the **Actions** tab, or run the script from the
repository root in Git Bash, with `gh` installed and logged in (`winget install GitHub.cli`,
then `gh auth login`). By hand, only committed work is published; the script stops when
`kimai/bundles/` has uncommitted changes.

```bash
scripts/mirror-plugins.sh                       # all plugins
scripts/mirror-plugins.sh QuickTimerBarBundle   # one plugin
```

A new plugin needs an empty repository on GitHub, named after its `composer.json` package,
with issues turned on, before its first publish, and the workflows' token needs access to it
(see [The plugin repositories token](#the-plugin-repositories-token)). Each plugin folder
carries its own `LICENSE`, and its `README.md` must work on its own in the mirror: link to
files outside the folder with full GitHub URLs. The README ends with an **Other plugins**
section listing the other plugins; add a new plugin to that list in every README.

### Pull requests on a mirror

A mirror's pull request is applied here instead of merged there. The **Plugin pull
requests** workflow (`.github/workflows/plugin-pull-requests.yml`) checks the mirrors every
hour, with `scripts/plugin-pull-requests.sh`:

1. A new pull request on a mirror is applied to the plugin's folder with `git am`, which
   keeps its commits and their author, on a `plugin-pr/<mirror>-<number>` branch here. The
   workflow opens a pull request here for it, labels the original `imported`, and comments
   there with a link.
2. Review the pull request here and merge it. Squash merging is turned off for this
   repository, because it would make you the author of the contributor's work: **Create a
   merge commit** and **Rebase and merge** keep their commits and authorship.
3. Closing it, merged or not, closes the original with a comment and deletes the branch. A
   merge reaches the mirror through the **Publish plugins** workflow; raise the plugin's
   version to release it.

A pull request that does not apply to the current folder gets the `import-failed` label, a
comment, and a failed workflow run, which GitHub reports by email. Apply it by hand, then
close it with a link to the commit:

```bash
gh pr diff 12 --patch --repo FaliseDotCom/kimai-quick-timer-bar-bundle \
  | git am --3way --directory=kimai/bundles/QuickTimerBarBundle
```

GitHub turns off scheduled workflows after 60 days without activity in the repository;
enable it again on the **Actions** tab.

### The plugin repositories token

Both workflows push to and comment on the mirrors, which the workflow's own token cannot.
They use a fine-grained personal access token in the `PLUGIN_REPOS_TOKEN` repository
secret:

1. On GitHub, open **Settings** > **Developer settings** > **Personal access tokens** >
   **Fine-grained tokens** > **Generate new token**.
2. **Repository access:** **Only select repositories**: this repository and every mirror.
3. **Permissions:** **Contents**, **Issues** and **Pull requests**, all **Read and write**.
4. Store it, in Git Bash: `gh secret set PLUGIN_REPOS_TOKEN --repo FaliseDotCom/ha-kimai`
   and paste the token.

GitHub emails a week before the token expires. Generate a new one the same way, or use
**Regenerate token** on its page, and store it again. Without the secret, the publish
workflow fails and the hourly check skips with a warning.

## Changes to watch for

- **New base Debian release.** MariaDB moves to a new major version. `run.sh` runs
  `mariadb-upgrade` on every start, but test an upgrade with real data.
- **New environment variables or a changed entrypoint.** `run.sh` relies on
  `/entrypoint.sh` creating the administrator, generating `APP_SECRET` in `var/data`, and
  ending with `exec apache2`.
- **Supervisor changes.** Check the
  [app configuration reference](https://developers.home-assistant.io/docs/apps/configuration)
  for deprecations, and watch the Supervisor log for warnings about this app.
