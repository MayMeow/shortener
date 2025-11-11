<?php
declare(strict_types=1);

use MayMeow\Shortener\LinkRepository;
use MayMeow\Shortener\LinkShortteningService;

require dirname(__DIR__) . '/vendor/autoload.php';

$secret = getenv('SHORTENER_SECRET');
if ($secret === false || $secret === '') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'SHORTENER_SECRET environment variable is not configured']);
    exit;
}

$databaseDirectory = dirname(__DIR__) . '/data';
$databasePath = $databaseDirectory . '/shortener.sqlite';

if (!is_dir($databaseDirectory)) {
    if (!mkdir($databaseDirectory, 0770, true) && !is_dir($databaseDirectory)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unable to create data directory']);
        exit;
    }
}

$pdo = new \PDO('sqlite:' . $databasePath, null, null, [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    \PDO::ATTR_EMULATE_PREPARES => false,
]);

$repository = new LinkRepository($pdo, new LinkShortteningService());

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$rawBody = file_get_contents('php://input') ?: '';

if (str_starts_with($uri, '/api/')) {
    handleApiRequest($method, $uri, $rawBody, $secret, $repository);
    exit;
}

handleRedirectRequest($uri, $repository);
exit;

function handleApiRequest(string $method, string $uri, string $rawBody, string $secret, LinkRepository $repository): void
{
    requireAuthentication($method, $uri, $rawBody, $secret);

    if ($method === 'POST' && $uri === '/api/shorten') {
        handleShortenRequest($rawBody, $repository);
        return;
    }

    if ($method === 'GET' && preg_match('#^/api/links/([0-9a-z]+)$#', $uri, $matches) === 1) {
        handleGetLinkRequest($matches[1], $repository);
        return;
    }

    jsonResponse(404, ['error' => 'Endpoint not found']);
}

function handleRedirectRequest(string $uri, LinkRepository $repository): void
{
    $code = ltrim($uri, '/');

    if ($code === '') {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Link shortener API']);
        return;
    }

    $link = $repository->findByCode($code);
    if ($link === null) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Short link not found']);
        return;
    }

    header('Location: ' . $link['url'], true, 302);
}

function handleShortenRequest(string $rawBody, LinkRepository $repository): void
{
    if ($rawBody === '') {
        jsonResponse(400, ['error' => 'Request body is required']);
        return;
    }

    try {
        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $exception) {
        jsonResponse(400, ['error' => 'Invalid JSON payload', 'details' => $exception->getMessage()]);
        return;
    }

    $url = trim((string) ($payload['url'] ?? ''));
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        jsonResponse(422, ['error' => 'A valid url field is required']);
        return;
    }

    $link = $repository->create($url);
    $shortUrl = buildShortUrl($link['code']);

    jsonResponse(201, [
        'code' => $link['code'],
        'short_url' => $shortUrl,
        'target_url' => $link['url'],
        'created_at' => $link['created_at'],
    ], ['Location' => $shortUrl]);
}

function handleGetLinkRequest(string $code, LinkRepository $repository): void
{
    $link = $repository->findByCode($code);
    if ($link === null) {
        jsonResponse(404, ['error' => 'Short link not found']);
        return;
    }

    $link['short_url'] = buildShortUrl($link['code']);

    jsonResponse(200, $link);
}

function requireAuthentication(string $method, string $uri, string $rawBody, string $secret): void
{
    $timestampHeader = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
    $signatureHeader = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

    if ($timestampHeader === '' || $signatureHeader === '') {
        jsonResponse(401, ['error' => 'Authentication headers missing']);
        exit;
    }

    if (!ctype_digit($timestampHeader)) {
        jsonResponse(401, ['error' => 'Invalid timestamp header']);
        exit;
    }

    $timestamp = (int) $timestampHeader;
    if (abs(time() - $timestamp) > 300) {
        jsonResponse(401, ['error' => 'Timestamp out of range']);
        exit;
    }

    $canonical = implode("\n", [$timestampHeader, $method, $uri, $rawBody]);
    $expectedSignature = base64_encode(hash_hmac('sha256', $canonical, $secret, true));

    if (!hash_equals($expectedSignature, $signatureHeader)) {
        jsonResponse(401, ['error' => 'Invalid signature']);
        exit;
    }
}

function jsonResponse(int $statusCode, array $payload, array $extraHeaders = []): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    foreach ($extraHeaders as $headerName => $value) {
        header($headerName . ': ' . $value, true);
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function buildShortUrl(string $code): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return sprintf('%s://%s/%s', $scheme, $host, $code);
}
