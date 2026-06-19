<?php
/**
 * db.php — CaseFlowX SQLite Connection
 * Shared database utility. Include this in any page that needs DB access.
 */

define('DB_PATH', __DIR__ . '/data/caseflowx.sqlite');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        init_schema($pdo);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }

    return $pdo;
}

function init_schema(PDO $pdo): void {
    // Create unified users table including all personal info, location, and role-based columns
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        national_id TEXT NOT NULL UNIQUE,
        date_of_birth TEXT NOT NULL,
        gender TEXT NOT NULL CHECK(gender IN ('male', 'female', 'other')),
        phone TEXT NOT NULL UNIQUE,
        email TEXT UNIQUE,
        division TEXT NOT NULL,
        district TEXT NOT NULL,
        address TEXT NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('Admin', 'Officer', 'Investigator', 'Citizen')),
        status TEXT NOT NULL DEFAULT 'Active' CHECK(status IN ('Active', 'Suspended')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed default accounts if users table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        $seeds = [
            [
                'full_name' => 'System Administrator',
                'national_id' => '1234567890',
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'phone' => '01712345678',
                'email' => 'admin@caseflowx.gov.bd',
                'division' => 'Dhaka',
                'district' => 'Dhaka',
                'address' => 'Police Headquarters, Dhaka',
                'password' => password_hash('admin123', PASSWORD_BCRYPT),
                'role' => 'Admin',
                'status' => 'Active'
            ],
            [
                'full_name' => 'Officer Mahbub',
                'national_id' => '1234567891',
                'date_of_birth' => '1988-05-15',
                'gender' => 'male',
                'phone' => '01712345679',
                'email' => 'officer@caseflowx.gov.bd',
                'division' => 'Dhaka',
                'district' => 'Dhaka',
                'address' => 'Dhanmondi Police Station, Dhaka',
                'password' => password_hash('officer123', PASSWORD_BCRYPT),
                'role' => 'Officer',
                'status' => 'Active'
            ],
            [
                'full_name' => 'Investigator Salam',
                'national_id' => '1234567892',
                'date_of_birth' => '1985-11-20',
                'gender' => 'male',
                'phone' => '01712345680',
                'email' => 'investigator@caseflowx.gov.bd',
                'division' => 'Dhaka',
                'district' => 'Dhaka',
                'address' => 'Gulshan Police Station, Dhaka',
                'password' => password_hash('investigator123', PASSWORD_BCRYPT),
                'role' => 'Investigator',
                'status' => 'Active'
            ],
            [
                'full_name' => 'Citizen Tahmid',
                'national_id' => '1234567893',
                'date_of_birth' => '1995-08-25',
                'gender' => 'male',
                'phone' => '01712345681',
                'email' => 'citizen@caseflowx.gov.bd',
                'division' => 'Dhaka',
                'district' => 'Dhaka',
                'address' => 'Mirpur Road, Dhaka',
                'password' => password_hash('citizen123', PASSWORD_BCRYPT),
                'role' => 'Citizen',
                'status' => 'Active'
            ]
        ];

        $insert_stmt = $pdo->prepare("INSERT INTO users (full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password, role, status) VALUES (:full_name, :national_id, :date_of_birth, :gender, :phone, :email, :division, :district, :address, :password, :role, :status)");
        foreach ($seeds as $user) {
            $insert_stmt->execute($user);
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS citizens (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name       TEXT    NOT NULL,
            national_id     TEXT    NOT NULL UNIQUE,
            date_of_birth   TEXT    NOT NULL,
            gender          TEXT    NOT NULL CHECK(gender IN ('male','female','other')),
            phone           TEXT    NOT NULL UNIQUE,
            email           TEXT    UNIQUE,
            division        TEXT    NOT NULL,
            district        TEXT    NOT NULL,
            address         TEXT    NOT NULL,
            password_hash   TEXT    NOT NULL,
            status          TEXT    NOT NULL DEFAULT 'active' CHECK(status IN ('active','suspended')),
            created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
            last_login      TEXT
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cases (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            citizen_id      INTEGER,
            case_number     TEXT    NOT NULL UNIQUE,
            title           TEXT    NOT NULL,
            description     TEXT    NOT NULL,
            status          TEXT    NOT NULL DEFAULT 'open' CHECK(status IN ('open','in_progress','resolved','closed','Draft','Submitted','Under Review','Registered','Rejected')),
            priority        TEXT    NOT NULL DEFAULT 'low' CHECK(priority IN ('low','medium','high')),
            created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
            investigator_id INTEGER,
            fir_number      TEXT,
            complainant_name TEXT,
            complainant_nid  TEXT,
            complainant_phone TEXT,
            complainant_address TEXT,
            incident_date    TEXT,
            incident_time    TEXT,
            incident_location TEXT,
            incident_description TEXT,
            sections_applied TEXT,
            witness_details  TEXT,
            officer_id       INTEGER,
            station_code     TEXT,
            investigating_officer TEXT,
            modified_by      INTEGER,
            modified_at      TEXT,
            FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (investigator_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (officer_id) REFERENCES officers(id) ON DELETE SET NULL
        )
    ");

    // Dynamic migration: Ensure all required columns exist in the cases table
    $requiredCaseCols = [
        'fir_number'            => 'TEXT',
        'complainant_name'      => 'TEXT',
        'complainant_nid'       => 'TEXT',
        'complainant_phone'     => 'TEXT',
        'complainant_address'   => 'TEXT',
        'incident_date'         => 'TEXT',
        'incident_time'         => 'TEXT',
        'incident_location'     => 'TEXT',
        'incident_description'  => 'TEXT',
        'sections_applied'      => 'TEXT',
        'witness_details'       => 'TEXT',
        'officer_id'            => 'INTEGER',
        'station_code'          => 'TEXT',
        'investigating_officer' => 'TEXT',
        'modified_by'           => 'INTEGER',
        'modified_at'           => 'TEXT'
    ];
    try {
        $existingCols = [];
        $stmt = $pdo->query("PRAGMA table_info(cases)");
        while ($row = $stmt->fetch()) {
            $existingCols[] = $row['name'];
        }
        foreach ($requiredCaseCols as $col => $type) {
            if (!in_array($col, $existingCols, true)) {
                $pdo->exec("ALTER TABLE cases ADD COLUMN {$col} {$type}");
            }
        }
    } catch (PDOException $e) {
        error_log("Cases table migration failed: " . $e->getMessage());
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cases_citizen ON cases(citizen_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cases_status ON cases(status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cases_officer ON cases(officer_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cases_created ON cases(created_at)');

    // Officers table with role support
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS officers (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            badge_number    TEXT    NOT NULL UNIQUE,
            full_name       TEXT    NOT NULL,
            email           TEXT    UNIQUE,
            phone           TEXT    NOT NULL UNIQUE,
            station_code    TEXT    NOT NULL,
            role            TEXT    NOT NULL DEFAULT 'FIR Officer' CHECK(role IN ('FIR Officer','Supervisor','Admin')),
            password_hash   TEXT    NOT NULL,
            status          TEXT    NOT NULL DEFAULT 'active' CHECK(status IN ('active','suspended')),
            created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
            last_login      TEXT
        )
    ");

    // FIR Records table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fir_records (
            id                      INTEGER PRIMARY KEY AUTOINCREMENT,
            fir_number              TEXT    NOT NULL UNIQUE,
            complainant_name        TEXT    NOT NULL,
            complainant_nid         TEXT    NOT NULL,
            complainant_phone       TEXT,
            complainant_address     TEXT,
            incident_date           TEXT    NOT NULL,
            incident_time           TEXT,
            incident_location       TEXT    NOT NULL,
            incident_description    TEXT    NOT NULL,
            sections_applied        TEXT,
            witness_details         TEXT,
            officer_id              INTEGER,
            station_code            TEXT    NOT NULL,
            status                  TEXT    NOT NULL DEFAULT 'Draft' CHECK(status IN ('Draft','Submitted','Under Review','Registered','Rejected')),
            priority                TEXT    NOT NULL DEFAULT 'medium' CHECK(priority IN ('low','medium','high')),
            created_by              INTEGER,
            created_at              TEXT    NOT NULL DEFAULT (datetime('now')),
            modified_by             INTEGER,
            modified_at             TEXT,
            FOREIGN KEY (officer_id) REFERENCES officers(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES officers(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fir_evidence (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            case_id         INTEGER NOT NULL,
            file_name       TEXT    NOT NULL,
            file_path       TEXT    NOT NULL,
            file_type       TEXT    NOT NULL,
            file_size       INTEGER NOT NULL,
            uploaded_by     INTEGER NOT NULL,
            uploaded_at     TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES officers(id) ON DELETE RESTRICT
        )
    ");

    // Dynamic migration: Ensure case_id column exists in fir_evidence table
    try {
        $existingEvCols = [];
        $stmt = $pdo->query("PRAGMA table_info(fir_evidence)");
        while ($row = $stmt->fetch()) {
            $existingEvCols[] = $row['name'];
        }
        if (!in_array('case_id', $existingEvCols, true)) {
            $pdo->exec("ALTER TABLE fir_evidence ADD COLUMN case_id INTEGER");
        }
    } catch (PDOException $e) {
        error_log("Evidence table migration failed: " . $e->getMessage());
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_evidence_case ON fir_evidence(case_id)');

    // FIR indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fir_officer ON fir_records(officer_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fir_status ON fir_records(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fir_number ON fir_records(fir_number)");

    // Notifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER,
            title       TEXT NOT NULL,
            message     TEXT NOT NULL,
            is_read     INTEGER DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
}

function require_citizen(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['logged_in']) || empty($_SESSION['citizen_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }

    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM citizens WHERE id = ? AND status = ?');
    $stmt->execute([$_SESSION['citizen_id'], 'active']);
    $citizen = $stmt->fetch();

    if (!$citizen) {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Citizen not found or suspended']);
        exit;
    }

    return $citizen;
}

function require_officer(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['logged_in']) || empty($_SESSION['officer_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }

    $valid_roles = ['FIR Officer', 'Supervisor', 'Admin'];
    if (!in_array($_SESSION['officer_role'] ?? '', $valid_roles, true)) {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid officer role']);
        exit;
    }

    $db = get_db();
    $stmt = $db->prepare('SELECT id, badge_number, full_name, email, phone, station_code, role, status, created_at FROM officers WHERE id = ? AND status = ?');
    $stmt->execute([$_SESSION['officer_id'], 'active']);
    $officer = $stmt->fetch();

    if (!$officer) {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Officer not found or suspended']);
        exit;
    }

    return $officer;
}
