# Development

How the Kimai app is put together, and how to test a change before it reaches users.

## Repository layout

```text
repository.yaml              App repository definition, read by the Supervisor
kimai/
  config.yaml                App definition: version, port, options and schema
  Dockerfile                 Upstream Kimai image plus jq and MariaDB
  run.sh                     Starts MariaDB and Kimai, and stops them in order
  kimai-console              Runs Kimai console commands inside the running app
  mariadb.cnf                MariaDB settings, tuned for small devices
  DOCS.md                    User documentation, shown on the Documentation tab
  README.md                  Short description, shown in the app store
  CHANGELOG.md               Release notes, shown when an update is available
  icon.png, logo.png         Artwork, taken from Kimai's own touch icon
  translations/              Option names and descriptions (en, nl)
  bundles/                   Kimai plugins that ship with the app
    ReportingGraphsBundle/      Reporting extras: summary report, and charts on the user reports
    QuickTimerBarBundle/        Quick start bar below the top bar
    ShortTimeEntriesBundle/     Short time and duration entry in Kimai's forms
    InlineTimesheetEditBundle/  Editing records directly in the record list
.devcontainer/, .vscode/     Home Assistant development environment
.docs/                       Documentation for contributors
scripts/update-kimai.sh      Bumps the app to a new Kimai release
scripts/mirror-plugins.sh    Publishes each plugin to its own read-only mirror repository
```

## How the app works

