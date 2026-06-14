<?php
// db.php - Database connection and initialization

$db_path = __DIR__ . '/caseflowx.db';

try {
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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

    // Seed default accounts if table is empty
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

    // Create unified cases table referencing the unified users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS cases (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        citizen_id      INTEGER NOT NULL,
        case_number     TEXT    NOT NULL UNIQUE,
        title           TEXT    NOT NULL,
        description     TEXT    NOT NULL,
        status          TEXT    NOT NULL DEFAULT 'open' CHECK(status IN ('open','in_progress','resolved','closed')),
        priority        TEXT    NOT NULL DEFAULT 'low' CHECK(priority IN ('low','medium','high')),
        created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
        investigator_id INTEGER,
        FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (investigator_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    // Seed default cases if cases table is empty
    $stmt_cases = $pdo->query("SELECT COUNT(*) FROM cases");
    $cases_count = $stmt_cases->fetchColumn();
    if ($cases_count == 0) {
        $mock_cases = [
            [
                'citizen_id' => 4, // Citizen Tahmid
                'case_number' => 'CF001-2026',
                'title' => 'Burglar Alert at Dhanmondi',
                'description' => 'A burglary attempt was made at my residence in Dhanmondi. The suspect fled when the alarm went off.',
                'status' => 'in_progress',
                'priority' => 'high',
                'investigator_id' => 3 // Investigator Salam
            ],
            [
                'citizen_id' => 4, // Citizen Tahmid
                'case_number' => 'CF002-2026',
                'title' => 'Lost Mobile Phone',
                'description' => 'I lost my iPhone 14 Pro near Dhanmondi Lake around 5 PM yesterday.',
                'status' => 'open',
                'priority' => 'low',
                'investigator_id' => 3 // Investigator Salam
            ],
            [
                'citizen_id' => 4, // Citizen Tahmid
                'case_number' => 'CF003-2026',
                'title' => 'Online Harassment Complaint',
                'description' => 'Receiving abusive messages and threats on Facebook from an unknown profile.',
                'status' => 'open',
                'priority' => 'medium',
                'investigator_id' => null
            ]
        ];

        $insert_case = $pdo->prepare("INSERT INTO cases (citizen_id, case_number, title, description, status, priority, investigator_id) VALUES (:citizen_id, :case_number, :title, :description, :status, :priority, :investigator_id)");
        foreach ($mock_cases as $c) {
            $insert_case->execute($c);
        }
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/**
 * Returns the PDO database connection instance.
 */
function get_db(): PDO {
    global $pdo;
    return $pdo;
}

return $pdo;
