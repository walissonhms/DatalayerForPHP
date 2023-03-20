<?php

namespace App\Controllers\Components\DataLayer;

use PDO;
use PDOException;

/**
 * Class Connect
 * @package CoffeeCode\DataLayer
 */
class Connect
{
    /** @var PDO */
    private static $instance;

    /** @var PDOException */
    private static $error;

    /**
     * @return PDO
     */
    public static function getInstance(): ?PDO
    {
        if (empty(self::$instance)) {
            try {
                self::$instance = new PDO(
                    getenv('DBDRIVER') . ":host=" . getenv('DBHOST') . ";dbname=" . getenv('DBNAME') . ";port=" . getenv('DBPORT'),
                    getenv('DBUSER'),
                    getenv('DBPASS'),
                    DATA_LAYER_CONFIG["options"]
                );
            } catch (PDOException $exception) {
                self::$error = $exception;
            }
        }
        return self::$instance;
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
