#!/usr/bin/env bash
# Starts the Kimai app: MariaDB first, then Kimai through the upstream
# /entrypoint.sh. On SIGTERM or SIGINT, Kimai is stopped before MariaDB so the
# database always shuts down cleanly.

set -euo pipefail

readonly OPTIONS_FILE=/data/options.json
readonly DB_SOCKET=/run/mysqld/mysqld.sock
readonly DB_DATA_DIR=/data/mysql
readonly DB_PASSWORD_FILE=/data/db_password
readonly DB_NAME=kimai
readonly DB_USER=kimai
readonly DB_WAIT_SECONDS=60
readonly KIMAI_DIR=/opt/kimai
readonly KIMAI_DATA_DIR=/data/kimai/data
readonly KIMAI_WEB_USER=www-data
readonly APP_CONFIG_DIR=/config
readonly BUNDLED_PLUGINS_DIR=/opt/kimai-bundles
readonly CONSOLE_ENV_FILE=/run/kimai-app/console.env
readonly MIN_PASSWORD_LENGTH=8

DB_PID=0
KIMAI_PID=0

log()
{
  echo "[kimai-app] $*"
}

fatal()
{
  log "ERROR: $*" >&2
  exit 1
}

# Prints one option from the app configuration, or nothing when it is unset.
option()
{
  jq -r --arg key "$1" '.[$key] // empty' "$OPTIONS_FILE"
}

# Runs a statement as the MariaDB root user over the local socket.
sql()
{
  mariadb --socket="$DB_SOCKET" --batch --skip-column-names -e "$1"
}

