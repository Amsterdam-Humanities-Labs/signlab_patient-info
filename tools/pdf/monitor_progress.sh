#!/bin/bash
# Monitor crawl progress every 10 minutes

LOG_FILE="crawl_output.log"

echo "Starting monitoring at $(date '+%H:%M:%S')"
echo "Will check every 10 minutes..."
echo ""

while true; do
    # Check if process is still running
    if ! ps aux | grep -q "[p]ython3.*crawl_in_het_kort"; then
        echo "=== SCRIPT COMPLETED at $(date '+%H:%M:%S') ==="
        echo ""
        # Show final summary
        tail -15 "$LOG_FILE"
        break
    fi

    # Show progress
    echo "=== Progress Check at $(date '+%H:%M:%S') ==="
    tail -3 "$LOG_FILE"
    echo ""

    PROCESSED=$(grep -c 'Processing' "$LOG_FILE")
    SUCCESSFUL=$(grep -c 'SUCCESS' "$LOG_FILE")
    FAILED_PARSE=$(grep -c 'WARNING: No' "$LOG_FILE")
    FAILED_FETCH=$(grep -c 'FETCH ERROR' "$LOG_FILE")

    echo "Processed: $PROCESSED / 692 ($(echo "scale=1; $PROCESSED*100/692" | bc)%)"
    echo "Successful: $SUCCESSFUL"
    echo "Failed parse: $FAILED_PARSE"
    echo "Failed fetch: $FAILED_FETCH"
    echo ""
    echo "---"
    echo ""

    # Wait 10 minutes
    sleep 600
done
