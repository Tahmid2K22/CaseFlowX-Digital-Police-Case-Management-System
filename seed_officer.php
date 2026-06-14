#!/usr/bin/env php
<?php
/**
 * seed_officer.php — CLI script to seed a test FIR Officer
 * 
 * Run once to create test officer HQ-001.
 * Idempotent: skips if officer already exists.
 */

require __DIR__ . '/db.php';

echo "Seeding FIR Officer...\n";

try {
    $db = get_db();
    
    // Check if officer already exists
    $stmt = $db->prepare('SELECT id, badge_number FROM officers WHERE badge_number = ?');
    $stmt->execute(['HQ-001']);
    $existing = $stmt->fetch();

    if ($existing) {
        echo "Officer HQ-001 already exists\n";
        exit(0);
    }

    // Insert new officer
    $insert = $db->prepare('
        INSERT INTO officers (badge_number, full_name, email, phone, station_code, role, password_hash, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $insert->execute([
        'HQ-001',
        'Inspector Test Officer',
        'inspector@caseflowx.local',
        '01712345678',
        'HQ01',
        'FIR Officer',
        password_hash('Test@1234', PASSWORD_DEFAULT),
        'active'
    ]);

    echo "Seeded officer HQ-001\n";

} catch (Exception $e) {
    error_log("Seed officer error: " . $e->getMessage());
    echo "Error seeding officer: " . $e->getMessage() . "\n";
    exit(1);
}