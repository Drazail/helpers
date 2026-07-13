#!/bin/sh
set -eu
OUT=""
for arg in "$@"; do
  case "$arg" in
    --result-file=*) OUT="${arg#*=}" ;;
  esac
done
if [ -z "$OUT" ]; then
  echo "fake-mysqldump: missing --result-file" >&2
  exit 1
fi
printf '%s\n' '-- fake sql dump' > "$OUT"
exit 0
