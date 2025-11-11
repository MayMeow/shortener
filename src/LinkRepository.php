<?php
declare(strict_types=1);

namespace MayMeow\Shortener;

use PDO;
use PDOException;

class LinkRepository
{
    private PDO $pdo;
    private LinkShortteningService $shortener;

    public function __construct(PDO $pdo, LinkShortteningService $shortener)
    {
        $this->pdo = $pdo;
        $this->shortener = $shortener;

        $this->configureConnection();
        $this->initializeSchema();
    }

    private function configureConnection(): void
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE,
                url TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL
            )'
        );
    }

    public function create(string $url): array
    {
        if ($existing = $this->findByUrl($url)) {
            return $existing;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('INSERT INTO links (url, created_at) VALUES (:url, :created_at)');
            $stmt->execute([
                ':url' => $url,
                ':created_at' => gmdate('c'),
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $code = $this->shortener->numToShortString($id);

            $update = $this->pdo->prepare('UPDATE links SET code = :code WHERE id = :id');
            $update->execute([
                ':code' => $code,
                ':id' => $id,
            ]);

            $this->pdo->commit();

            return $this->findByCode($code);
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param string $shortCode
     */
    public function findByCode($shortCode): ?array
    {
        $codeValue = func_get_arg(0);
        if (!is_string($codeValue)) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, code, url, created_at FROM links WHERE code = :code LIMIT 1');
        $stmt->bindValue(':code', $codeValue, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * @param string $targetUrl
     */
    public function findByUrl($targetUrl): ?array
    {
        $targetValue = func_get_arg(0);
        if (!is_string($targetValue)) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, code, url, created_at FROM links WHERE url = :url LIMIT 1');
        $stmt->bindValue(':url', $targetValue, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();

        return $result ?: null;
    }
}
