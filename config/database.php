<?php
define('DB_HOST', '103.108.220.222');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_USER', 'synapse_1_ssas');
define('DB_PASS', 'Vruski@GP10');
define('DB_NAME', 'synapse_1_pharma_care');
define('SITE_NAME', 'Pharma Care');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Determine base path from REQUEST_URI or use environment variable
$_basePath = getenv('SITE_BASE_PATH') ?: '';
if (empty($_basePath) && isset($_SERVER['SCRIPT_NAME'])) {
    // Auto-detect base path from script location
    $_scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $_basePath = ($_scriptDir === '/' || $_scriptDir === '\\') ? '' : $_scriptDir;
}

define('SITE_URL', $_protocol . '://' . $_host . $_basePath);
unset($_protocol, $_host, $_basePath, $_scriptDir);

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
                gst_number TEXT,
                gst_percentage REAL DEFAULT 0,
                tagline TEXT,
                usage_paused INTEGER DEFAULT 0,
                pause_message TEXT,
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
                manufacturer TEXT,
                batch_number TEXT,
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
                patient_id INTEGER,
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
                FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
                FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS patients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                phone TEXT,
                gender TEXT,
                age INTEGER,
                address TEXT,
                notes TEXT,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS patient_prescriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_id INTEGER NOT NULL,
                file_name TEXT NOT NULL,
                original_name TEXT,
                notes TEXT,
                uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
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

