# Development

How the Kimai app is put together, and how to test a change before it reaches users.

## Repository layout

```text
repository.yaml              App repository definition, read by the Supervisor
kimai/
  config.yaml                App definition: version, port, options and schema
  Dockerfile                 Upstream Kimai image plus jq and MariaDB
  run.sh                     Starts MariaDB and Kimai, and stops them in order
  mariadb.cnf                MariaDB settings, tuned for small devices
  DOCS.md                    User documentation, shown on the Documentation tab
  README.md                  Short description, shown in the app store
  CHANGELOG.md               Release notes, shown when an update is available
  icon.png, logo.png         Artwork, taken from Kimai's own touch icon
  translations/              Option names and descriptions (en, nl)
.devcontainer/, .vscode/     Home Assistant development environment
.docs/                       Documentation for contributors
scripts/update-kimai.sh      Bumps the app to a new Kimai release
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
3. Replaces Kimai's `var/data` with a link to `/data/kimai/data` and `var/plugins` with a
   link to `/config/plugins`, and links `/config/local.yaml` into Kimai's configuration
   when it exists.
4. Starts MariaDB on `127.0.0.1:3306` with its data in `/data/mysql`, initialising the data
   directory on first start and running `mariadb-upgrade` on every start.
5. Creates the `kimai` database and user. The user's password is generated once and kept
   in `/data/db_password`.
6. Refuses to continue when Kimai has no users and no administrator credentials are set.
7. Exports the Kimai environment variables and starts the upstream `/entrypoint.sh` in the
   background. That script installs or migrates the database, creates the administrator,
   generates `APP_SECRET` (persisted in `var/data/.appsecret`), and finally replaces itself
   with Apache.
8. Waits. On `SIGTERM` or `SIGINT` it stops Apache, then shuts MariaDB down cleanly. If
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

## Testing a change

Work through these in order. Each catches problems the previous one cannot.

### 1. Lint

```bash
docker run --rm -v "$PWD/kimai:/mnt" koalaman/shellcheck:stable /mnt/run.sh
```

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
