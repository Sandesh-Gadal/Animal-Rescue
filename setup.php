<?php
declare(strict_types=1);

/**
 * One-file installer: writes config.php from submitted DB credentials,
 * creates/upgrades the database schema, and creates the first Admin account.
 * Delete this file from the server once setup is complete.
 */

$configPath = __DIR__.'/config.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_name('DBMAP_SETUP');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__.'/includes/functions.php';

function setup_requirements(): array {
    return [
        'PHP 8.2 or newer' => ['ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'fatal' => true, 'detail' => 'Running '.PHP_VERSION],
        'pdo_mysql extension' => ['ok' => extension_loaded('pdo_mysql'), 'fatal' => true, 'detail' => 'Required to talk to MySQL/MariaDB'],
        'mbstring extension' => ['ok' => extension_loaded('mbstring'), 'fatal' => true, 'detail' => 'Required for text handling'],
        'gd extension' => ['ok' => extension_loaded('gd'), 'fatal' => false, 'detail' => 'Recommended for photo handling'],
        'zip extension' => ['ok' => extension_loaded('zip'), 'fatal' => false, 'detail' => 'Needed for Excel (.xlsx) export; CSV export still works without it'],
        'uploads/ writable' => ['ok' => is_dir(__DIR__.'/uploads') && is_writable(__DIR__.'/uploads'), 'fatal' => false, 'detail' => 'Needed for report photo uploads'],
    ];
}

function requirements_pass(array $req): bool {
    foreach ($req as $r) if ($r['fatal'] && !$r['ok']) return false;
    return true;
}

function render_requirements(array $req): string {
    $rows = '';
    foreach ($req as $label => $r) {
        $badge = $r['ok'] ? '<span class="success" style="padding:2px 10px;border-radius:999px">OK</span>' : ($r['fatal'] ? '<span class="danger" style="padding:2px 10px;border-radius:999px">Missing</span>' : '<span class="warning" style="padding:2px 10px;border-radius:999px">Missing</span>');
        $rows .= '<tr><td>'.e($label).'</td><td>'.$badge.'</td><td class="small">'.e($r['detail']).'</td></tr>';
    }
    return '<table class="table" style="width:100%;margin-bottom:20px"><thead><tr><th>Requirement</th><th>Status</th><th></th></tr></thead><tbody>'.$rows.'</tbody></table>';
}

function page(string $title, string $body): never {
    $css = '/assets/css/app.css';
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<link rel="stylesheet" href="'.e($css).'"><title>'.e($title).' — Setup</title></head><body>'
        .'<div class="container" style="max-width:680px;padding:40px 0">'.$body.'</div></body></html>';
    exit;
}

$requirements = setup_requirements();

// ---------------------------------------------------------------------
// STEP 1: no config.php yet -> collect DB credentials and write one.
// ---------------------------------------------------------------------
if (!file_exists($configPath)) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        if (!requirements_pass($requirements)) {
            $err = 'Fix the missing requirements above before continuing.';
        } else {
            $appName = trim((string)($_POST['app_name'] ?? 'Livestock Carcass Management System')) ?: 'Livestock Carcass Management System';
            $baseUrl = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');
            $timezone = trim((string)($_POST['timezone'] ?? 'Asia/Kathmandu')) ?: 'Asia/Kathmandu';
            $host = trim((string)($_POST['db_host'] ?? 'localhost'));
            $port = (int)($_POST['db_port'] ?? 3306);
            $name = trim((string)($_POST['db_name'] ?? ''));
            $user = trim((string)($_POST['db_user'] ?? ''));
            $pass = (string)($_POST['db_pass'] ?? '');

            if ($port < 1) $port = 3306;

            if ($host === '' || $name === '' || $user === '') {
                $err = 'Database host, name and user are required.';
            } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                $err = 'Database name may only contain letters, numbers and underscores.';
            } else {
                try {
                    $pdo = new PDO(
                        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
                        $user, $pass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $quotedName = '`'.str_replace('`', '``', $name).'`';
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS $quotedName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE $quotedName");

                    $setupKey = bin2hex(random_bytes(32));
                    $configArray = [
                        'app_name' => $appName,
                        'base_url' => $baseUrl,
                        'timezone' => $timezone,
                        'db' => [
                            'host' => $host,
                            'port' => $port,
                            'name' => $name,
                            'user' => $user,
                            'pass' => $pass,
                            'charset' => 'utf8mb4',
                        ],
                        'emergency' => [
                            'police_control' => '100',
                            'police_toll_free' => '16600141516',
                        ],
                        'security' => [
                            'session_name' => 'DBMAPSESSID',
                            'max_upload_mb' => 8,
                            'max_photos' => 5,
                            'public_coordinate_decimals' => 3,
                            'setup_key' => $setupKey,
                        ],
                    ];
                    $php = "<?php\ndeclare(strict_types=1);\n\nreturn ".var_export($configArray, true).";\n";
                    if (file_put_contents($configPath, $php, LOCK_EX) === false) {
                        $err = 'Could not write config.php — check that the web server user can write to this directory.';
                    } else {
                        @chmod($configPath, 0640);
                        header('Location: setup.php?key='.urlencode($setupKey));
                        exit;
                    }
                } catch (Throwable $e) {
                    $err = 'Could not connect: '.$e->getMessage();
                }
            }
        }
    }

    $body = '<div class="card">'
        .'<h1>Livestock Carcass Management System — Setup</h1>'
        .'<p class="small">Step 1 of 3: database connection.</p>'
        .render_requirements($requirements)
        .'<div class="warning">This page is not yet protected by a setup key (none exists until config.php is written). Run it immediately after uploading the files, before anyone else can reach this URL, and delete <code>setup.php</code> as soon as setup finishes.</div>'
        .($err ? '<div class="danger" style="margin-top:16px">'.e($err).'</div>' : '')
        .'<form method="post" style="margin-top:16px">'
        .'<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'
        .'<div class="form-group"><label>Application name</label><input class="input" name="app_name" value="'.e((string)($_POST['app_name'] ?? 'Livestock Carcass Management System')).'"></div>'
        .'<div class="form-group"><label>Base URL (optional, e.g. https://example.com/db)</label><input class="input" name="base_url" value="'.e((string)($_POST['base_url'] ?? '')).'"></div>'
        .'<div class="form-group"><label>Timezone</label><input class="input" name="timezone" value="'.e((string)($_POST['timezone'] ?? 'Asia/Kathmandu')).'"></div>'
        .'<div class="grid grid-2">'
        .'<div class="form-group"><label>DB host</label><input class="input" name="db_host" value="'.e((string)($_POST['db_host'] ?? 'localhost')).'" required></div>'
        .'<div class="form-group"><label>DB port</label><input class="input" name="db_port" value="'.e((string)($_POST['db_port'] ?? '3306')).'" required></div>'
        .'<div class="form-group"><label>DB name</label><input class="input" name="db_name" value="'.e((string)($_POST['db_name'] ?? '')).'" required></div>'
        .'<div class="form-group"><label>DB user</label><input class="input" name="db_user" value="'.e((string)($_POST['db_user'] ?? '')).'" required></div>'
        .'</div>'
        .'<div class="form-group"><label>DB password</label><input class="input" type="password" name="db_pass"></div>'
        .'<p class="small">If the database doesn\'t exist yet, this user needs privileges to create it. Otherwise, pre-create the database and grant this user full privileges on it only.</p>'
        .'<button class="btn btn-dark">Save &amp; Continue</button>'
        .'</form></div>';
    page('Database Setup', $body);
}

