<?php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'pharma_care');
define('SITE_NAME', 'Pharma Care');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $_protocol . '://' . $_host . '/pharma-care');
unset($_protocol, $_host);

if (!defined('MYSQLI_ASSOC')) {
    define('MYSQLI_ASSOC', 1);
}
if (!defined('MYSQLI_NUM')) {
    define('MYSQLI_NUM', 2);
}
if (!defined('MYSQLI_BOTH')) {
    define('MYSQLI_BOTH', 3);
}

class TempSQLiteResult {
    private array $rows;
    private int $cursor = 0;
    public int $num_rows = 0;

    public function __construct(array $rows) {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc(): ?array {
        if ($this->cursor >= $this->num_rows) {
            return null;
        }
        return $this->rows[$this->cursor++];
    }

    public function fetch_row(): ?array {
        $row = $this->fetch_assoc();
        if ($row === null) {
            return null;
        }
        return array_values($row);
    }

    public function fetch_all(int $mode = MYSQLI_ASSOC): array {
        if ($mode === MYSQLI_NUM) {
            return array_map('array_values', $this->rows);
        }
        if ($mode === MYSQLI_BOTH) {
            $output = [];
            foreach ($this->rows as $row) {
                $numeric = array_values($row);
                $output[] = $row + $numeric;
            }
            return $output;
        }
        return $this->rows;
    }

    public function free(): void {
        $this->rows = [];
        $this->num_rows = 0;
        $this->cursor = 0;
    }
}

class TempSQLiteStmt {
    private TempSQLiteConnection $connection;
    private string $sql;
    private ?PDOStatement $statement = null;
    private array $params = [];
    private ?TempSQLiteResult $result = null;
    public int $affected_rows = 0;

    public function __construct(TempSQLiteConnection $connection, string $sql) {
        $this->connection = $connection;
        $this->sql = $sql;
    }

    public function bind_param(string $types, &...$vars): bool {
        $this->params = [];
        foreach ($vars as $index => &$value) {
            $this->params[$index + 1] = &$value;
        }
        return true;
    }

    public function execute(): bool {
        $this->result = null;
        $sql = $this->connection->normalizeSql($this->sql);
        try {
            $this->statement = $this->connection->pdo()->prepare($sql);
            foreach ($this->params as $position => $value) {
                $this->statement->bindValue($position, $value);
            }
            $ok = $this->statement->execute();
            $this->affected_rows = $this->statement->rowCount();
            $this->connection->setInsertId((int) $this->connection->pdo()->lastInsertId());

            if ($this->statement->columnCount() > 0) {
                $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);
                $this->result = new TempSQLiteResult($rows);
            }

            return $ok;
        } catch (Throwable $e) {
            $this->connection->setError($e->getMessage());
            return false;
        }
    }

    public function get_result(): TempSQLiteResult {
        if ($this->result === null) {
            return new TempSQLiteResult([]);
        }
        return $this->result;
    }

    public function close(): bool {
        $this->statement = null;
        $this->result = null;
        return true;
    }
}

class TempSQLiteConnection {
    private PDO $pdo;
    public string $connect_error = '';
    public string $error = '';
    public int $insert_id = 0;

