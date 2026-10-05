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

## Changes to watch for

- **New base Debian release.** MariaDB moves to a new major version. `run.sh` runs
  `mariadb-upgrade` on every start, but test an upgrade with real data.
- **New environment variables or a changed entrypoint.** `run.sh` relies on
  `/entrypoint.sh` creating the administrator, generating `APP_SECRET` in `var/data`, and
  ending with `exec apache2`.
- **Supervisor changes.** Check the
  [app configuration reference](https://developers.home-assistant.io/docs/apps/configuration)
  for deprecations, and watch the Supervisor log for warnings about this app.
