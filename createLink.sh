#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 1 ]; then
    echo "Usage: $0 <target-url> [api-base-url]" >&2
    exit 1
fi

export SHORTENER_SECRET="replace-with-a-random-string"

: "${SHORTENER_SECRET:?SHORTENER_SECRET must be exported}"

TARGET_URL="$1"
API_BASE_URL="${2:-http://127.0.0.1:8080}"
API_PATH="/api/shorten"

TIMESTAMP=$(date -u +%s)
BODY=$(python3 -c 'import json,sys; print(json.dumps({"url": sys.argv[1]}))' "$TARGET_URL")
CANONICAL=$(printf "%s\n%s\n%s\n%s" "$TIMESTAMP" "POST" "$API_PATH" "$BODY")
SIGNATURE=$(printf "%s" "$CANONICAL" | openssl dgst -sha256 -mac HMAC -macopt "key:$SHORTENER_SECRET" -binary | base64)

curl -X POST "${API_BASE_URL}${API_PATH}" \
    -H "Content-Type: application/json" \
    -H "X-Timestamp: $TIMESTAMP" \
    -H "X-Signature: $SIGNATURE" \
    -d "$BODY"

