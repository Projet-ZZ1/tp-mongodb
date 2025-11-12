<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__.'/vendor/autoload.php';

use MongoDB\Database;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Predis\Client;
use Dotenv\Dotenv;

// env configuration
//(Dotenv\Dotenv::createImmutable(__DIR__))->load();
// Charger le fichier .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
function getTwig(): Environment
{
    // twig configuration
    return new Environment(new FilesystemLoader('../templates'));
}

function getMongoDbManager(): Database
{
    $client = new MongoDB\Client("mongodb://{$_ENV['MDB_USER']}:{$_ENV['MDB_PASS']}@{$_ENV['MDB_SRV']}:{$_ENV['MDB_PORT']}");
    return $client->selectDatabase($_ENV['MDB_DB']);
}


function getRedisClient() {
    $host = $_ENV['REDIS_HOST'] ?? 'tpmongo-redis';
    $port = $_ENV['REDIS_PORT'] ?? 6379;
    $enable = $_ENV['REDIS_ENABLE'] ?? 'false';

    if ($enable !== 'true') return null;

    try {
        $client = new Client([
            'scheme' => 'tcp',
            'host' => $host,
            'port' => $port,
        ]);
        $client->ping();
        return $client;
    } catch (Exception $e) {
        error_log("Erreur Redis : " . $e->getMessage());
        return null;
    }
}