#!/bin/sh
# =============================================================================
#  Container entrypoint.
#
#  Its whole job is the port. Apache listens on 80 by default; Railway assigns
#  a port through $PORT and routes only to that one. An image that ignores it
#  starts cleanly, reports healthy, and receives no traffic -- which looks like
#  a networking fault and is not.
# =============================================================================
set -eu

PORT="${PORT:-80}"

# -----------------------------------------------------------------------------
#  Exactly one MPM
# -----------------------------------------------------------------------------
#  Apache refuses to start with "AH00534: More than one MPM loaded" when two of
#  mpm_prefork/mpm_event/mpm_worker are enabled, and the platform restarts it,
#  so the only symptom is that line repeating forever.
#
#  The Dockerfile already normalises this at build time. It is done again here
#  because a build-time fix only covers what the build controls: a base-image
#  change, a cached layer, or a platform that mutates the image afterwards can
#  all reintroduce it, and the failure mode is a crash loop rather than
#  anything diagnosable.
#
#  The list is printed either way. If this ever fires, the log says exactly
#  which modules were present instead of leaving it to guesswork.
# -----------------------------------------------------------------------------
MPM_DIR=/etc/apache2/mods-enabled
MPM_ENABLED=$(ls "${MPM_DIR}" 2>/dev/null | grep '^mpm_.*\.load$' | tr '\n' ' ' || true)
MPM_COUNT=$(echo "${MPM_ENABLED}" | wc -w)

if [ "${MPM_COUNT}" -eq 1 ]; then
    echo "[entrypoint] MPM: ${MPM_ENABLED}"
else
    echo "[entrypoint] MPM: found ${MPM_COUNT} (${MPM_ENABLED}) -- normalising to mpm_prefork" >&2

    rm -f "${MPM_DIR}/mpm_event.load"  "${MPM_DIR}/mpm_event.conf"
    rm -f "${MPM_DIR}/mpm_worker.load" "${MPM_DIR}/mpm_worker.conf"

    # mod_php requires prefork, so that is the one to keep.
    ln -sf ../mods-available/mpm_prefork.load "${MPM_DIR}/mpm_prefork.load"
    ln -sf ../mods-available/mpm_prefork.conf "${MPM_DIR}/mpm_prefork.conf"

    echo "[entrypoint] MPM: now $(ls "${MPM_DIR}" | grep '^mpm_.*\.load$' | tr '\n' ' ')" >&2
fi

# Both files matter: ports.conf decides what Apache binds, the vhost decides
# which requests it answers. Changing one and not the other yields a server
# listening on the right port that 404s everything.
sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "[entrypoint] Apache will listen on ${PORT}"

# A missing DB_HOST surfaces as a crash loop with no explanation otherwise --
# bootstrap exits on the config guard before anything is logged to the web
# server. Saying so here turns a mystery into a sentence.
MISSING=""
for required in DB_HOST DB_NAME DB_USER DB_PASS JWT_SECRET; do
    eval "value=\${${required}:-}"
    if [ -z "${value}" ]; then
        echo "[entrypoint] WARNING: ${required} is not set. Every request will return 500." >&2
        MISSING="yes"
    fi
done

# -----------------------------------------------------------------------------
#  Bring the database up to date
# -----------------------------------------------------------------------------
#  Creates missing tables, seeds the required settings row, and runs any
#  migration that has not been applied. Everything it does is conditional, so a
#  database that is already current comes out untouched.
#
#  Demo data is NOT included -- db/seed.sql creates accounts whose passwords are
#  published in the repository. It needs SEED_DEMO_DATA=1, which belongs in
#  development only.
#
#  Concurrent replicas are serialised by a MySQL advisory lock inside the
#  script, so several containers starting at once do not race to CREATE TABLE.
#
#  Set SKIP_DB_INIT=1 to leave the database alone entirely.
# -----------------------------------------------------------------------------
if [ "${SKIP_DB_INIT:-0}" = "1" ]; then
    echo "[entrypoint] SKIP_DB_INIT=1, leaving the database alone."
elif [ -n "${MISSING}" ]; then
    echo "[entrypoint] Skipping database init: configuration is incomplete." >&2
else
    echo "[entrypoint] Checking the database..."

    # The database may still be accepting connections when this container
    # starts. Retrying beats failing the deploy over a few seconds of startup
    # ordering.
    attempt=1
    until php /var/www/html/bin/db-init.php; do
        if [ "${attempt}" -ge 5 ]; then
            echo "[entrypoint] Database init failed after ${attempt} attempts." >&2
            # Apache still starts. A broken database is visible in the logs and
            # in the 500s; refusing to boot turns it into a crash loop that
            # hides the reason.
            break
        fi

        echo "[entrypoint] Database not ready (attempt ${attempt}), retrying in 3s..." >&2
        attempt=$((attempt + 1))
        sleep 3
    done
fi

exec "$@"
