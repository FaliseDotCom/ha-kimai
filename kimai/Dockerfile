# Kimai with a bundled MariaDB server, packaged as a Home Assistant app.
#
# The upstream image is pinned to an exact release. Bump this tag and the
# `version` in config.yaml together (see .docs/releasing.md).
FROM kimai/kimai2:2.67.0

# jq reads the app options from /data/options.json; mariadb-server provides
# the database that runs next to Kimai inside this container.
RUN apt-get update \
  && apt-get install -y --no-install-recommends jq mariadb-server \
  && rm -rf /var/lib/apt/lists/* /var/lib/mysql/*

COPY mariadb.cnf /etc/mysql/mariadb.conf.d/90-kimai.cnf
# Plugins that ship with the app; run.sh links them into Kimai's plugin folder.
COPY bundles/ /opt/kimai-bundles/
COPY run.sh /run.sh
COPY kimai-console /usr/local/bin/kimai-console
RUN chmod 0755 /run.sh /usr/local/bin/kimai-console

# The upstream image stops Apache with SIGWINCH. run.sh needs SIGTERM so it
# can shut Kimai and MariaDB down in the right order.
STOPSIGNAL SIGTERM

# The Supervisor shows the app as starting until this check passes, and its
# watchdog restarts the app when the check fails. Apache only starts listening
# once Kimai is installed or migrated, so an open port means Kimai is ready.
# The upstream check requests a page, which fails when trusted_hosts excludes
# 127.0.0.1. Disabling the check is no option either: the image then still
# carries health check settings, and the Supervisor waits forever for a result.
HEALTHCHECK --start-period=10m --interval=30s --timeout=10s --retries=3 \
  CMD [ "bash", "-c", "exec 3<> /dev/tcp/127.0.0.1/8001" ]

CMD [ "/run.sh" ]
