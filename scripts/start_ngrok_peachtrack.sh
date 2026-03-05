#!/usr/bin/env bash
set -euo pipefail

NGROK_BIN="/opt/homebrew/bin/ngrok"

# Forward to local PeachTrack server
exec "$NGROK_BIN" http 8888 --log=stdout
