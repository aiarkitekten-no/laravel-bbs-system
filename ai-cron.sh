#!/bin/bash
# PUNKTET AI Activity Runner
# Run via cron every 6 minutes: */6 * * * * /var/www/vhosts/punktet.no/httpdocs/ai-cron.sh

cd /var/www/vhosts/punktet.no/httpdocs

# Check hour (skip night 23-06)
HOUR=$(date +%H)
if [ $HOUR -ge 23 ] || [ $HOUR -lt 6 ]; then
    echo "[$(date)] Night mode - skipping"
    exit 0
fi

# Run single AI cycle
/usr/bin/php artisan ai:life --once >> storage/logs/ai-cron.log 2>&1

# Also update node activity
/usr/bin/php artisan tinker --execute="app(\App\Services\AiNodeService::class)->simulateActivity();" > /dev/null 2>&1
