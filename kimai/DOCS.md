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

## Extras in this app

The app adds two Kimai plugins, made for this app and maintained in the same repository: a
quick start bar and graphs.

### Quick start bar

A bar to start and stop time recording without opening a form. On wide screens it sits in the
top navigation of every page, in place of Kimai's own start button. On phones and narrow
windows Kimai's own button stays in the navigation, and the bar appears above the content of
the dashboard and **My times**.

- **Description:** type what you are working on. Recent descriptions are suggested; picking
  one fills in the project and activity it was last booked on.
- **Project and activity:** projects are grouped by customer, and the activity list only
  offers activities that can be booked on the selected project.
- **Tags** (tag icon): tick existing tags, or type new ones separated by commas if you may
  create tags. The icon shows how many tags are chosen.
- **Billable** (coins icon): shows whether the record will be billable. It follows Kimai's rule
  (billable when the customer, project and activity all are) until you press it; then your
  choice counts. It only appears if you may change the billable setting.
- **Start** (green button) starts recording now. While a record runs, the bar shows its
  description, project, tags and a running clock, and the red button stops it.

On **My times**, every record has a green play button next to its menu. It continues that
record: a new record starts now with the same description, project, activity, tags and
billable setting.

The bar starts and stops records the same way the rest of Kimai does, so Kimai's settings
for rounding and for how many records may run at once apply. When only one record may run,
starting a new one stops the running one.

Kimai rounds start times down to the minute by default, so a new record's clock can start at
up to 59 seconds. To record exact seconds, turn rounding off in [`local.yaml`](#localyaml):

```yaml
kimai:
  timesheet:
    rounding:
      default:
        begin: 0
        end: 0
```

### Graphs

**Weekly, monthly and yearly view for one user.** Kimai's own reports under **Reporting** get
two charts above their table: a bar chart of the hours per day (per month in the yearly
view), stacked by project, and a doughnut chart of each project's share. They follow the
week, month, year and user picked in the report.

**Summary report.** **Reporting** > **Summary** shows where your time went in one page:

- totals for the period: total time, billable time and, if you may see rates, the amount;
- a bar chart of the time per day (for periods up to two months) or per month, coloured by
  project, customer, activity or user;
- a doughnut chart and a breakdown per project, customer, activity or user, which opens to
  show the time per description.

**Colours and short records.** The charts use the colours of your projects, customers and
activities in Kimai. When two of them are too alike to tell apart, for example two projects
that both take their customer's red, the smaller one gets a clearly different colour, and the
legend or breakdown shows which is which. Bars of a few minutes are drawn at a minimum height
and small slices at a minimum size, so even a 15-minute record stays visible; hover over them
for the exact time. Charts with more than eight groups combine the smallest into **Other**.

Pick the period with the date field; its menu offers this week, last week, recent months,
quarters and years. The arrows next to it move one period back or forward. The report starts
with your own time this week. Users who may see other people's reports can choose a
colleague or **All users**.

### Turning an extra off

Create a folder with the extra's name inside the `plugins` folder of the app's
configuration folder, put an empty file named `.disabled` in it, and restart the app:

- `plugins/SummaryBundle/.disabled` turns off the graphs.
- `plugins/TimerBarBundle/.disabled` turns off the quick start bar.

Remove the folder and restart to turn the extra back on.

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
plugin's own documentation; see [Running Kimai commands](#running-kimai-commands).

### Running Kimai commands

Kimai has a command line for maintenance tasks, such as resetting a password or installing a
plugin. The app includes `kimai-console`, which runs those commands with the right user and
database settings. It needs the **Advanced SSH & Web Terminal** app with *Protection mode*
turned off. In its terminal, run for example:

```bash
docker exec -it "$(docker ps --quiet --filter name=kimai)" kimai-console kimai:user:list
```

Replace `kimai:user:list` with the command you need; `list` shows them all. Turn *Protection
mode* back on afterwards.

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
  from the command line, as described in [Running Kimai commands](#running-kimai-commands):

  ```bash
  docker exec -it "$(docker ps --quiet --filter name=kimai)" kimai-console kimai:user:password admin
  ```

**A page breaks after adding a plugin**
: A broken or incompatible plugin can stop Kimai from loading. Remove it from the `plugins`
  folder, or [turn it off](#turning-an-extra-off), and restart the app.

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
