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
COPY run.sh /run.sh
RUN chmod 0755 /run.sh

# The upstream image stops Apache with SIGWINCH. run.sh needs SIGTERM so it
# can shut Kimai and MariaDB down in the right order.
STOPSIGNAL SIGTERM

# The upstream health check is replaced by the Supervisor watchdog.
HEALTHCHECK NONE

CMD [ "/run.sh" ]
