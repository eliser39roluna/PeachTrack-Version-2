#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/Users/sumitniveriya/clawd/peachtrack-shift-tip-dashboard/src"
PHP_BIN="/opt/homebrew/opt/php/bin/php"
PORT="8888"

cd "$APP_DIR"
exec "$PHP_BIN" -S 0.0.0.0:"$PORT" -t .
