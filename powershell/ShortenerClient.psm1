function Get-ShortenerSecret {
    param()

    if (-not $env:SHORTENER_SECRET) {
        throw 'SHORTENER_SECRET environment variable is not set. Provide -Secret or export the variable.'
    }

    return $env:SHORTENER_SECRET
}

function New-ShortenerSignature {
    param(
        [Parameter(Mandatory = $true)][string]$Secret,
        [Parameter(Mandatory = $true)][string]$Timestamp,
        [Parameter(Mandatory = $true)][string]$Method,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Body
    )

    $canonical = [string]::Join("`n", @($Timestamp, $Method.ToUpperInvariant(), $Path, $Body))
    $bodyBytes = [System.Text.Encoding]::UTF8.GetBytes($canonical)
    $secretBytes = [System.Text.Encoding]::UTF8.GetBytes($Secret)

    $hmac = [System.Security.Cryptography.HMACSHA256]::new($secretBytes)
    try {
        $hash = $hmac.ComputeHash($bodyBytes)
        return [Convert]::ToBase64String($hash)
    }
    finally {
        $hmac.Dispose()
    }
}

function Invoke-ShortenerApi {
    param(
        [Parameter(Mandatory = $true)][string]$Method,
        [Parameter(Mandatory = $true)][string]$ApiBaseUrl,
        [Parameter(Mandatory = $true)][string]$Secret,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter()][string]$Body = ''
    )

    $timestamp = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds().ToString()
    $signature = New-ShortenerSignature -Secret $Secret -Timestamp $timestamp -Method $Method -Path $Path -Body $Body

    $headers = @{
        'Content-Type' = 'application/json'
        'X-Timestamp'  = $timestamp
        'X-Signature'  = $signature
    }

    $uri = (([System.Uri]$ApiBaseUrl).ToString().TrimEnd('/')) + $Path

    if ($Method -eq 'GET') {
        return Invoke-RestMethod -Method $Method -Uri $uri -Headers $headers -ErrorAction Stop
    }

    return Invoke-RestMethod -Method $Method -Uri $uri -Headers $headers -Body $Body -ErrorAction Stop
}

function New-ShortLink {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter()][string]$ApiBaseUrl = 'http://127.0.0.1:8080',
        [Parameter()][string]$Secret
    )

    if (-not $Secret) {
        $Secret = Get-ShortenerSecret
    }

    $payload = @{ url = $Url } | ConvertTo-Json -Depth 5 -Compress
    return Invoke-ShortenerApi -Method 'POST' -ApiBaseUrl $ApiBaseUrl -Secret $Secret -Path '/api/shorten' -Body $payload
}

function Get-ShortLink {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$Code,
        [Parameter()][string]$ApiBaseUrl = 'http://127.0.0.1:8080',
        [Parameter()][string]$Secret
    )

    if (-not $Secret) {
        $Secret = Get-ShortenerSecret
    }

    $path = '/api/links/' + $Code
    return Invoke-ShortenerApi -Method 'GET' -ApiBaseUrl $ApiBaseUrl -Secret $Secret -Path $path -Body ''
}

Export-ModuleMember -Function New-ShortenerSignature, Invoke-ShortenerApi, New-ShortLink, Get-ShortLink
