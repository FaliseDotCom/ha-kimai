# Kimai for Home Assistant

[![Add repository to Home Assistant][repository-badge]][repository-url]
![Kimai 2.67.0][kimai-shield]
![Supports aarch64 Architecture][aarch64-shield]
![Supports amd64 Architecture][amd64-shield]
[![License: MIT][license-shield]](LICENSE)

Run [Kimai](https://www.kimai.org/), the free and open-source time tracker, as a Home
Assistant app. Kimai and its MariaDB database run together in one app, so there is nothing
else to set up.

## Features

- Kimai with a bundled database; no separate database app needed.
- The first administrator is created from the app options.
- All data is included in Home Assistant backups.
- Kimai plugins and `local.yaml` customisation through the app configuration folder.
- Uses the Home Assistant time zone.
- Opens from the app page, the Home Assistant Companion app, or a sidebar dashboard.
- Runs on `amd64` and `aarch64` (Raspberry Pi 4 and 5).

## Installation

Select the button above, or add the repository by hand:

1. In Home Assistant, go to **Settings** > **Apps** > **Install app**.
2. Open the three-dots menu in the top-right corner and select **Repositories**.
3. Add `https://github.com/FaliseDotCom/ha-kimai` and select **Add**.
4. Find **Kimai** in the app store and select **Install**.

Then follow the [app documentation](kimai/DOCS.md) to create your administrator account and
open Kimai. The same documentation is shown on the app's **Documentation** tab in Home
Assistant.

## Apps in this repository

| App                | Description                                                     |
| ------------------ | --------------------------------------------------------------- |
| [Kimai](kimai/)    | Self-hosted time tracking for freelancers and teams.            |

## Contributing

Bug reports and pull requests are welcome. [Development](.docs/development.md) explains how
the app works and how to test changes, and [Releasing](.docs/releasing.md) covers updating
to a new Kimai version.

## AI coding guidelines

This project is shell, YAML, Docker and Markdown only. None of the language skills (`php`,
`phpstan`, `wordpress`, `wordpress-translations`, `javascript`, `svelte`, `css`) apply. The
global guidelines for formatting (two-space indentation, braces on their own line) and Git
still do, and shell scripts must pass [ShellCheck](https://www.shellcheck.net/).

## License

The files in this repository are released under the [MIT License](LICENSE). Kimai itself is
licensed under the [AGPL-3.0](https://github.com/kimai/kimai/blob/main/LICENSE) and is not
affiliated with this project.

[repository-badge]: https://my.home-assistant.io/badges/supervisor_add_addon_repository.svg
[repository-url]: https://my.home-assistant.io/redirect/supervisor_add_addon_repository/?repository_url=https%3A%2F%2Fgithub.com%2FFaliseDotCom%2Fha-kimai
[kimai-shield]: https://img.shields.io/badge/Kimai-2.67.0-blue.svg
[aarch64-shield]: https://img.shields.io/badge/aarch64-yes-green.svg
[amd64-shield]: https://img.shields.io/badge/amd64-yes-green.svg
[license-shield]: https://img.shields.io/badge/license-MIT-green.svg
