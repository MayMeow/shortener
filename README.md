# Simple link shortening service

## Quick start

1. Pick a strong shared secret and export it before starting the server:

	```bash
	export SHORTENER_SECRET="replace-with-a-random-string"
	php -S 0.0.0.0:8080 -t webroot
	```

2. Create a short link with an authenticated API call:

	```bash
	SECRET="$SHORTENER_SECRET"
	TIMESTAMP=$(date -u +%s)
	BODY='{"url":"https://example.org"}'
	CANONICAL=$(printf "%s\n%s\n%s\n%s" "$TIMESTAMP" "POST" "/api/shorten" "$BODY")
	SIGNATURE=$(printf "%s" "$CANONICAL" | openssl dgst -sha256 -mac HMAC -macopt "key:$SECRET" -binary | base64)

	curl -X POST http://127.0.0.1:8080/api/shorten \
	  -H "Content-Type: application/json" \
	  -H "X-Timestamp: $TIMESTAMP" \
	  -H "X-Signature: $SIGNATURE" \
	  -d "$BODY"
	```

	The signature is calculated as `base64(hmac_sha256(timestamp + "\n" + METHOD + "\n" + PATH + "\n" + body, secret))` and requests are only accepted when the timestamp is within five minutes of the server time.

3. Resolve a short link (no authentication required):

	```bash
	curl -i http://127.0.0.1:8080/abc
	```

### PowerShell helper

```powershell
Import-Module "$PSScriptRoot/powershell/ShortenerClient.psm1"
$env:SHORTENER_SECRET = "replace-with-a-random-string"

New-ShortLink -Url "https://example.org" -ApiBaseUrl "http://127.0.0.1:8080"
Get-ShortLink -Code "abc" -ApiBaseUrl "http://127.0.0.1:8080"
```

## API

- `POST /api/shorten` (authenticated) accepts `{"url": "https://target"}` and returns the `code`, `short_url`, `target_url`, and `created_at` fields.
- `GET /api/links/{code}` (authenticated) returns metadata about an existing short link.
- `GET /{code}` (public) issues an HTTP redirect to the stored target URL.

Links are stored in `data/shortener.sqlite` using SQLite. The database and schema are created automatically on first run.