# Changelog

All notable changes to this app are documented here. The version number is the Kimai
version the app contains; see the
[Kimai releases](https://github.com/kimai/kimai/releases) for changes in Kimai itself.

## 2.67.0.1

- Fix the app showing as starting forever in Home Assistant, with no Stop or Restart
  buttons. The app now reports itself as started once Kimai is ready to use.

## 2.67.0

First release.

- Kimai 2.67.0 with a bundled MariaDB 10.11 database.
- Creates the first administrator from the app options.
- Persistent data, plugins and `local.yaml` support through the app configuration folder.
- Uses the Home Assistant time zone as Kimai's default time zone.
- Optional email through any Symfony Mailer transport.
- Opens through **Open web UI**; English and Dutch option descriptions.
