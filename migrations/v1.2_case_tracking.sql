-- Dead Body Mapping System v1.2 migration for an existing v1.0/v1.1 database.
-- Run this ONCE against a v1.0/v1.1 database. It is NOT safe to re-run:
-- real MySQL (unlike MariaDB) has no "ADD COLUMN IF NOT EXISTS", so a second
-- run will fail with a duplicate-column error. Prefer migrate_v1_2.php, which
-- checks column existence first and is safe to re-run.

ALTER TABLE body_reports
  MODIFY COLUMN status ENUM(
    'new','verification_required','verified','confirmed','police_informed','team_dispatched',
    'recovered','shifted','buried','identified','closed','false_report','invalid','duplicate','unable_to_locate'
  ) NOT NULL DEFAULT 'new',
  MODIFY COLUMN destination_type ENUM('hospital','mortuary','shelter','burial_site','other','none') NOT NULL DEFAULT 'none';

ALTER TABLE body_reports
  ADD COLUMN location_source ENUM('gps','map','manual','unknown') NOT NULL DEFAULT 'unknown' AFTER altitude,
  ADD COLUMN confirmed_at DATETIME NULL AFTER verification_notes,
  ADD COLUMN confirmed_by INT UNSIGNED NULL AFTER confirmed_at,
  ADD COLUMN police_informed TINYINT(1) NOT NULL DEFAULT 0 AFTER confirmed_by,
  ADD COLUMN police_informed_at DATETIME NULL AFTER police_informed,
  ADD COLUMN police_informed_source ENUM('reporter','admin','system','unknown') NOT NULL DEFAULT 'unknown' AFTER police_informed_at,
  ADD COLUMN police_contact_name VARCHAR(160) NULL AFTER police_unit,
  ADD COLUMN police_contact_phone VARCHAR(30) NULL AFTER police_contact_name,
  ADD COLUMN police_reference VARCHAR(120) NULL AFTER police_contact_phone,
  ADD COLUMN location_shared_with_police_at DATETIME NULL AFTER police_reference,
  ADD COLUMN rescue_team_name VARCHAR(180) NULL AFTER location_shared_with_police_at,
  ADD COLUMN rescue_team_contact VARCHAR(60) NULL AFTER rescue_team_name,
  ADD COLUMN team_dispatched_at DATETIME NULL AFTER rescue_team_contact,
  ADD COLUMN location_shared_with_team_at DATETIME NULL AFTER team_dispatched_at,
  ADD COLUMN buried_at DATETIME NULL AFTER muchulka_reference,
  ADD COLUMN burial_location VARCHAR(255) NULL AFTER buried_at,
  ADD COLUMN burial_latitude DECIMAL(10,7) NULL AFTER burial_location,
  ADD COLUMN burial_longitude DECIMAL(10,7) NULL AFTER burial_latitude,
  ADD COLUMN burial_by VARCHAR(180) NULL AFTER burial_longitude,
  ADD COLUMN burial_notes TEXT NULL AFTER burial_by,
  ADD COLUMN closure_reason VARCHAR(255) NULL AFTER identification_notes,
  ADD COLUMN false_report_details TEXT NULL AFTER closure_reason,
  ADD COLUMN last_edited_by INT UNSIGNED NULL AFTER closed_by;

CREATE TABLE IF NOT EXISTS case_share_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    recipient_type ENUM('police','rescue_team','other') NOT NULL,
    recipient_name VARCHAR(180) NULL,
    recipient_contact VARCHAR(80) NULL,
    note VARCHAR(500) NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_accessed_at DATETIME NULL,
    access_count INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_share_report (report_id),
    INDEX idx_share_expiry (expires_at),
    CONSTRAINT fk_share_report FOREIGN KEY (report_id) REFERENCES body_reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_share_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