function mariadbTableExists(mysqli $conn, string $table): bool {
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

function mariadbColumnExists(mysqli $conn, string $table, string $column): bool {
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

    if (DB_DRIVER === 'mariadb' && $conn instanceof mysqli) {
        // Create base tables first (in correct order to satisfy foreign key constraints)
        if (!mariadbTableExists($conn, 'users')) {
            $conn->query("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                full_name VARCHAR(150),
                role ENUM('admin','owner','manager') NOT NULL DEFAULT 'manager',
                company_id INT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            
            // Insert default admin user
            $conn->query("INSERT IGNORE INTO users (username, password, email, full_name, role, company_id, is_active)
                VALUES ('admin', '\$2y\$12\$2CWGX7X2mAHoZgTUR6iXl.6ZTIUSV7UngCh.2/.ewQElwkziu9jF.', 'admin@pharmacare.com', 'System Administrator', 'admin', NULL, 1)");
        }

        if (!mariadbTableExists($conn, 'subscriptions')) {
            $conn->query("CREATE TABLE IF NOT EXISTS subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                duration_days INT NOT NULL DEFAULT 30,
                features TEXT,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            
            // Insert default subscription plans
            $conn->query("INSERT IGNORE INTO subscriptions (name, price, duration_days, features, is_active) VALUES
                ('Basic Plan', 29.99, 30, 'Up to 5 Users\nUp to 500 Products\nBasic Reports\nEmail Support', 1),
                ('Professional Plan', 59.99, 30, 'Up to 20 Users\nUnlimited Products\nAdvanced Reports\nPriority Support\nFinancial Statements', 1),
                ('Enterprise Plan', 99.99, 30, 'Unlimited Users\nUnlimited Products\nFull Reports Suite\n24/7 Support\nFinancial Statements\nCustom Branding', 1)");
        }

        if (!mariadbTableExists($conn, 'companies')) {
            $conn->query("CREATE TABLE IF NOT EXISTS companies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(200) NOT NULL,
                email VARCHAR(150),
                phone VARCHAR(30),
                address TEXT,
                logo VARCHAR(255),
                owner_id INT,
                subscription_id INT,
                marquee_message TEXT,
                gst_number VARCHAR(50),
                gst_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                tagline VARCHAR(255),
                usage_paused TINYINT(1) NOT NULL DEFAULT 0,
                pause_message TEXT,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'company_subscriptions')) {
            $conn->query("CREATE TABLE IF NOT EXISTS company_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                subscription_id INT NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'alerts')) {
            $conn->query("CREATE TABLE IF NOT EXISTS alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('warning','info','success','danger') NOT NULL DEFAULT 'info',
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            
            // Insert default alerts
            $conn->query("INSERT IGNORE INTO alerts (title, message, type, is_active) VALUES
                ('Welcome to Pharma Care PMS', 'Welcome to the Pharma Care Pharmacy Management System. Please configure your company settings to get started.', 'info', 1),
                ('System Update', 'The system has been updated to the latest version. All features are working normally.', 'success', 1)");
        }

        if (!mariadbTableExists($conn, 'footer_settings')) {
            $conn->query("CREATE TABLE IF NOT EXISTS footer_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content TEXT NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            
            // Insert default footer
            $conn->query("INSERT IGNORE INTO footer_settings (id, content) VALUES
                (1, '&copy; 2024 <strong>Pharma Care</strong>. All rights reserved. | Pharmacy Management System')");
        }

        if (!mariadbTableExists($conn, 'categories')) {
            $conn->query("CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                name VARCHAR(150) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'units')) {
            $conn->query("CREATE TABLE IF NOT EXISTS units (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                abbreviation VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'products')) {
            $conn->query("CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                name VARCHAR(200) NOT NULL,
                manufacturer VARCHAR(200),
                batch_number VARCHAR(100),
                category_id INT,
                unit_id INT,
                purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                selling_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                stock_quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                low_stock_threshold DECIMAL(10,2) NOT NULL DEFAULT 10.00,
                expiry_date DATE NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
                FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }

        // Now create dependent tables (patients, doctors) that reference companies
        if (!mariadbTableExists($conn, 'patients')) {
            $conn->query("CREATE TABLE IF NOT EXISTS patients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                name VARCHAR(200) NOT NULL,
                phone VARCHAR(30),
                gender ENUM('male','female','other') DEFAULT NULL,
                age INT DEFAULT NULL,
                address TEXT,
                notes TEXT,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'patient_prescriptions')) {
            $conn->query("CREATE TABLE IF NOT EXISTS patient_prescriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                original_name VARCHAR(255),
                notes TEXT,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'doctors')) {
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

        if (!mariadbTableExists($conn, 'sales')) {
            $conn->query("CREATE TABLE IF NOT EXISTS sales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                manager_id INT,
                doctor_id INT,
                patient_id INT,
                invoice_number VARCHAR(50) NOT NULL UNIQUE,
                customer_name VARCHAR(150),
                customer_phone VARCHAR(30),
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                final_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                payment_method ENUM('cash','card','mobile','credit') NOT NULL DEFAULT 'cash',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
                FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'sale_items')) {
            $conn->query("CREATE TABLE IF NOT EXISTS sale_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_id INT NOT NULL,
                product_id INT,
                product_name VARCHAR(200),
                quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'sale_returns')) {
            $conn->query("CREATE TABLE IF NOT EXISTS sale_returns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_id INT NOT NULL,
                company_id INT NOT NULL,
                return_number VARCHAR(50) NOT NULL UNIQUE,
                reason TEXT,
                returned_by INT,
                return_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (returned_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'sale_return_items')) {
            $conn->query("CREATE TABLE IF NOT EXISTS sale_return_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_return_id INT NOT NULL,
                sale_item_id INT,
                product_id INT,
                product_name VARCHAR(200),
                quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                FOREIGN KEY (sale_return_id) REFERENCES sale_returns(id) ON DELETE CASCADE,
                FOREIGN KEY (sale_item_id) REFERENCES sale_items(id) ON DELETE SET NULL,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'vendors')) {
            $conn->query("CREATE TABLE IF NOT EXISTS vendors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                name VARCHAR(200) NOT NULL,
                contact_person VARCHAR(150),
                phone VARCHAR(30),
                email VARCHAR(150),
                address TEXT,
                is_manufacturer TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'purchase_orders')) {
            $conn->query("CREATE TABLE IF NOT EXISTS purchase_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                vendor_id INT NOT NULL,
                po_number VARCHAR(50) NOT NULL UNIQUE,
                order_date DATE NOT NULL,
                expected_date DATE NULL,
                status ENUM('draft','ordered','received','cancelled') NOT NULL DEFAULT 'ordered',
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                notes TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'purchase_order_items')) {
            $conn->query("CREATE TABLE IF NOT EXISTS purchase_order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                purchase_order_id INT NOT NULL,
                product_name VARCHAR(200) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'supplier_returns')) {
            $conn->query("CREATE TABLE IF NOT EXISTS supplier_returns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                vendor_id INT NOT NULL,
                return_number VARCHAR(50) NOT NULL UNIQUE,
                return_date DATE NOT NULL,
                status ENUM('pending','sent','completed') NOT NULL DEFAULT 'pending',
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                notes TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'supplier_return_items')) {
            $conn->query("CREATE TABLE IF NOT EXISTS supplier_return_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                supplier_return_id INT NOT NULL,
                product_name VARCHAR(200) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                FOREIGN KEY (supplier_return_id) REFERENCES supplier_returns(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'indents')) {
            $conn->query("CREATE TABLE IF NOT EXISTS indents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                details TEXT,
                status ENUM('open','in-progress','closed') NOT NULL DEFAULT 'open',
                requested_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }

        if (!mariadbTableExists($conn, 'shipping_records')) {
            $conn->query("CREATE TABLE IF NOT EXISTS shipping_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                reference_type ENUM('purchase_order','supplier_return') NOT NULL,
                reference_id INT NOT NULL,
                carrier_name VARCHAR(150),
                tracking_number VARCHAR(100),
                shipping_status ENUM('pending','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
                shipped_at DATETIME NULL,
                delivered_at DATETIME NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        // Column additions and alterations (for existing databases)
        if (!mariadbColumnExists($conn, 'companies', 'gst_number')) {
            $conn->query("ALTER TABLE companies ADD COLUMN gst_number VARCHAR(50) NULL AFTER marquee_message");
        }
        if (!mariadbColumnExists($conn, 'companies', 'gst_percentage')) {
            $conn->query("ALTER TABLE companies ADD COLUMN gst_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER gst_number");
        }
        if (!mariadbColumnExists($conn, 'companies', 'tagline')) {
            $conn->query("ALTER TABLE companies ADD COLUMN tagline VARCHAR(255) NULL AFTER gst_percentage");
        }
        if (!mariadbColumnExists($conn, 'companies', 'usage_paused')) {
            $conn->query("ALTER TABLE companies ADD COLUMN usage_paused TINYINT(1) NOT NULL DEFAULT 0 AFTER tagline");
        }
        if (!mariadbColumnExists($conn, 'companies', 'pause_message')) {
            $conn->query("ALTER TABLE companies ADD COLUMN pause_message TEXT NULL AFTER usage_paused");
        }

        if (!mariadbColumnExists($conn, 'products', 'manufacturer')) {
            $conn->query("ALTER TABLE products ADD COLUMN manufacturer VARCHAR(200) NULL AFTER name");
        }
        if (!mariadbColumnExists($conn, 'products', 'batch_number')) {
            $conn->query("ALTER TABLE products ADD COLUMN batch_number VARCHAR(100) NULL AFTER manufacturer");
        }

        if (!mariadbColumnExists($conn, 'products', 'low_stock_threshold')) {
            $conn->query("ALTER TABLE products ADD COLUMN low_stock_threshold DECIMAL(10,2) NOT NULL DEFAULT 10.00 AFTER stock_quantity");
        }
        if (!mariadbColumnExists($conn, 'products', 'expiry_date')) {
            $conn->query("ALTER TABLE products ADD COLUMN expiry_date DATE NULL AFTER low_stock_threshold");
        }
        
        // These ALTER statements are for backward compatibility with existing databases
        // New installations will have these columns created via the CREATE TABLE statement
        if (mariadbTableExists($conn, 'sales')) {
            if (!mariadbColumnExists($conn, 'sales', 'doctor_id')) {
                $conn->query("ALTER TABLE sales ADD COLUMN doctor_id INT NULL AFTER manager_id");
                $conn->query("ALTER TABLE sales ADD CONSTRAINT fk_sales_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL");
            }
            if (!mariadbColumnExists($conn, 'sales', 'patient_id')) {
                $conn->query("ALTER TABLE sales ADD COLUMN patient_id INT NULL AFTER doctor_id");
                $conn->query("ALTER TABLE sales ADD CONSTRAINT fk_sales_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL");
            }
        }
    }

    if (DB_DRIVER === 'sqlite-temp' && $conn instanceof TempSQLiteConnection) {
        $conn->query("CREATE TABLE IF NOT EXISTS patients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            phone TEXT,
            gender TEXT,
            age INTEGER,
            address TEXT,
            notes TEXT,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS patient_prescriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NOT NULL,
            file_name TEXT NOT NULL,
            original_name TEXT,
            notes TEXT,
            uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        )");

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

        if (!sqliteColumnExists($conn, 'companies', 'gst_number')) {
            $conn->query("ALTER TABLE companies ADD COLUMN gst_number TEXT");
        }
        if (!sqliteColumnExists($conn, 'companies', 'gst_percentage')) {
            $conn->query("ALTER TABLE companies ADD COLUMN gst_percentage REAL NOT NULL DEFAULT 0");
        }
        if (!sqliteColumnExists($conn, 'companies', 'tagline')) {
            $conn->query("ALTER TABLE companies ADD COLUMN tagline TEXT");
        }
        if (!sqliteColumnExists($conn, 'companies', 'usage_paused')) {
            $conn->query("ALTER TABLE companies ADD COLUMN usage_paused INTEGER NOT NULL DEFAULT 0");
        }
        if (!sqliteColumnExists($conn, 'companies', 'pause_message')) {
            $conn->query("ALTER TABLE companies ADD COLUMN pause_message TEXT");
        }

        if (!sqliteColumnExists($conn, 'products', 'manufacturer')) {
            $conn->query("ALTER TABLE products ADD COLUMN manufacturer TEXT");
        }
        if (!sqliteColumnExists($conn, 'products', 'batch_number')) {
            $conn->query("ALTER TABLE products ADD COLUMN batch_number TEXT");
        }

        if (!sqliteColumnExists($conn, 'products', 'low_stock_threshold')) {
            $conn->query("ALTER TABLE products ADD COLUMN low_stock_threshold REAL NOT NULL DEFAULT 10");
        }
        if (!sqliteColumnExists($conn, 'products', 'expiry_date')) {
            $conn->query("ALTER TABLE products ADD COLUMN expiry_date TEXT");
        }
        if (!sqliteColumnExists($conn, 'sales', 'doctor_id')) {
            $conn->query("ALTER TABLE sales ADD COLUMN doctor_id INTEGER");
        }
        if (!sqliteColumnExists($conn, 'sales', 'patient_id')) {
            $conn->query("ALTER TABLE sales ADD COLUMN patient_id INTEGER");
        }

        $conn->query("CREATE TABLE IF NOT EXISTS sale_returns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_id INTEGER NOT NULL,
            company_id INTEGER NOT NULL,
            return_number TEXT NOT NULL UNIQUE,
            reason TEXT,
            returned_by INTEGER,
            return_amount REAL NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (returned_by) REFERENCES users(id) ON DELETE SET NULL
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS sale_return_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_return_id INTEGER NOT NULL,
            sale_item_id INTEGER,
            product_id INTEGER,
            product_name TEXT,
            quantity REAL NOT NULL DEFAULT 0,
            unit_price REAL NOT NULL DEFAULT 0,
            subtotal REAL NOT NULL DEFAULT 0,
            FOREIGN KEY (sale_return_id) REFERENCES sale_returns(id) ON DELETE CASCADE,
            FOREIGN KEY (sale_item_id) REFERENCES sale_items(id) ON DELETE SET NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS vendors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            contact_person TEXT,
            phone TEXT,
            email TEXT,
            address TEXT,
            is_manufacturer INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS purchase_orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            vendor_id INTEGER NOT NULL,
            po_number TEXT NOT NULL UNIQUE,
            order_date TEXT NOT NULL,
            expected_date TEXT,
            status TEXT NOT NULL DEFAULT 'ordered',
            total_amount REAL NOT NULL DEFAULT 0,
            notes TEXT,
            created_by INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS purchase_order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            purchase_order_id INTEGER NOT NULL,
            product_name TEXT NOT NULL,
            quantity REAL NOT NULL DEFAULT 0,
            unit_cost REAL NOT NULL DEFAULT 0,
            subtotal REAL NOT NULL DEFAULT 0,
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS supplier_returns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            vendor_id INTEGER NOT NULL,
            return_number TEXT NOT NULL UNIQUE,
            return_date TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            total_amount REAL NOT NULL DEFAULT 0,
            notes TEXT,
            created_by INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS supplier_return_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            supplier_return_id INTEGER NOT NULL,
            product_name TEXT NOT NULL,
            quantity REAL NOT NULL DEFAULT 0,
            unit_cost REAL NOT NULL DEFAULT 0,
            subtotal REAL NOT NULL DEFAULT 0,
            FOREIGN KEY (supplier_return_id) REFERENCES supplier_returns(id) ON DELETE CASCADE
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS indents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            details TEXT,
            status TEXT NOT NULL DEFAULT 'open',
            requested_by INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS shipping_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            reference_type TEXT NOT NULL,
            reference_id INTEGER NOT NULL,
            carrier_name TEXT,
            tracking_number TEXT,
            shipping_status TEXT NOT NULL DEFAULT 'pending',
            shipped_at TEXT,
            delivered_at TEXT,
            notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )");
    }
}

$conn = null;
$forceSqlite = in_array(strtolower((string) getenv('USE_SQLITE_TEMP')), ['1', 'true', 'yes', 'on'], true);

if (class_exists('mysqli') && !$forceSqlite) {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    } catch (mysqli_sql_exception $e) {
        $hint = ' Ensure MariaDB is running and DB settings are correct.';
        if (stripos($e->getMessage(), 'No such file or directory') !== false) {
            $hint = ' Socket connection failed. Use DB_HOST=127.0.0.1 for TCP and verify MariaDB is running.';
        }
        die('Database connection failed: ' . $e->getMessage() . $hint);
    }

    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    define('DB_DRIVER', 'mariadb');
} else {
    if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        die('MySQLi (MariaDB/MySQL) is not enabled and SQLite PDO driver is unavailable. Install php-mysql or php-sqlite3.');
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
