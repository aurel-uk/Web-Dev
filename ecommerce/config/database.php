<?php
/**
 * ============================================
 * DATABASE CONFIGURATION
 * ============================================
 *
 * This file handles the database connection using PDO.
 * PDO (PHP Data Objects) is a database abstraction layer that
 * provides a secure way to interact with databases.
 *
 * WHY PDO?
 * 1. Supports prepared statements (prevents SQL injection)
 * 2. Works with multiple database types
 * 3. Provides consistent error handling
 *
 * HOW TO CONFIGURE:
 * 1. Install XAMPP/WAMP
 * 2. Start Apache and MySQL services
 * 3. Create the database using database.sql
 * 4. Update the credentials below if needed
 * ============================================
 */

// Database configuration constants
// These values should match your local setup

// Database host - 'localhost' for local development
define('DB_HOST', 'localhost');

// Database name - must match the database you created
define('DB_NAME', 'ecommerce_db');

// Database username - 'root' is default for XAMPP/WAMP
define('DB_USER', 'root');

// Database password - empty by default for XAMPP/WAMP
// IMPORTANT: Change this in production!
define('DB_PASS', '');

// Character set - utf8mb4 supports emojis and special characters
define('DB_CHARSET', 'utf8mb4');

/**
 * Database Class
 *
 * This class implements the Singleton pattern to ensure
 * only one database connection exists throughout the application.
 *
 * WHAT IS SINGLETON?
 * A design pattern that restricts a class to a single instance.
 * This is useful for database connections because:
 * - Opening multiple connections is expensive
 * - We only need one connection per request
 */
class Database
{
    // The single instance of this class
    private static $instance = null;

    // The PDO connection object
    private $connection = null;

    /**
     * Private constructor - prevents direct instantiation
     *
     * This is called when getInstance() creates the singleton.
     * It establishes the database connection.
     */
    private function __construct()
    {
        try {
            // Build the DSN (Data Source Name)
            // Format: mysql:host=HOST;dbname=NAME;charset=CHARSET
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            // PDO options for security and error handling
            $options = [
                // Throw exceptions on errors (helps debugging)
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                // Return results as associative arrays
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // Use real prepared statements (not emulated)
                // This is important for security!
                PDO::ATTR_EMULATE_PREPARES => false,

                // Use persistent connections for better performance
                PDO::ATTR_PERSISTENT => true
            ];

            // Create the PDO connection
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {
            // If connection fails, show a user-friendly error
            // In production, log the error and show a generic message
            die("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get the singleton instance
     *
     * This is the only way to get a Database object.
     * It creates the instance on first call, then returns it on subsequent calls.
     *
     * @return Database The singleton instance
     */
    public static function getInstance(): Database
    {
        // If no instance exists, create one
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the PDO connection
     *
     * Use this to get the actual PDO object for queries.
     *
     * @return PDO The database connection
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Prevent cloning of the singleton
     */
    private function __clone() {}

    /**
     * Prevent unserialization of the singleton
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper function to get the database connection
 *
 * This provides a simple way to access the database from anywhere.
 *
 * USAGE EXAMPLE:
 * $db = getDB();
 * $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
 * $stmt->execute([1]);
 * $user = $stmt->fetch();
 *
 * @return PDO The database connection
 */
function getDB(): PDO
{
    return Database::getInstance()->getConnection();
}

/**
 * ============================================
 * EXAMPLE QUERIES (FOR LEARNING)
 * ============================================
 *
 * 1. SELECT with WHERE:
 *    $db = getDB();
 *    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
 *    $stmt->execute([$email]);
 *    $user = $stmt->fetch();
 *
 * 2. INSERT:
 *    $db = getDB();
 *    $stmt = $db->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
 *    $stmt->execute([$name, $email]);
 *    $newId = $db->lastInsertId();
 *
 * 3. UPDATE:
 *    $db = getDB();
 *    $stmt = $db->prepare("UPDATE users SET name = ? WHERE id = ?");
 *    $stmt->execute([$name, $id]);
 *
 * 4. DELETE:
 *    $db = getDB();
 *    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
 *    $stmt->execute([$id]);
 *
 * IMPORTANT: Always use prepared statements with ?
 * NEVER concatenate user input directly into SQL!
 *
 * BAD:  $db->query("SELECT * FROM users WHERE id = " . $_GET['id']);
 * GOOD: $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
 *       $stmt->execute([$_GET['id']]);
 * ============================================
 */
