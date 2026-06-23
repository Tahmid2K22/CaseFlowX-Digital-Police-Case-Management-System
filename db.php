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

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    init_schema($pdo);
    return $pdo;
}

function init_schema(PDO $pdo): void {
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
            status          TEXT    NOT NULL DEFAULT 'Open' CHECK(status IN ('Open','Closed','Pending','open','in_progress','resolved','closed','Submitted','Under Review','Registered','Rejected')),
            priority        TEXT    NOT NULL DEFAULT 'medium' CHECK(priority IN ('low','medium','high')),
            created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
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
            FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE CASCADE,
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

    // Dynamic migration: Ensure cases status CHECK constraint supports 'Open', 'Closed', 'Pending'
    try {
        $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='cases'");
        $row = $stmt->fetch();
        if ($stmt) {
            $stmt->closeCursor();
        }
        if ($row && strpos($row['sql'], "'Pending'") === false) {
            $pdo->exec("PRAGMA foreign_keys = OFF;");
            $pdo->exec("ALTER TABLE cases RENAME TO cases_old;");
            $pdo->exec("
                CREATE TABLE cases (
                    id              INTEGER PRIMARY KEY AUTOINCREMENT,
                    citizen_id      INTEGER,
                    case_number     TEXT    NOT NULL UNIQUE,
                    title           TEXT    NOT NULL,
                    description     TEXT    NOT NULL,
                    status          TEXT    NOT NULL DEFAULT 'Open' CHECK(status IN ('Open','Closed','Pending','open','in_progress','resolved','closed','Submitted','Under Review','Registered','Rejected')),
                    priority        TEXT    NOT NULL DEFAULT 'medium' CHECK(priority IN ('low','medium','high')),
                    created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
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
                    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE CASCADE,
                    FOREIGN KEY (officer_id) REFERENCES officers(id) ON DELETE SET NULL
                )
            ");
            $pdo->exec("
                INSERT INTO cases (
                    id, citizen_id, case_number, title, description, status, priority, created_at,
                    fir_number, complainant_name, complainant_nid, complainant_phone, complainant_address,
                    incident_date, incident_time, incident_location, incident_description, sections_applied,
                    witness_details, officer_id, station_code, investigating_officer, modified_by, modified_at
                )
                SELECT 
                    id, citizen_id, case_number, title, description, 
                    CASE 
                        WHEN status = 'open' THEN 'Open'
                        WHEN status = 'closed' THEN 'Closed'
                        WHEN status = 'in_progress' THEN 'Pending'
                        WHEN status = 'Submitted' THEN 'Open'
                        ELSE status
                    END,
                    priority, created_at,
                    fir_number, complainant_name, complainant_nid, complainant_phone, complainant_address,
                    incident_date, incident_time, incident_location, incident_description, sections_applied,
                    witness_details, officer_id, station_code, investigating_officer, modified_by, modified_at
                FROM cases_old
            ");
            $pdo->exec("DROP TABLE cases_old;");
            $pdo->exec("PRAGMA foreign_keys = ON;");
        }
    } catch (PDOException $e) {
        error_log("Cases check constraint migration failed: " . $e->getMessage());
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
            status                  TEXT    NOT NULL DEFAULT 'Open' CHECK(status IN ('Open','Closed','Pending','Draft','Submitted','Under Review','Registered','Rejected','open','in_progress','resolved','closed')),
            priority                TEXT    NOT NULL DEFAULT 'medium' CHECK(priority IN ('low','medium','high')),
            created_by              INTEGER,
            created_at              TEXT    NOT NULL DEFAULT (datetime('now')),
            modified_by             INTEGER,
            modified_at             TEXT,
            FOREIGN KEY (officer_id) REFERENCES officers(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES officers(id) ON DELETE SET NULL
        )
    ");

    // Dynamic migration: Ensure fir_records status CHECK constraint supports 'Open', 'Closed', 'Pending'
    try {
        $stmtFir = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='fir_records'");
        $rowFir = $stmtFir->fetch();
        if ($stmtFir) {
            $stmtFir->closeCursor();
        }
        if ($rowFir && strpos($rowFir['sql'], "'Pending'") === false) {
            $pdo->exec("PRAGMA foreign_keys = OFF;");
            $pdo->exec("ALTER TABLE fir_records RENAME TO fir_records_old;");
            $pdo->exec("
                CREATE TABLE fir_records (
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
                    status                  TEXT    NOT NULL DEFAULT 'Open' CHECK(status IN ('Open','Closed','Pending','Draft','Submitted','Under Review','Registered','Rejected','open','in_progress','resolved','closed')),
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
                INSERT INTO fir_records (
                    id, fir_number, complainant_name, complainant_nid, complainant_phone, complainant_address,
                    incident_date, incident_time, incident_location, incident_description, sections_applied,
                    witness_details, officer_id, station_code, status, priority, created_by, created_at,
                    modified_by, modified_at
                )
                SELECT 
                    id, fir_number, complainant_name, complainant_nid, complainant_phone, complainant_address,
                    incident_date, incident_time, incident_location, incident_description, sections_applied,
                    witness_details, officer_id, station_code,
                    CASE 
                        WHEN status = 'Draft' THEN 'Draft'
                        WHEN status = 'Submitted' THEN 'Open'
                        WHEN status = 'open' THEN 'Open'
                        WHEN status = 'closed' THEN 'Closed'
                        ELSE status
                    END,
                    priority, created_by, created_at,
                    modified_by, modified_at
                FROM fir_records_old
            ");
            $pdo->exec("DROP TABLE fir_records_old;");
            $pdo->exec("PRAGMA foreign_keys = ON;");
        }
    } catch (PDOException $e) {
        error_log("Fir_records check constraint migration failed: " . $e->getMessage());
    }

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

    // Dynamic migration: Ensure fir_evidence table matches the updated schema (uses case_id, drops fir_id or makes it nullable)
    try {
        $stmtEvCheck = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='fir_evidence'");
        $rowEvCheck = $stmtEvCheck->fetch();
        if ($stmtEvCheck) {
            $stmtEvCheck->closeCursor();
        }
        if ($rowEvCheck && strpos($rowEvCheck['sql'], 'fir_id') !== false) {
            $pdo->exec("PRAGMA foreign_keys = OFF;");
            $pdo->exec("ALTER TABLE fir_evidence RENAME TO fir_evidence_old;");
            $pdo->exec("
                CREATE TABLE fir_evidence (
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
            $pdo->exec("
                INSERT INTO fir_evidence (id, case_id, file_name, file_path, file_type, file_size, uploaded_by, uploaded_at)
                SELECT 
                    id, 
                    COALESCE(case_id, (SELECT c.id FROM cases c JOIN fir_records f ON c.fir_number = f.fir_number WHERE f.id = fir_evidence_old.fir_id LIMIT 1)),
                    file_name, file_path, file_type, file_size, uploaded_by, uploaded_at
                FROM fir_evidence_old
                WHERE COALESCE(case_id, (SELECT c.id FROM cases c JOIN fir_records f ON c.fir_number = f.fir_number WHERE f.id = fir_evidence_old.fir_id LIMIT 1)) IS NOT NULL
            ");
            $pdo->exec("DROP TABLE fir_evidence_old;");
            $pdo->exec("PRAGMA foreign_keys = ON;");
        }
    } catch (PDOException $e) {
        error_log("Evidence table schema migration failed: " . $e->getMessage());
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_evidence_case ON fir_evidence(case_id)');

    // FIR indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fir_officer ON fir_records(officer_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fir_status ON fir_records(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fir_number ON fir_records(fir_number)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS criminals (
            id                  INTEGER PRIMARY KEY AUTOINCREMENT,
            name                TEXT    NOT NULL,
            nid                 TEXT    NOT NULL UNIQUE,
            date_of_birth       TEXT,
            gender              TEXT,
            photo_path          TEXT,
            crimes_committed    TEXT,
            status              TEXT    NOT NULL DEFAULT 'Wanted' CHECK(status IN ('Wanted', 'In Custody', 'Released', 'Convicted')),
            last_known_location TEXT,
            created_at          TEXT    NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_criminals_nid ON criminals(nid)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_criminals_name ON criminals(name)');

    // Seed initial criminals if empty
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM criminals")->fetchColumn();
        if ($count === 0) {
            $stmtSeed = $pdo->prepare("
                INSERT INTO criminals (name, nid, date_of_birth, gender, crimes_committed, status, last_known_location)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtSeed->execute(['Akash Ahmed', '1234567890', '1988-10-12', 'male', 'Robbery, Assault', 'Wanted', 'Mirpur, Dhaka']);
            $stmtSeed->execute(['Tasnim Rahman', '0987654321', '1992-04-15', 'female', 'Fraud, Embezzlement', 'In Custody', 'Central Jail, Dhaka']);
            $stmtSeed->execute(['Kamal Uddin', '1122334455', '1980-08-20', 'male', 'Drug Trafficking', 'Convicted', 'Kashimpur Jail']);
            $stmtSeed->execute(['Sohail Rana', '5544332211', '1995-12-05', 'male', 'Theft', 'Released', 'Uttara, Dhaka']);
        }
    } catch (PDOException $e) {
        error_log("Criminals table seeding failed: " . $e->getMessage());
    }
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
