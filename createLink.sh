export SHORTENER_SECRET="replace-with-a-random-string"
SECRET="$SHORTENER_SECRET"
TIMESTAMP=$(date -u +%s)
BODY='{"url":"https://catgirl.sk"}'
CANONICAL=$(printf "%s\n%s\n%s\n%s" "$TIMESTAMP" "POST" "/api/shorten" "$BODY")
SIGNATURE=$(printf "%s" "$CANONICAL" | openssl dgst -sha256 -mac HMAC -macopt "key:$SECRET" -binary | base64)

curl -X POST http://127.0.0.1:8080/api/shorten \
    -H "Content-Type: application/json" \
    -H "X-Timestamp: $TIMESTAMP" \
    -H "X-Signature: $SIGNATURE" \
    -d "$BODY"