    public function __construct(string $path) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $bootstrapNeeded = !file_exists($path) || filesize($path) === 0;
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        if ($bootstrapNeeded) {
            $this->bootstrap();
        }
    }

    public function pdo(): PDO {
        return $this->pdo;
    }

    public function setInsertId(int $id): void {
        $this->insert_id = $id;
    }

    public function setError(string $message): void {
        $this->error = $message;
    }

    public function query(string $sql) {
        $this->error = '';
        $normalized = $this->normalizeSql($sql);
        $isSelect = (bool) preg_match('/^\s*SELECT\b/i', $normalized);

        try {
            $stmt = $this->pdo->query($normalized);
            if ($stmt instanceof PDOStatement && $stmt->columnCount() > 0) {
                return new TempSQLiteResult($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            $this->insert_id = (int) $this->pdo->lastInsertId();
            return true;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            if ($isSelect) {
                return new TempSQLiteResult([]);
            }
            return false;
        }
    }

    public function prepare(string $sql): TempSQLiteStmt {
        $this->error = '';
        return new TempSQLiteStmt($this, $sql);
    }

    public function begin_transaction(): bool {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool {
        return $this->pdo->commit();
    }

    public function rollback(): bool {
        return $this->pdo->rollBack();
    }

    public function set_charset(string $charset): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function normalizeSql(string $sql): string {
        $normalized = trim($sql);
        $normalized = str_replace('`', '', $normalized);

        if (stripos($normalized, 'INSERT INTO footer_settings') !== false && stripos($normalized, 'ON DUPLICATE KEY UPDATE') !== false) {
            return "INSERT INTO footer_settings (id, content, updated_at) VALUES (1, ?, datetime('now')) ON CONFLICT(id) DO UPDATE SET content = ?, updated_at = datetime('now')";
        }

        $normalized = preg_replace('/\bINSERT\s+IGNORE\b/i', 'INSERT OR IGNORE', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bCURDATE\s*\(\s*\)/i', "date('now')", $normalized) ?? $normalized;
        $normalized = preg_replace('/\bNOW\s*\(\s*\)/i', "datetime('now')", $normalized) ?? $normalized;

        $normalized = preg_replace_callback(
            '/DATEDIFF\s*\(\s*([^,]+?)\s*,\s*date\(\s*\'now\'\s*\)\s*\)/i',
            static function (array $matches): string {
                $a = trim($matches[1]);
                return "CAST((julianday({$a}) - julianday(date('now'))) AS INTEGER)";
            },
            $normalized
        ) ?? $normalized;

        $normalized = preg_replace_callback(
            '/DATEDIFF\s*\(\s*([^,]+?)\s*,\s*([^)]+?)\s*\)/i',
            static function (array $matches): string {
                $a = trim($matches[1]);
                $b = trim($matches[2]);
                return "CAST((julianday({$a}) - julianday({$b})) AS INTEGER)";
            },
            $normalized
        ) ?? $normalized;

        return $normalized;
    }

    private function bootstrap(): void {
        $schema = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                full_name TEXT,
                role TEXT NOT NULL DEFAULT 'manager',
                company_id INTEGER NULL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                price REAL NOT NULL DEFAULT 0,
                duration_days INTEGER NOT NULL DEFAULT 30,
                features TEXT,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS companies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT,
                phone TEXT,
                address TEXT,
                logo TEXT,
                owner_id INTEGER,
                subscription_id INTEGER,
                marquee_message TEXT,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS company_subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                subscription_id INTEGER NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                type TEXT NOT NULL DEFAULT 'info',
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS footer_settings (
                id INTEGER PRIMARY KEY,
                content TEXT NOT NULL,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS units (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                abbreviation TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                category_id INTEGER,
                unit_id INTEGER,
                purchase_price REAL NOT NULL DEFAULT 0,
                selling_price REAL NOT NULL DEFAULT 0,
                stock_quantity REAL NOT NULL DEFAULT 0,
                low_stock_threshold REAL NOT NULL DEFAULT 10,
                expiry_date TEXT,
                description TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
                FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS doctors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                phone TEXT,
                specialization TEXT,
                commission_rate REAL NOT NULL DEFAULT 0,
                notes TEXT,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS sales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                manager_id INTEGER,
                doctor_id INTEGER,
                invoice_number TEXT NOT NULL UNIQUE,
                customer_name TEXT,
                customer_phone TEXT,
                total_amount REAL NOT NULL DEFAULT 0,
                discount REAL NOT NULL DEFAULT 0,
                final_amount REAL NOT NULL DEFAULT 0,
                payment_method TEXT NOT NULL DEFAULT 'cash',
                notes TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS sale_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sale_id INTEGER NOT NULL,
                product_id INTEGER,
                product_name TEXT,
                quantity REAL NOT NULL DEFAULT 1,
                unit_price REAL NOT NULL DEFAULT 0,
                purchase_price REAL NOT NULL DEFAULT 0,
                subtotal REAL NOT NULL DEFAULT 0,
                FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
            )"
        ];

        foreach ($schema as $ddl) {
            $this->pdo->exec($ddl);
        }

        $this->pdo->exec("INSERT OR IGNORE INTO users (id, username, password, email, full_name, role, company_id, is_active)
            VALUES (1, 'admin', '$2y$12$2CWGX7X2mAHoZgTUR6iXl.6ZTIUSV7UngCh.2/.ewQElwkziu9jF.', 'admin@pharmacare.com', 'System Administrator', 'admin', NULL, 1)");

        $this->pdo->exec("INSERT OR IGNORE INTO subscriptions (id, name, price, duration_days, features, is_active) VALUES
            (1, 'Basic Plan', 29.99, 30, 'Up to 5 Users\nUp to 500 Products\nBasic Reports\nEmail Support', 1),
            (2, 'Professional Plan', 59.99, 30, 'Up to 20 Users\nUnlimited Products\nAdvanced Reports\nPriority Support\nFinancial Statements', 1),
            (3, 'Enterprise Plan', 99.99, 30, 'Unlimited Users\nUnlimited Products\nFull Reports Suite\n24/7 Support\nFinancial Statements\nCustom Branding', 1)");

        $this->pdo->exec("INSERT OR IGNORE INTO footer_settings (id, content)
            VALUES (1, '&copy; 2024 <strong>Pharma Care</strong>. All rights reserved. | Pharmacy Management System')");

        $this->pdo->exec("INSERT OR IGNORE INTO alerts (id, title, message, type, is_active) VALUES
            (1, 'Welcome to Pharma Care PMS', 'Welcome to the Pharma Care Pharmacy Management System. Please configure your company settings to get started.', 'info', 1),
            (2, 'System Update', 'The system has been updated to the latest version. All features are working normally.', 'success', 1)");
    }
}

function mysqlTableExists(mysqli $conn, string $table): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (int) ($stmt->get_result()->fetch_row()[0] ?? 0) > 0;
    $stmt->close();
    return $exists;
}

function mysqlColumnExists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (int) ($stmt->get_result()->fetch_row()[0] ?? 0) > 0;
    $stmt->close();
    return $exists;
}

function sqliteColumnExists(TempSQLiteConnection $conn, string $table, string $column): bool {
    $result = $conn->query("PRAGMA table_info({$table})");
    if (!$result || !($result instanceof TempSQLiteResult)) {
        return false;
    }
    while ($row = $result->fetch_assoc()) {
        if (($row['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
}

function runSchemaMigrations($conn): void {
    if (!defined('DB_DRIVER')) {
        return;
    }

    if (DB_DRIVER === 'mysql' && $conn instanceof mysqli) {
        if (!mysqlTableExists($conn, 'doctors')) {
            $conn->query("CREATE TABLE IF NOT EXISTS doctors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                name VARCHAR(200) NOT NULL,
                phone VARCHAR(30),
                specialization VARCHAR(150),
                commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                notes TEXT,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        if (!mysqlColumnExists($conn, 'products', 'low_stock_threshold')) {
            $conn->query("ALTER TABLE products ADD COLUMN low_stock_threshold DECIMAL(10,2) NOT NULL DEFAULT 10.00 AFTER stock_quantity");
        }
        if (!mysqlColumnExists($conn, 'products', 'expiry_date')) {
            $conn->query("ALTER TABLE products ADD COLUMN expiry_date DATE NULL AFTER low_stock_threshold");
        }
        if (!mysqlColumnExists($conn, 'sales', 'doctor_id')) {
            $conn->query("ALTER TABLE sales ADD COLUMN doctor_id INT NULL AFTER manager_id");
            $conn->query("ALTER TABLE sales ADD CONSTRAINT fk_sales_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL");
        }
    }

    if (DB_DRIVER === 'sqlite-temp' && $conn instanceof TempSQLiteConnection) {
        $conn->query("CREATE TABLE IF NOT EXISTS doctors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            phone TEXT,
            specialization TEXT,
            commission_rate REAL NOT NULL DEFAULT 0,
            notes TEXT,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )");

        if (!sqliteColumnExists($conn, 'products', 'low_stock_threshold')) {
            $conn->query("ALTER TABLE products ADD COLUMN low_stock_threshold REAL NOT NULL DEFAULT 10");
        }
        if (!sqliteColumnExists($conn, 'products', 'expiry_date')) {
            $conn->query("ALTER TABLE products ADD COLUMN expiry_date TEXT");
        }
        if (!sqliteColumnExists($conn, 'sales', 'doctor_id')) {
            $conn->query("ALTER TABLE sales ADD COLUMN doctor_id INTEGER");
        }
    }
}

$conn = null;
$forceSqlite = in_array(strtolower((string) getenv('USE_SQLITE_TEMP')), ['1', 'true', 'yes', 'on'], true);

if (class_exists('mysqli') && !$forceSqlite) {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    } catch (mysqli_sql_exception $e) {
        $hint = ' Ensure MySQL is running and DB settings are correct.';
        if (stripos($e->getMessage(), 'No such file or directory') !== false) {
            $hint = ' Socket connection failed. Use DB_HOST=127.0.0.1 for TCP and verify MySQL is running.';
        }
        die('Database connection failed: ' . $e->getMessage() . $hint);
    }

    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    define('DB_DRIVER', 'mysql');
} else {
    if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        die('MySQLi is not enabled and SQLite PDO driver is unavailable. Install php-mysql or php-sqlite3.');
    }

    $sqlitePath = getenv('TEMP_SQLITE_PATH') ?: sys_get_temp_dir() . '/pharma_care_tmp.sqlite';

    try {
        $conn = new TempSQLiteConnection($sqlitePath);
    } catch (Throwable $e) {
        die('Temporary SQLite database initialization failed: ' . $e->getMessage());
    }

    define('DB_DRIVER', 'sqlite-temp');
}

runSchemaMigrations($conn);