read_options()
{
  if [ ! -f "$OPTIONS_FILE" ]
  then
    fatal "$OPTIONS_FILE not found. This image must run as a Home Assistant app."
  fi

  ADMIN_EMAIL=$(option admin_email)
  ADMIN_PASSWORD=$(option admin_password)
  TRUSTED_HOSTS=$(option trusted_hosts)
  TRUSTED_PROXIES=$(option trusted_proxies)
  MAILER_FROM=$(option mailer_from)
  MAILER_URL=$(option mailer_url)

  if [ -n "$ADMIN_EMAIL" ] && ! [[ "$ADMIN_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+$ ]]
  then
    fatal "admin_email '$ADMIN_EMAIL' is not a valid email address."
  fi

  if [ -n "$ADMIN_PASSWORD" ] && [ "${#ADMIN_PASSWORD}" -lt "$MIN_PASSWORD_LENGTH" ]
  then
    fatal "admin_password must be at least $MIN_PASSWORD_LENGTH characters long."
  fi
}

# Applies the Home Assistant time zone (passed in as TZ) to PHP, so new Kimai
# users start out in the right time zone.
configure_timezone()
{
  if [ -z "${TZ:-}" ]
  then
    return
  fi

  if ! php -r 'exit( in_array( getenv( "TZ" ), timezone_identifiers_list(), true ) ? 0 : 1 );'
  then
    log "Ignoring unknown time zone '$TZ'; PHP keeps using UTC."
    return
  fi

  echo "date.timezone = \"$TZ\"" > /usr/local/etc/php/conf.d/zz-timezone.ini
  log "Time zone: $TZ"
}

# Points Kimai's data folder at persistent storage, links the plugins, and loads
# an optional local.yaml from the app configuration folder.
prepare_storage()
{
  mkdir -p "$KIMAI_DATA_DIR"
  chown -R "$KIMAI_WEB_USER:$KIMAI_WEB_USER" "$KIMAI_DATA_DIR"

  rm -rf "$KIMAI_DIR/var/data"
  ln -s "$KIMAI_DATA_DIR" "$KIMAI_DIR/var/data"

  link_plugins

  if [ -f "$APP_CONFIG_DIR/local.yaml" ]
  then
    ln -sf "$APP_CONFIG_DIR/local.yaml" "$KIMAI_DIR/config/packages/local.yaml"
    log "Using local.yaml from the app configuration folder."
  fi
}

# Fills Kimai's plugin folder with links to the plugins bundled with the app and
# to the plugins in the app configuration folder. A plugin in the configuration
# folder replaces a bundled plugin with the same name.
link_plugins()
{
  local plugins_dir="$KIMAI_DIR/var/plugins"
  local plugin

  mkdir -p "$APP_CONFIG_DIR/plugins"
  rm -rf "$plugins_dir"
  mkdir -p "$plugins_dir"

  shopt -s nullglob
  for plugin in "$BUNDLED_PLUGINS_DIR"/*/
  do
    ln -s "${plugin%/}" "$plugins_dir/$(basename "$plugin")"
  done

  for plugin in "$APP_CONFIG_DIR/plugins"/*/
  do
    if [ -e "$plugins_dir/$(basename "$plugin")" ]
    then
      log "Using $(basename "$plugin") from the app configuration folder instead of the bundled copy."
    fi

    ln -sfn "${plugin%/}" "$plugins_dir/$(basename "$plugin")"
  done
  shopt -u nullglob
}

start_database()
{
  mkdir -p "$DB_DATA_DIR" /run/mysqld
  chown -R mysql:mysql "$DB_DATA_DIR" /run/mysqld

  if [ ! -d "$DB_DATA_DIR/mysql" ]
  then
    log "Initialising a new MariaDB data directory."
    mariadb-install-db --user=mysql --datadir="$DB_DATA_DIR" --skip-test-db > /dev/null
  fi

  log "Starting MariaDB."
  # A separate session keeps MariaDB out of the process group, so a stop signal
  # sent to the whole group cannot stop it before Kimai.
  setsid mariadbd --user=mysql &
  DB_PID=$!

  local waited=0
  until mariadb-admin --socket="$DB_SOCKET" --silent ping > /dev/null 2>&1
  do
    if ! kill -0 "$DB_PID" 2> /dev/null
    then
      fatal "MariaDB failed to start. See the log above."
    fi

    if [ "$waited" -ge "$DB_WAIT_SECONDS" ]
    then
      fatal "MariaDB did not become ready within $DB_WAIT_SECONDS seconds."
    fi

    sleep 1
    waited=$(( waited + 1 ))
  done

  # Upgrades the system tables after a MariaDB version change; a no-op otherwise.
  mariadb-upgrade --socket="$DB_SOCKET" --silent > /dev/null || log "mariadb-upgrade reported a problem; continuing."
}

stop_database()
{
  if [ "$DB_PID" -eq 0 ]
  then
    return
  fi

  log "Stopping MariaDB."
  mariadb-admin --socket="$DB_SOCKET" shutdown 2> /dev/null || kill -TERM "$DB_PID" 2> /dev/null || true
  wait "$DB_PID" 2> /dev/null || true
  DB_PID=0
}

# Creates the Kimai database and user on first start. The password is generated
# once and kept in /data, so it never has to appear in the app options.
prepare_database()
{
  if [ ! -s "$DB_PASSWORD_FILE" ]
  then
    ( umask 077 && php -r 'echo bin2hex( random_bytes( 24 ) );' > "$DB_PASSWORD_FILE" )
  fi

  DB_PASSWORD=$(cat "$DB_PASSWORD_FILE")

  sql "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
    ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
    GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
    FLUSH PRIVILEGES;"

  local server_version
  server_version=$(sql "SELECT SUBSTRING_INDEX( VERSION(), '-', 1 );")
  DATABASE_URL="mysql://$DB_USER:$DB_PASSWORD@127.0.0.1:3306/$DB_NAME?charset=utf8mb4&serverVersion=$server_version-MariaDB"
}

# Kimai has no sign-up by default, so a fresh install without admin
# credentials would leave nobody able to log in.
require_admin_on_first_start()
{
  local user_count
  user_count=$(sql "SELECT COUNT(*) FROM \`$DB_NAME\`.kimai2_users;" 2> /dev/null || echo 0)

  if [ "$user_count" -gt 0 ]
  then
    return
  fi

  if [ -z "$ADMIN_EMAIL" ] || [ -z "$ADMIN_PASSWORD" ]
  then
    stop_database
    fatal "No Kimai users exist yet. Set admin_email and admin_password in the app configuration, then start the app again."
  fi

  log "First start: the administrator account '$ADMIN_EMAIL' will be created."
}

start_kimai()
{
  export DATABASE_URL TRUSTED_HOSTS TRUSTED_PROXIES

  if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]
  then
    export ADMINMAIL="$ADMIN_EMAIL" ADMINPASS="$ADMIN_PASSWORD"
  fi

  if [ -n "$MAILER_FROM" ]
  then
    export MAILER_FROM
  fi

  if [ -n "$MAILER_URL" ]
  then
    export MAILER_URL
  fi

  write_console_env

  log "Starting Kimai."
  /entrypoint.sh &
  KIMAI_PID=$!
}

# Stores the settings Kimai needs for the kimai-console command. Commands run
# with "docker exec" do not inherit this script's environment, and the image
# sets DATABASE_URL to an empty value that would hide any .env file.
write_console_env()
{
  local name

  mkdir -p "$(dirname "$CONSOLE_ENV_FILE")"
  ( umask 077 && : > "$CONSOLE_ENV_FILE" )
  for name in DATABASE_URL TRUSTED_HOSTS TRUSTED_PROXIES MAILER_FROM MAILER_URL
  do
    if [ -n "${!name:-}" ]
    then
      printf 'export %s=%q\n' "$name" "${!name}" >> "$CONSOLE_ENV_FILE"
    fi
  done
}

# shellcheck disable=SC2329 # Invoked through the trap set in main.
shutdown()
{
  # Ignore repeated stop signals so the shutdown runs only once.
  trap '' TERM INT
  log "Shutting down."

  if [ "$KIMAI_PID" -ne 0 ]
  then
    kill -TERM "$KIMAI_PID" 2> /dev/null || true
    wait "$KIMAI_PID" 2> /dev/null || true
  fi

  stop_database
  exit 0
}

# Waits until either process exits. If one of them stops on its own, the other
# is stopped too and the app exits with an error, so the watchdog can restart it.
supervise()
{
  wait -n "$KIMAI_PID" "$DB_PID" || true

  if ! kill -0 "$DB_PID" 2> /dev/null
  then
    DB_PID=0
    log "MariaDB stopped unexpectedly."
  else
    log "Kimai stopped unexpectedly."
  fi

  kill -TERM "$KIMAI_PID" 2> /dev/null || true
  stop_database
  exit 1
}

main()
{
  read_options
  configure_timezone
  prepare_storage
  trap shutdown TERM INT
  start_database
  prepare_database
  require_admin_on_first_start
  start_kimai
  supervise
}

main "$@"
