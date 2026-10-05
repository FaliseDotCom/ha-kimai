# Kimai

[Kimai](https://www.kimai.org/) is a free, open-source time-tracking application. Track
time against customers, projects and activities, then turn it into reports, exports and
invoices. This app runs Kimai on your Home Assistant server, together with its own MariaDB
database, so there is nothing else to install.

## Requirements

- Home Assistant OS or a Supervised installation, version 2026.7 or later.
- A 64-bit system: `amd64` (most PCs and NUCs) or `aarch64` (Raspberry Pi 4 or 5, and
  most other ARM boards).
- Around 1.5 GB of free disk space for the app, plus room for your data.
- Around 300 MB of free memory while Kimai is running.

The first installation builds the app on your own device. Expect it to take a few minutes,
or longer on a Raspberry Pi.

## Installation

1. Add this repository to Home Assistant. Go to **Settings** > **Apps** > **Install app**,
   open the three-dots menu in the top-right corner, select **Repositories**, then add:

   ```text
   https://github.com/FaliseDotCom/ha-kimai
   ```

2. Find **Kimai** in the app store and select **Install**.
3. Open the **Configuration** tab and set `admin_email` and `admin_password`. See
   [First start](#first-start).
4. Go back to the **Info** tab, turn on **Watchdog**, and select **Start**.
5. Open the **Log** tab. The first start takes a minute or two while the database is
   created. Kimai is ready when the log shows `resuming normal operations`.

## First start

Kimai does not allow visitors to sign up, so the app creates the first administrator for
you. Before the very first start, fill in:

| Option           | Value                       |
| ---------------- | --------------------------- |
| `admin_email`    | Your email address          |
| `admin_password` | A password of 8+ characters |

The account is created with the user name `admin`. You can log in with either the user name
or the email address.

These two options are only read while Kimai has no users. Once you have logged in, change
the password inside Kimai (open your profile from the avatar in the top-right corner, then
the **Password** tab), then clear both
options in the app configuration so the password is no longer stored there. Changing
the options later does **not** change the existing account.

If you start the app without these options on a fresh install, it stops with a message
in the log asking you to set them.

## Opening Kimai

Kimai runs on its own port, **8001**, separate from the Home Assistant interface.

### From the app page

Select **Open web UI** on the app's **Info** tab. This opens
`http://<your-home-assistant-address>:8001` in a new tab. It works on any device on your
home network, including the Home Assistant Companion app on Android and iOS.

### On your phone

In the Companion app, go to **Settings** > **Apps** > **Kimai** and select **Open web UI**.
Kimai opens in your phone's browser. Kimai's interface works well on small screens; to keep
it one tap away, use your browser's **Add to home screen** option.

The address uses the host name you connected to Home Assistant with. When you are away from
home and connect through Home Assistant Cloud or another remote URL, port 8001 is usually not
reachable. Set up [remote access](#remote-access) if you want to use Kimai away from home.

### In the Home Assistant sidebar (optional)

This app does not use Home Assistant's built-in sidebar integration (called *ingress*),
because Kimai cannot run under the changing sub-path that ingress uses. You can still add
Kimai to the sidebar with a **Webpage** dashboard:

1. Go to **Settings** > **Dashboards** and select **Add dashboard**.
2. Choose **Webpage** and enter Kimai's address, for example
   `http://homeassistant.local:8001`.
3. Give it a title such as *Kimai*, pick an icon such as `mdi:timer-outline`, and keep
   **Show in sidebar** turned on.

Two things to keep in mind:

- The browser loads Kimai directly, so the sidebar entry only works where Kimai's address
  is reachable, normally on your home network.
- If you open Home Assistant over `https://`, the address of the dashboard must use
  `https://` too, or the browser blocks the page. Use your [remote access](#remote-access)
  address in that case.

## Configuration

Example configuration:

```yaml
admin_email: you@example.com
admin_password: a-long-random-password
trusted_hosts: homeassistant\.local|192\.168\.1\.10|kimai\.example\.com
trusted_proxies: 127.0.0.1,172.30.32.0/23
```

Restart the app after changing any option.

### Option: `admin_email`

Email address of the first administrator. Only used while Kimai has no users. See
[First start](#first-start).

### Option: `admin_password`

Password of the first administrator, at least 8 characters. Only used while Kimai has no
users. See [First start](#first-start).

### Option: `trusted_hosts`

A regular expression listing every host name you use to reach Kimai. Kimai rejects requests
for any other host name with an *Untrusted Host* error (HTTP 400). This protects against
host header attacks, for example on password reset links.

Leave it empty to accept every host name, which is convenient while you are testing on your
home network. Set it before you make Kimai reachable from the internet. Escape dots with a
backslash and separate host names with `|`:

```yaml
trusted_hosts: homeassistant\.local|192\.168\.1\.10|kimai\.example\.com
```

### Option: `trusted_proxies`

A comma-separated list of IP addresses or ranges (CIDR) of reverse proxies that sit in
front of Kimai. Kimai only trusts the `X-Forwarded-*` headers from these addresses, which
it needs to know the visitor's real IP address and whether the original request used HTTPS.

The default, `127.0.0.1,172.30.32.0/23`, covers proxies that run as another Home Assistant
app, such as NGINX Proxy Manager or Cloudflared. Add the address of any proxy that runs
elsewhere on your network.

### Option: `mailer_from`

The sender address for emails from Kimai, for example `kimai@example.com`. Optional.

### Option: `mailer_url`

The mail server Kimai uses to send email, such as password reset links. Optional; without
it Kimai sends no email. The format is a
[Symfony Mailer DSN](https://symfony.com/doc/current/mailer.html#using-built-in-transports):

```yaml
mailer_url: smtp://user:password@smtp.example.com:587
```

Characters with a special meaning in a URL, such as `@`, `:` or `/` inside the user name or
password, must be URL-encoded, so `@` becomes `%40`.

Both mail options are hidden until you select **Show unused optional configuration options**
on the **Configuration** tab.

### Port

The **Network** section of the **Configuration** tab sets the port Kimai is published on.
Change it if port 8001 is already in use on your server. Clear it to stop publishing Kimai
on your network entirely, for example when a proxy app is the only way in.

## Remote access

To use Kimai away from home, put it behind a reverse proxy that provides HTTPS, such as the
**Cloudflared** or **NGINX Proxy Manager** app, and point the proxy at:

```text
http://<your-home-assistant-ip>:8001
```

Then:

1. Add the public host name to [`trusted_hosts`](#option-trusted_hosts), for example
   `kimai\.example\.com`.
2. Make sure the proxy's address is in [`trusted_proxies`](#option-trusted_proxies). If
   Kimai redirects you to `http://` addresses or shows the proxy's IP address as the
   visitor's, the proxy is not trusted yet.
3. Turn on two-factor authentication for every Kimai account, on the **Two-Factor (2FA)**
   tab of each user's profile.

Never expose port 8001 directly to the internet without HTTPS.

## Customising Kimai

The app has its own configuration folder, which you can reach with the **Samba share** or
**Studio Code Server** app. It is the `app_configs` share, in the folder whose name ends in
`_kimai`.

### `local.yaml`

Kimai's advanced settings, such as time rounding rules, LDAP or SAML sign-in, and
permission changes, live in a file called `local.yaml`. Place it in the app's configuration
folder and restart the app. See [Kimai's documentation on
local.yaml](https://www.kimai.org/documentation/local-yaml.html) for what it can contain.

An error in `local.yaml` stops Kimai from starting. If that happens, the log names the
problem; fix or remove the file and restart.

### Plugins

Copy each [Kimai plugin](https://www.kimai.org/store/) into the `plugins` folder inside the
app's configuration folder, so that you end up with, for example,
`plugins/ExamplePluginBundle/`. Then restart the app; Kimai rebuilds its cache on every start
and picks the plugin up. Some plugins also need an install command, described in the
plugin's own documentation. Run it the same way as the password reset command under
[Troubleshooting](#troubleshooting).

## Backups

The app is included in Home Assistant backups. Kimai is stopped while the backup is made, so
that the database is copied in a consistent state, and starts again afterwards. For a small
database this takes well under a minute.

A backup contains everything: the database, uploaded files such as invoice templates, the
configuration folder and the options. Restoring the app from a backup restores all of it.

Uninstalling the app **deletes the database**. Create a backup first if you might want your
data back.

## Updates

The app's version number is the Kimai version it contains. Before you update:

1. Create a backup that includes Kimai. Database changes during an update cannot be undone,
   and Kimai cannot be downgraded without restoring a backup.
2. Read the [Kimai release notes](https://github.com/kimai/kimai/releases) for the versions
   you are skipping.

Kimai upgrades its database automatically on the first start after an update.

## Troubleshooting

Start with the **Log** tab of the app. Kimai and MariaDB both write to it, and messages
from the app itself start with `[kimai-app]`.

**"No Kimai users exist yet"**
: Set `admin_email` and `admin_password` and start the app again. See
  [First start](#first-start).

**"Untrusted Host" or HTTP 400**
: The host name in your browser's address bar does not match `trusted_hosts`. Add it, or
  clear the option.

**Kimai keeps redirecting to `http://` behind a proxy**
: The proxy is not listed in `trusted_proxies`. See [Remote access](#remote-access).

**The app fails to start right after installation**
: Check that port 8001 is not used by another app, or pick a different port in the
  **Network** section.

**Forgotten administrator password**
: If email is configured, use **Forgot password** on the login page. Otherwise, reset it
  from the command line. This needs the **Advanced SSH & Web Terminal** app with
  *Protection mode* turned off:

  ```bash
  docker exec -it --user www-data "$(docker ps --quiet --filter name=kimai)" \
    /opt/kimai/bin/console kimai:user:password admin
  ```

  Turn *Protection mode* back on afterwards.

## Security

- Use a long, unique administrator password and turn on two-factor authentication.
- Set `trusted_hosts` before Kimai is reachable from outside your home network.
- Only reach Kimai over HTTPS from the internet; see [Remote access](#remote-access).
- The database is only reachable from inside the app; it is never published on your
  network. Its password is generated on first start and stored in the app's private data.

## Support

- Problems with this app: [open an issue](https://github.com/FaliseDotCom/ha-kimai/issues).
- Questions about using Kimai: [Kimai documentation](https://www.kimai.org/documentation/)
  and [Kimai discussions](https://github.com/kimai/kimai/discussions).
