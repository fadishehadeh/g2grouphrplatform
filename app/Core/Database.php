<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private array $config;
    private ?PDO $connection = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $this->config['driver'] ?? 'mysql',
            $this->config['host'] ?? '127.0.0.1',
            $this->config['port'] ?? '3306',
            $this->config['database'] ?? '',
            $this->config['charset'] ?? 'utf8mb4'
        );

        try {
            $this->connection = new PDO(
                $dsn,
                $this->config['username'] ?? '',
                $this->config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new \RuntimeException('Database connection failed: ' . $exception->getMessage(), 0, $exception);
        }

        return $this->connection;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($params);
        $result = $statement->fetch();

        return $result === false ? null : $result;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }

    public function execute(string $sql, array $params = []): bool
    {
        $statement = $this->connection()->prepare($sql);

        return $statement->execute($params);
    }

    public function lastInsertId(): string
    {
        return $this->connection()->lastInsertId();
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }
}