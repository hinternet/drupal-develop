#!/usr/bin/env sh
if [ ! -f "$HASH_SALT_FILE" ]; then
  LC_ALL=C;
  cat /dev/urandom | tr -dc 'a-zA-Z0-9-_' | fold -w 74 | head -n 1 > "$HASH_SALT_FILE";
fi
