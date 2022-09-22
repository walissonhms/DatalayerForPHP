<?php

namespace WalissonHms\DataLayer;

use PDO;
use PDOException;

/**
 * Class Connect
 * @package WalissonHms\DataLayer
 */
class Connect
{
    /** @var array */
    private static array $instance;

    /** @var PDOException|null */
    private static ?PDOException $error = null;

    private const CONFIG = [
        'driver' => getenv('DBDRIVER'),
        'host' => getenv('DBHOST'),
        'port' => getenv('DBPORT'),
        'dbname' => getenv('DBNAME'),
        'username' => getenv('DBUSER'),
        'passwd' => getenv('DBPASS'),
        'options' => [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_CASE => PDO::CASE_NATURAL
        ]
    ];

    /**
     * @param array|null $database
     * @return PDO|null
     */
    public static function getInstance(array $database = null): ?PDO
    {
        $dbConf = $database ?? self::CONFIG;
        $dbName = "{$dbConf["driver"]}-{$dbConf["dbname"]}@{$dbConf["host"]}";

        if (empty(self::$instance[$dbName])) {
            try {
                self::$instance[$dbName] = new PDO(
                    $dbConf["driver"] . ":host=" . $dbConf["host"] . ";dbname=" . $dbConf["dbname"] . ";port=" . $dbConf["port"],
                    $dbConf["username"],
                    $dbConf["passwd"],
                    $dbConf["options"]
                );
            } catch (PDOException $exception) {
                self::$error = $exception;
            }
        }

        return self::$instance[$dbName];
    }


    /**
     * @return PDOException|null
     */
    public static function getError(): ?PDOException
    {
        return self::$error;
    }

    /**
     * Connect constructor.
     */
    private function __construct()
    {
    }

    /**
     * Connect clone.
     */
    private function __clone()
    {
    }
}
