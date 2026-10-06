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
published to its own GitHub repository, a read-only mirror of its folder, so it can be
installed in any Kimai installation and listed in the
[Kimai Store](https://www.kimai.org/documentation/store.html):

| Folder                 | Mirror                                                                               |
| ---------------------- | ------------------------------------------------------------------------------------ |
| `ReportingBundle`      | [kimai-reporting-bundle](https://github.com/FaliseDotCom/kimai-reporting-bundle)           |
| `TimerBarBundle`       | [kimai-timerbar-bundle](https://github.com/FaliseDotCom/kimai-timerbar-bundle)             |
| `UiImprovementsBundle` | [kimai-ui-improvements-bundle](https://github.com/FaliseDotCom/kimai-ui-improvements-bundle) |

The mirror's name is the package name in the plugin's `composer.json`. Never commit to a
mirror: the next publish would refuse to overwrite it.

1. When a plugin changes, raise its `version` in `composer.json` (`1.2.0` to `1.3.0` for new
   features, `1.2.1` for fixes) in the same release as the app.
2. After pushing the app release, run from the repository root, in Git Bash:

   ```bash
   scripts/mirror-plugins.sh                  # all plugins
   scripts/mirror-plugins.sh TimerBarBundle   # one plugin
   ```

   For each plugin, the script uses `git subtree split` to extract the folder's history,
   pushes it to the mirror's `main` branch, and tags the `composer.json` version when the
   mirror does not have it yet. Only committed work is published; the script stops when
   `kimai/bundles/` has uncommitted changes.
3. With the [GitHub CLI](https://cli.github.com/) installed and logged in
   (`winget install GitHub.cli`, then `gh auth login`), the script also creates a GitHub
   release for a new version, with a zip whose top folder is the plugin's folder name, so it
   unzips straight into Kimai's `var/plugins/`. Without it the script only pushes and tags;
   run it again once `gh` is set up to add the missing releases.

When a plugin changed since its last tag but its version was not raised, the script pushes
`main` and points out that the changes are not released yet.

A new plugin needs an empty repository on GitHub, named after its `composer.json` package,
before its first publish. Each plugin folder carries its own `LICENSE`, and its `README.md`
must work on its own in the mirror: link to files outside the folder with full GitHub URLs.

## Changes to watch for

- **New base Debian release.** MariaDB moves to a new major version. `run.sh` runs
  `mariadb-upgrade` on every start, but test an upgrade with real data.
- **New environment variables or a changed entrypoint.** `run.sh` relies on
  `/entrypoint.sh` creating the administrator, generating `APP_SECRET` in `var/data`, and
  ending with `exec apache2`.
- **Supervisor changes.** Check the
  [app configuration reference](https://developers.home-assistant.io/docs/apps/configuration)
  for deprecations, and watch the Supervisor log for warnings about this app.
