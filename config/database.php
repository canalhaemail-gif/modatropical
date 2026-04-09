<?php
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_NAME = 'cardapio_digital';
const DB_PORT = '3306';
const DB_CHARSET = 'utf8mb4';
const DB_USER = 'loja';
const DB_PASS = 'ModaTropical@2026';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $exception) {
        http_response_code(500);
        exit('Falha ao conectar ao banco de dados. Verifique o arquivo config/database.php e a importacao do SQL.');
    }

    return $pdo;
}

