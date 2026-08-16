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

# Both files matter: ports.conf decides what Apache binds, the vhost decides
# which requests it answers. Changing one and not the other yields a server
# listening on the right port that 404s everything.
sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "[entrypoint] Apache will listen on ${PORT}"

# A missing DB_HOST surfaces as a crash loop with no explanation otherwise --
# bootstrap exits on the config guard before anything is logged to the web
# server. Saying so here turns a mystery into a sentence.
for required in DB_HOST DB_NAME DB_USER DB_PASS JWT_SECRET; do
    eval "value=\${${required}:-}"
    if [ -z "${value}" ]; then
        echo "[entrypoint] WARNING: ${required} is not set. Every request will return 500." >&2
    fi
done

exec "$@"
