<?php
/**
 * Database Service - Layanan Koneksi Database
 * 
 * Mengelola koneksi dan operasi database untuk aplikasi HR Kaori
 * Menggunakan PDO dengan prepared statements untuk keamanan
 * 
 * @author Tim Pengembang Kaori HR
 * @version 1.0.0
 */

namespace KaoriHR;

use PDO;
use PDOException;
use Monolog\Logger;

class DatabaseService
{
    private static ?PDO $pdo = null;
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Mendapatkan koneksi database PDO
     */
    public function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $this->createConnection();
        }
        
        return self::$pdo;
    }

    /**
     * Membuat koneksi database baru
     */
    private function createConnection(): void
    {
        $config = require __DIR__ . '/../config/database.php';
        
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            
            self::$pdo = new PDO(
                $dsn, 
                $config['username'], 
                $config['password'],
                $config['options']
            );

            $this->logger->info("Koneksi database berhasil dibuat", [
                'host' => $config['host'],
                'database' => $config['dbname']
            ]);

        } catch (PDOException $e) {
            $this->logger->error("Gagal koneksi database", [
                'error' => $e->getMessage(),
                'host' => $config['host'],
                'database' => $config['dbname']
            ]);
            
            throw new \Exception("Gagal koneksi database: " . $e->getMessage());
        }
    }

    /**
     * Eksekusi query dengan parameter
     */
    public function executeQuery(string $sql, array $params = []): array
    {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $this->logger->debug("Query dieksekusi", ['sql' => $sql, 'params' => $params]);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $this->logger->error("Error eksekusi query", [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Eksekusi query untuk single row
     */
    public function executeQuerySingle(string $sql, array $params = []): ?array
    {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $this->logger->debug("Single query dieksekusi", ['sql' => $sql, 'params' => $params]);
            
            return $stmt->fetch() ?: null;
            
        } catch (PDOException $e) {
            $this->logger->error("Error eksekusi single query", [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Insert data dan return last insert ID
     */
    public function insert(string $table, array $data): int
    {
        try {
            $pdo = $this->getConnection();
            
            $columns = implode(',', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            $insertId = (int) $pdo->lastInsertId();
            
            $this->logger->info("Data berhasil disimpan", [
                'table' => $table,
                'insert_id' => $insertId,
                'data' => $data
            ]);
            
            return $insertId;
            
        } catch (PDOException $e) {
            $this->logger->error("Error insert data", [
                'table' => $table,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Update data
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        try {
            $pdo = $this->getConnection();
            
            $setClause = [];
            foreach ($data as $column => $value) {
                $setClause[] = "{$column} = :{$column}";
            }
            $setClause = implode(', ', $setClause);
            
            $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
            
            $params = array_merge($data, $whereParams);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $affectedRows = $stmt->rowCount();
            
            $this->logger->info("Data berhasil diupdate", [
                'table' => $table,
                'affected_rows' => $affectedRows,
                'where' => $where,
                'data' => $data
            ]);
            
            return $affectedRows;
            
        } catch (PDOException $e) {
            $this->logger->error("Error update data", [
                'table' => $table,
                'data' => $data,
                'where' => $where,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Memulai transaction
     */
    public function beginTransaction(): void
    {
        $pdo = $this->getConnection();
        $pdo->beginTransaction();
        $this->logger->debug("Transaction dimulai");
    }

    /**
     * Commit transaction
     */
    public function commit(): void
    {
        $pdo = $this->getConnection();
        $pdo->commit();
        $this->logger->debug("Transaction di-commit");
    }

    /**
     * Rollback transaction
     */
    public function rollback(): void
    {
        $pdo = $this->getConnection();
        $pdo->rollBack();
        $this->logger->debug("Transaction di-rollback");
    }
}