// ---------------------------------------------------------------------
// From here, config.php exists. Load it and connect.
// ---------------------------------------------------------------------
require __DIR__.'/includes/db.php';
$config = require $configPath;
date_default_timezone_set($config['timezone'] ?? 'Asia/Kathmandu');

try {
    $db = db_connect($config);
} catch (Throwable $e) {
    page('Database Error', '<div class="card"><h1>Can\'t connect to the database</h1><div class="danger">'.e($e->getMessage()).'</div><p>Fix the <code>db</code> settings in <code>config.php</code> and reload this page.</p></div>');
}

try {
    $adminCount = (int)$db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
} catch (Throwable $e) {
    $adminCount = 0;
}

$key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
$expectedKey = (string)($config['security']['setup_key'] ?? '');
$keyIsPlaceholder = $expectedKey === '' || $expectedKey === 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET';

// The setup key alone gates everything past this point. Schema install/migration
// stays available on every valid-key visit (so this file can double as the
// per-release migration tool for upgrades); only admin creation below locks
// itself once an admin exists.
if ($keyIsPlaceholder || !hash_equals($expectedKey, $key)) {
    http_response_code(403);
    exit('Setup unavailable or invalid setup key.');
}

// ---------------------------------------------------------------------
// STEP 2: create the schema on a fresh database, then run any pending
// incremental migrations. Both halves are safe to re-run.
// ---------------------------------------------------------------------
$tableCheck = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users'");
$tableCheck->execute();
$schemaInstalled = (bool)$tableCheck->fetchColumn();