The image extends the official [`kimai/kimai2`](https://hub.docker.com/r/kimai/kimai2)
Apache image, pinned to an exact release, and adds MariaDB from Debian. `run.sh` is the
container command; Docker's `init` (enabled with `init: true`) runs it as a child of a
minimal init process, which forwards signals and reaps zombies.

On start, `run.sh`:

1. Reads the options from `/data/options.json` with `jq`, and validates the administrator
   email and password length.
2. Writes the `TZ` time zone that the Supervisor passes in to a PHP ini file, so new users
   default to the Home Assistant time zone.
3. Replaces Kimai's `var/data` with a link to `/data/kimai/data`, and links
   `/config/local.yaml` into Kimai's configuration when it exists.
4. Rebuilds Kimai's `var/plugins` folder from links: one to each plugin bundled in
   `/opt/kimai-bundles`, then one to each folder in `/config/plugins`. A user's folder with
   the same name replaces the bundled plugin, which is also how users turn a bundled plugin
   off (a folder holding only a `.disabled` file).
5. Starts MariaDB on `127.0.0.1:3306` with its data in `/data/mysql`, initialising the data
   directory on first start and running `mariadb-upgrade` on every start. MariaDB runs in
   its own session (`setsid`), so a stop signal sent to the whole process group cannot
   stop it before Kimai.
6. Creates the `kimai` database and user. The user's password is generated once and kept
   in `/data/db_password`.
7. Refuses to continue when Kimai has no users and no administrator credentials are set.
8. Exports the Kimai environment variables, saves them in `/run/kimai-app/console.env` for
   `kimai-console`, and starts the upstream `/entrypoint.sh` in the background. That script installs or migrates the database, creates the administrator,
   generates `APP_SECRET` (persisted in `var/data/.appsecret`), and finally replaces itself
   with Apache.
9. Waits. On `SIGTERM` or `SIGINT` it stops Apache, then shuts MariaDB down cleanly. If
   either process exits on its own, it stops the other and exits with an error, so the
   Supervisor watchdog restarts the app.

The Dockerfile sets `STOPSIGNAL SIGTERM`, because the upstream image uses `SIGWINCH` to stop
Apache gracefully and `run.sh` would otherwise never see the stop request. `timeout: 60`
in `config.yaml` gives MariaDB time to flush before Docker kills the container.

The Dockerfile also defines a health check that tests whether Apache accepts connections
on port 8001. Apache only starts once Kimai is installed or migrated, so the Supervisor shows
the app as *starting* until Kimai is ready, and its watchdog restarts the app if the check
fails after the 10-minute start period. It checks the port rather than a page, because a
page request fails when `trusted_hosts` does not include `127.0.0.1`. Do not replace it with
`HEALTHCHECK NONE`: the image then still carries health check settings, so the Supervisor
keeps waiting for a result that never comes and shows the app as starting forever.

### Console commands

`docker exec` starts a process that does not inherit `run.sh`'s environment, and the
upstream image declares `DATABASE_URL` as an empty environment variable, which overrides
any value in Kimai's `.env` files. A plain `bin/console` call therefore cannot reach the
database. `kimai-console` loads the settings that `run.sh` saved in
`/run/kimai-app/console.env` (readable by root only) and runs `bin/console` as `www-data`,
so files it creates in Kimai's cache keep the right owner.

### Storage

| Path in the container | Contents                                  | In backups |
| --------------------- | ----------------------------------------- | ---------- |
| `/data/mysql`         | MariaDB data directory                    | Yes        |
| `/data/db_password`   | Password of the `kimai` database user     | Yes        |
| `/data/kimai/data`    | Kimai `var/data`: uploads, `APP_SECRET`   | Yes        |
| `/data/options.json`  | App options, written by the Supervisor    | Yes        |
| `/config/local.yaml`  | Optional Kimai configuration override     | Yes        |
| `/config/plugins`     | Kimai plugins                             | Yes        |

`/config` is the app configuration folder (`map: app_config:rw`), which users can reach
through the `app_configs` Samba share. `backup: cold` stops the app during a backup so the
MariaDB files are consistent.

### Design decisions

**MariaDB inside the container.** Using the official MariaDB app would save a little
memory, but makes installation a two-app job and ties Kimai's data to a database that other
apps share. Bundling keeps the app self-contained and its backup complete.

**No ingress.** Home Assistant's ingress serves an app under a per-session sub-path
(`/api/hassio_ingress/<token>/`). Kimai is a Symfony application that builds absolute URLs
and asset paths from the web root, so it breaks under that prefix. The app publishes port
8001 and sets `webui` instead, which gives the **Open web UI** button. `DOCS.md` explains
how to add a sidebar entry with a Webpage dashboard; Kimai sends no `X-Frame-Options` header,
so it can be framed.

**Cold backups.** A hot backup would need a `backup_pre` dump and a restore path that
imports it. Kimai's database is small, so a short stop is the simpler and safer trade.

**Locally built image.** The Supervisor builds the Dockerfile on the user's device; there is
no `image` key in `config.yaml`. Publishing prebuilt images to a container registry from CI
would make installation faster and is a possible improvement.

**Upstream versioned tags.** `kimai/kimai2:<version>` is the Apache variant and is
published for `amd64` and `arm64`, which map to the Supervisor's `amd64` and `aarch64`.

## Bundled plugins

The plugins in `kimai/bundles/` are ordinary Kimai plugins (Symfony bundles in the
`KimaiPlugin\` namespace) and also work on a Kimai installed without Home Assistant; each
has its own `README.md`. They use this repository's code style (two-space indentation, braces
on their own line, spaces inside parentheses), not Kimai's.

They only use Kimai's extension points and services:

| Plugin           | Hooks into                                    | Uses                                                    |
| ---------------- | --------------------------------------------- | ------------------------------------------------------- |
| `ReportingGraphsBundle` | `ReportingEvent`, adds a report; `ThemeEvent::CONTENT_START` on the three `report_user_*` routes | `DateRangeType`, `UserType`, Kimai's Chart.js build |
| `QuickTimerBarBundle` | `ThemeEvent::CONTENT_START` on every page     | `TimesheetService` (create, validate, save, restart, stop), project, activity and tag queries |
| `ShortTimeEntriesBundle` | `ThemeEvent::JAVASCRIPT` on every page | Kimai's form markup: `input[data-timepicker]` and `input.duration-input` |
| `InlineTimesheetEditBundle` | `ThemeEvent::JAVASCRIPT` and `ThemeEvent::STYLESHEET` on the record lists; `UserPreferenceEvent` | The list markup (`tr[data-href]` with its `modal-ajax-form` class, `col_*` cell classes), `TimesheetService` (validate, save), the tracking mode, and project, activity and tag queries |

The quick start bar is rendered at the top of the page content and hides Kimai's own start
button (`.ticktac`). Its script moves it into a row at the start of `.page-wrapper`, directly
below the top bar; Kimai offers no event for that place. Kimai's entry tables reload by
fetching the whole page and swapping in its content area, which brings along a fresh copy of
the bar; the script removes that copy on `kimai.reloadedContent`. The inline editing script
listens for the same event to mark the reloaded rows, and dispatches
`kimai.timesheetUpdate` after a save so Kimai reloads the list. The charts on the user
reports are rendered the same way and moved into the report's `#reporting-content`, below
its filters.

Both respect Kimai's permissions: the summary report needs `report:user`, and `report:other`
to pick other users, whose list comes from Kimai's own team-aware user query; amounts need
the `view_rate_*` permissions. The timer bar needs `create_own_timesheet`, only offers
projects and activities from Kimai's form queries, checks the posted IDs against those
lists, and starts and stops records through `TimesheetService`, so Kimai's validation,
rounding and running-record limit apply.

Each plugin serves its own script and stylesheet through a controller route
(`/…/assets/{name}`, limited to a fixed list of files), because Kimai has no asset pipeline
for plugins. A version parameter based on the files' modification time busts browser
caches after an update.

`QuickTimerBarBundle` and `InlineTimesheetEditBundle` each carry `Service/QuickCreator.php`,
which creates projects, customers and activities from the "+" buttons; the copies differ only
in their namespace. `ShortTimeEntriesBundle` and `InlineTimesheetEditBundle` each carry an identical copy of
`Resources/public/input-parsing.js`, because a published plugin cannot load files from
another plugin. Change both copies together.

Each plugin folder is also published on its own, to a read-only mirror repository (see
[Releasing](releasing.md#publishing-the-plugins)). Keep a plugin's folder self-contained: its
own `LICENSE`, and links in its `README.md` to files outside the folder as full GitHub URLs.

Both declare `"require": 26700` (Kimai 2.67.0) in `composer.json`, the version they were
tested with. Raise it when a plugin starts using newer Kimai features.

## Testing a change

Work through these in order. Each catches problems the previous one cannot.

### 1. Lint

```bash
docker run --rm -v "$PWD/kimai:/mnt" koalaman/shellcheck:stable /mnt/run.sh /mnt/kimai-console
```

For the plugins, lint the PHP and run [PHPStan](https://phpstan.org/) at level 6 inside the
Kimai image, which provides Kimai's classes. Download `phpstan.phar` from the PHPStan
releases first:

```bash
docker run --rm --entrypoint bash \
  -v "$PWD/kimai/bundles/ReportingGraphsBundle:/opt/kimai/var/plugins/ReportingGraphsBundle" \
  -v "$PWD/phpstan.phar:/phpstan.phar" kimai/kimai2:2.67.0 -c \
  'cd /opt/kimai && php -d memory_limit=1G /phpstan.phar analyse --level 6 \
    -a vendor/autoload.php var/plugins/ReportingGraphsBundle'
```

Without Kimai's development dependencies the Symfony stubs are missing, so PHPStan reports
`argument.templateType` and `generics.notGeneric` on form classes. Kimai's own form classes
show the same two errors in this setup; anything else is a real finding.

### 2. Build and run with Docker

This checks the image and `run.sh` without Home Assistant. It needs Docker Desktop or any
Docker Engine.

```bash
docker build -t local/kimai-app kimai

mkdir -p test/data test/config
cat > test/data/options.json <<'JSON'
{
  "admin_email": "admin@example.com",
  "admin_password": "changeme123",
  "trusted_hosts": "",
  "trusted_proxies": "127.0.0.1,172.30.32.0/23"
}
JSON

docker run --rm --init --name kimai-test -p 8001:8001 -e TZ=Europe/Amsterdam \
  -v "$PWD/test/data:/data" -v "$PWD/test/config:/config" local/kimai-app
```

Open <http://localhost:8001> and log in as `admin` / `changeme123`. Stop with
`docker stop kimai-test`; the log should end with `mariadbd: Shutdown complete`. The `test/`
folder is ignored by Git.

To check the `aarch64` build on an `amd64` machine (slow, uses emulation):

```bash
docker buildx build --platform linux/arm64 -t local/kimai-app:arm64 kimai
```

Things worth checking after a change to `run.sh`:

- A fresh start without `admin_email` stops with the *No Kimai users exist yet* message.
- A second start with both admin options cleared still works.
- `docker stop` shuts MariaDB down cleanly, and a restart after
  `docker exec kimai-test pkill -KILL mariadbd` recovers.
- A request with an unlisted `Host` header returns 400 when `trusted_hosts` is set.

### 3. Run in a development Home Assistant

The repository includes the official Home Assistant development container, which runs a
complete Home Assistant with a Supervisor inside Docker. This tests the parts plain Docker
cannot: `config.yaml`, the options form, translations, the **Open web UI** button and the
watchdog.

1. Install the VS Code *Dev Containers* extension and start Docker Desktop.
2. Open the repository in VS Code and select **Reopen in Container**.
3. Run the task **Start Home Assistant** (**Terminal** > **Run Task**).
4. Open <http://localhost:7123>, complete onboarding, and go to **Settings** > **Apps** >
   **Install app**. Kimai is listed under **Local apps**.

Port 8001 is forwarded from the development container, so **Open web UI** works from the
same computer. To test from a phone, use the Companion app on the same network and enter
`http://<your-computer-ip>:7123` as the server address; Windows may ask to allow the ports
through the firewall.

### 4. Install on a real Home Assistant

1. Install the **Samba share** or **Advanced SSH & Web Terminal** app.
2. Copy the `kimai/` folder into the `local_apps` share (the `/addons` folder over SSH).
3. Go to **Settings** > **Apps** > **Install app**, open the three-dots menu and select
   **Check for updates**. Kimai appears under **Local apps**.
4. Install, configure and start it as described in `DOCS.md`.

A local copy is a separate app from the one installed from GitHub, with its own data, so
both can be installed side by side. After changing files, rebuild from the app's three-dots
menu (**Rebuild**).
