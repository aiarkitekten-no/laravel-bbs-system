#!/bin/bash
# PUNKTET BBS - AI Daemon Starter
# This script ensures the AI simulation is running

LOCKFILE="/tmp/punktet_ai.lock"
LOGFILE="/var/www/vhosts/punktet.no/httpdocs/storage/logs/ai-daemon.log"
ARTISAN="/var/www/vhosts/punktet.no/httpdocs/artisan"

# Check if already running
if [ -f "$LOCKFILE" ]; then
    PID=$(cat "$LOCKFILE")
    if ps -p "$PID" > /dev/null 2>&1; then
        echo "AI daemon already running (PID: $PID)"
        exit 0
    fi
    rm -f "$LOCKFILE"
fi

# Start the daemon
cd /var/www/vhosts/punktet.no/httpdocs
nohup php artisan ai:simulate >> "$LOGFILE" 2>&1 &
echo $! > "$LOCKFILE"

echo "AI daemon started (PID: $!)"
