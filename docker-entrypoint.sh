#!/bin/sh
set -e

# Require basic user identity for notmuch; allow optional aliases
: "${NOTMUCH_CONFIG:?Set NOTMUCH_CONFIG}"
: "${NOTMUCH_NAME:?Set NOTMUCH_NAME}"
: "${NOTMUCH_PRIMARY_EMAIL:?Set NOTMUCH_PRIMARY_EMAIL}"
: "${NOTMUCH_OTHER_EMAILS:=}"

# Ensure the target directory exists for the generated config
install -d "$(dirname "$NOTMUCH_CONFIG")"

# Export the config path and generate the config file
CONFIG_TEMPLATE=/app/conf/notmuch-config.template
envsubst < "$CONFIG_TEMPLATE" > "$NOTMUCH_CONFIG"

# Ensure the database and mail roots exist before initializing notmuch
install -d /mail /notmuch

# Initialize or update the notmuch database when starting
notmuch new

exec "$@"