function run_sql_file(PDO $db, string $path): void {
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException("Could not read $path");
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $db->exec($stmt);
    }
}

function column_exists(PDO $db, string $table, string $column): bool {
    $s = $db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $s->execute([$table, $column]);
    return (bool)$s->fetchColumn();
}

function index_exists(PDO $db, string $table, string $index): bool {
    $s = $db->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $s->execute([$table, $index]);
    return (bool)$s->fetchColumn();
}

function constraint_exists(PDO $db, string $table, string $constraint): bool {
    $s = $db->prepare('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
    $s->execute([$table, $constraint]);
    return (bool)$s->fetchColumn();
}

// Brings any pre-v1.2 or pre-v1.6 database up to the current schema.
// No-op on a database created fresh from database.sql (every column already exists).
function run_pending_migrations(PDO $db): array {
    $applied = [];

    // v1.2: case-tracking workflow fields + case_share_links
    $db->exec("ALTER TABLE body_reports MODIFY COLUMN status ENUM('new','verification_required','verified','confirmed','police_informed','team_dispatched','recovered','shifted','buried','identified','closed','false_report','invalid','duplicate','unable_to_locate') NOT NULL DEFAULT 'new', MODIFY COLUMN destination_type ENUM('hospital','mortuary','shelter','burial_site','other','none') NOT NULL DEFAULT 'none'");
    $v12Columns = [
        "location_source ENUM('gps','map','manual','unknown') NOT NULL DEFAULT 'unknown' AFTER altitude",
        "confirmed_at DATETIME NULL AFTER verification_notes",
        "confirmed_by INT UNSIGNED NULL AFTER confirmed_at",
        "police_informed TINYINT(1) NOT NULL DEFAULT 0 AFTER confirmed_by",
        "police_informed_at DATETIME NULL AFTER police_informed",
        "police_informed_source ENUM('reporter','admin','system','unknown') NOT NULL DEFAULT 'unknown' AFTER police_informed_at",
        "police_contact_name VARCHAR(160) NULL AFTER police_unit",
        "police_contact_phone VARCHAR(30) NULL AFTER police_contact_name",
        "police_reference VARCHAR(120) NULL AFTER police_contact_phone",
        "location_shared_with_police_at DATETIME NULL AFTER police_reference",
        "rescue_team_name VARCHAR(180) NULL AFTER location_shared_with_police_at",
        "rescue_team_contact VARCHAR(60) NULL AFTER rescue_team_name",
        "team_dispatched_at DATETIME NULL AFTER rescue_team_contact",
        "location_shared_with_team_at DATETIME NULL AFTER team_dispatched_at",
        "buried_at DATETIME NULL AFTER muchulka_reference",
        "burial_location VARCHAR(255) NULL AFTER buried_at",
        "burial_latitude DECIMAL(10,7) NULL AFTER burial_location",
        "burial_longitude DECIMAL(10,7) NULL AFTER burial_latitude",
        "burial_by VARCHAR(180) NULL AFTER burial_longitude",
        "burial_notes TEXT NULL AFTER burial_by",
        "closure_reason VARCHAR(255) NULL AFTER identification_notes",
        "false_report_details TEXT NULL AFTER closure_reason",
        "last_edited_by INT UNSIGNED NULL AFTER closed_by",
    ];
    foreach ($v12Columns as $c) {
        $col = strtok($c, ' ');
        if (column_exists($db, 'body_reports', $col)) continue;
        $db->exec('ALTER TABLE body_reports ADD COLUMN '.$c);
        $applied[] = "body_reports.$col";
    }
    $db->exec("CREATE TABLE IF NOT EXISTS case_share_links (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,report_id BIGINT UNSIGNED NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,recipient_type ENUM('police','rescue_team','other') NOT NULL,recipient_name VARCHAR(180) NULL,recipient_contact VARCHAR(80) NULL,note VARCHAR(500) NULL,expires_at DATETIME NOT NULL,revoked_at DATETIME NULL,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,last_accessed_at DATETIME NULL,access_count INT UNSIGNED NOT NULL DEFAULT 0,INDEX idx_share_report (report_id),INDEX idx_share_expiry (expires_at),CONSTRAINT fk_share_report FOREIGN KEY (report_id) REFERENCES body_reports(id) ON DELETE CASCADE,CONSTRAINT fk_share_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // v1.6: submitted_by tracking + animal carcass assessment fields
    if (!column_exists($db, 'body_reports', 'submitted_by')) {
        $db->exec('ALTER TABLE body_reports ADD COLUMN submitted_by INT UNSIGNED NULL AFTER reporter_private');
        $applied[] = 'body_reports.submitted_by';
    }
    if (!index_exists($db, 'body_reports', 'idx_submitted_by')) {
        $db->exec('ALTER TABLE body_reports ADD INDEX idx_submitted_by (submitted_by)');
    }
    if (!constraint_exists($db, 'body_reports', 'fk_submitted_by')) {
        $db->exec('ALTER TABLE body_reports ADD CONSTRAINT fk_submitted_by FOREIGN KEY (submitted_by) REFERENCES admin_users(id) ON DELETE SET NULL');
    }
    $v16Columns = [
        "observed_at DATETIME NULL AFTER nearby_objects",
        "weather_condition ENUM('clear','raining','extreme_heat') NULL AFTER observed_at",
        "animal_species ENUM('cow_ox','buffalo','goat_sheep','pig_boar','chicken_duck','other') NULL AFTER weather_condition",
        "animal_species_other VARCHAR(120) NULL AFTER animal_species",
        "estimated_size ENUM('large','medium','small') NULL AFTER animal_species_other",
        "carcass_count SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER estimated_size",
        "decomposition_state ENUM('fresh','decomposing','fully_decomposed','mutilated') NULL AFTER carcass_count",
        "distance_water_source ENUM('under_50m','50_to_200m','over_200m') NULL AFTER decomposition_state",
        "distance_settlement ENUM('under_50m','100_to_500m','over_500m') NULL AFTER distance_water_source",
        "disposal_method ENUM('trench_burial','offsite_transport','other_approved') NULL AFTER distance_settlement",
        "disposal_method_notes VARCHAR(255) NULL AFTER disposal_method",
        "equipment_needed VARCHAR(255) NULL AFTER disposal_method_notes",
        "equipment_needed_other VARCHAR(120) NULL AFTER equipment_needed",
        "disinfection_materials VARCHAR(255) NULL AFTER equipment_needed_other",
    ];
    foreach ($v16Columns as $c) {
        $col = strtok($c, ' ');
        if (column_exists($db, 'body_reports', $col)) continue;
        $db->exec('ALTER TABLE body_reports ADD COLUMN '.$c);
        $applied[] = "body_reports.$col";
    }

    return $applied;
}

$migrationsApplied = [];
try {
    if (!$schemaInstalled) {
        run_sql_file($db, __DIR__.'/database.sql');
        $schemaInstalled = true;
    }
    $migrationsApplied = run_pending_migrations($db);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Schema installation/migration failed:\n".$e->getMessage()."\n\nFix the issue (e.g. database user privileges) and reload this page — it is safe to re-run.");
}

// ---------------------------------------------------------------------
// STEP 3: create the first Admin account. Skipped once one already exists —
// at that point this file is just the migration runner from here on.
// ---------------------------------------------------------------------
if ($adminCount > 0) {
    $base = app_base($config);
    $migrationNote = $migrationsApplied
        ? '<div class="info">Applied '.count($migrationsApplied).' pending schema update(s): '.e(implode(', ', $migrationsApplied)).'</div>'
        : '<div class="success">Schema already up to date — nothing to migrate.</div>';
    page('Already Set Up', '<div class="card">'
        .'<h1>Schema checked</h1>'
        .$migrationNote
        .'<p>An admin account already exists, so the setup wizard itself is done. Re-visiting this URL with the correct key still safely re-checks and applies any pending schema migrations — keep that in mind before deleting it if you plan to use it for future upgrades instead of per-release migration scripts.</p>'
        .'<p><a class="btn btn-dark" href="'.e($base).'/admin/login">Go to Admin Login</a></p>'
        .'</div>');
}

$msg = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    csrf_check();
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $office = trim((string)($_POST['office_name'] ?? ''));
    $post = trim((string)($_POST['post_title'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if ($name === '') {
        $msg = 'Name is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{4,80}$/', $username)) {
        $msg = 'Username must be 4-80 characters (letters, numbers, dot, underscore, hyphen).';
    } elseif (strlen($password) < 10) {
        $msg = 'Password must be at least 10 characters.';
    } elseif ($password !== $confirm) {
        $msg = 'Password and confirmation do not match.';
    } else {
        $exists = $db->prepare('SELECT 1 FROM admin_users WHERE username=? LIMIT 1');
        $exists->execute([$username]);
        if ($exists->fetchColumn()) {
            $msg = 'That username is already taken.';
        } else {
            $s = $db->prepare("INSERT INTO admin_users(name,phone,office_name,post_title,username,password_hash,role,can_close) VALUES(?,?,?,?,?,?,'admin',1)");
            $s->execute([$name, $phone, $office, $post, $username, password_hash($password, PASSWORD_DEFAULT)]);
            $done = true;
        }
    }
}

$base = app_base($config);

if ($done) {
    page('Setup Complete', '<div class="card">'
        .'<h1>Setup complete</h1>'
        .'<div class="success">Database schema installed and super admin account created.</div>'
        .'<p><strong>Delete <code>setup.php</code> (and the <code>/setup/</code> folder) from the server now.</strong> They have no further purpose and leaving them live is a needless attack surface.</p>'
        .'<p><a class="btn btn-dark" href="'.e($base).'/admin/login">Go to Admin Login</a></p>'
        .'</div>');
}

$migrationNote = $migrationsApplied
    ? '<div class="info">Applied '.count($migrationsApplied).' pending schema update(s): '.e(implode(', ', $migrationsApplied)).'</div>'
    : '';

page('Create Admin', '<form class="card" method="post">'
    .'<h1>Step 3 of 3: create the super admin</h1>'
    .'<p class="small">Schema is installed and up to date.</p>'
    .$migrationNote
    .($msg ? '<div class="danger">'.e($msg).'</div>' : '')
    .'<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'
    .'<input type="hidden" name="key" value="'.e($key).'">'
    .'<div class="form-group"><label>Name</label><input class="input" name="name" value="'.e((string)($_POST['name'] ?? '')).'" required></div>'
    .'<div class="grid grid-2">'
    .'<div class="form-group"><label>Post</label><input class="input" name="post_title" value="'.e((string)($_POST['post_title'] ?? '')).'"></div>'
    .'<div class="form-group"><label>Office</label><input class="input" name="office_name" value="'.e((string)($_POST['office_name'] ?? '')).'"></div>'
    .'<div class="form-group"><label>Phone</label><input class="input" name="phone" value="'.e((string)($_POST['phone'] ?? '')).'"></div>'
    .'<div class="form-group"><label>Username</label><input class="input" name="username" value="'.e((string)($_POST['username'] ?? '')).'" required></div>'
    .'</div>'
    .'<div class="form-group"><label>Password (10+ characters)</label><input class="input" type="password" name="password" required></div>'
    .'<div class="form-group"><label>Confirm password</label><input class="input" type="password" name="password_confirm" required></div>'
    .'<button class="btn btn-dark">Create Super Admin</button>'
    .'</form>');
