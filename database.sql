CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NULL,
    office_name VARCHAR(160) NULL,
    post_title VARCHAR(120) NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','operator','viewer') NOT NULL DEFAULT 'operator',
    can_close TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS body_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    body_type ENUM('human','animal','unsure') NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    gps_accuracy DECIMAL(8,2) NULL,
    altitude DECIMAL(8,2) NULL,
    location_source ENUM('gps','map','manual','unknown') NOT NULL DEFAULT 'unknown',
    location_text VARCHAR(255) NULL,
    landmark VARCHAR(255) NULL,
    description TEXT NULL,

    reporter_name VARCHAR(120) NOT NULL,
    reporter_phone VARCHAR(30) NOT NULL,
    alternate_phone VARCHAR(30) NULL,
    reporter_organization VARCHAR(160) NULL,
    reporter_private TINYINT(1) NOT NULL DEFAULT 1,
    submitted_by INT UNSIGNED NULL,

    status ENUM(
      'new','verification_required','verified','confirmed','police_informed','team_dispatched',
      'recovered','shifted','buried','identified','closed','false_report','invalid','duplicate','unable_to_locate'
    ) NOT NULL DEFAULT 'new',

    verification_notes TEXT NULL,
    confirmed_at DATETIME NULL,
    confirmed_by INT UNSIGNED NULL,

    police_informed TINYINT(1) NOT NULL DEFAULT 0,
    police_informed_at DATETIME NULL,
    police_informed_source ENUM('reporter','admin','system','unknown') NOT NULL DEFAULT 'unknown',
    police_unit VARCHAR(160) NULL,
    police_contact_name VARCHAR(160) NULL,
    police_contact_phone VARCHAR(30) NULL,
    police_reference VARCHAR(120) NULL,
    location_shared_with_police_at DATETIME NULL,

    rescue_team_name VARCHAR(180) NULL,
    rescue_team_contact VARCHAR(60) NULL,
    team_dispatched_at DATETIME NULL,
    location_shared_with_team_at DATETIME NULL,

    approx_gender ENUM('male','female','unknown','not_applicable') NOT NULL DEFAULT 'unknown',
    approx_age_min SMALLINT UNSIGNED NULL,
    approx_age_max SMALLINT UNSIGNED NULL,
    body_condition VARCHAR(160) NULL,
    clothes TEXT NULL,
    identifying_marks TEXT NULL,
    documents_found TEXT NULL,
    nearby_objects TEXT NULL,

    -- Animal carcass assessment (populated only when body_type='animal')
    observed_at DATETIME NULL,
    weather_condition ENUM('clear','raining','extreme_heat') NULL,
    animal_species ENUM('cow_ox','buffalo','goat_sheep','pig_boar','chicken_duck','other') NULL,
    animal_species_other VARCHAR(120) NULL,
    estimated_size ENUM('large','medium','small') NULL,
    carcass_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    decomposition_state ENUM('fresh','decomposing','fully_decomposed','mutilated') NULL,
    distance_water_source ENUM('under_50m','50_to_200m','over_200m') NULL,
    distance_settlement ENUM('under_50m','100_to_500m','over_500m') NULL,
    disposal_method ENUM('trench_burial','offsite_transport','other_approved') NULL,
    disposal_method_notes VARCHAR(255) NULL,
    equipment_needed VARCHAR(255) NULL,
    equipment_needed_other VARCHAR(120) NULL,
    disinfection_materials VARCHAR(255) NULL,

    recovered_by VARCHAR(160) NULL,
    recovered_at DATETIME NULL,
    destination_type ENUM('hospital','mortuary','shelter','burial_site','other','none') NOT NULL DEFAULT 'none',
    destination_name VARCHAR(200) NULL,
    mortuary_bag_no VARCHAR(80) NULL,
    muchulka_reference VARCHAR(120) NULL,

    buried_at DATETIME NULL,
    burial_location VARCHAR(255) NULL,
    burial_latitude DECIMAL(10,7) NULL,
    burial_longitude DECIMAL(10,7) NULL,
    burial_by VARCHAR(180) NULL,
    burial_notes TEXT NULL,

    identified_name VARCHAR(180) NULL,
    identification_notes TEXT NULL,
    closure_reason VARCHAR(255) NULL,
    false_report_details TEXT NULL,
    closed_at DATETIME NULL,
    closed_by INT UNSIGNED NULL,
    last_edited_by INT UNSIGNED NULL,

    client_ip_hash CHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_type_status (body_type, status),
    INDEX idx_created (created_at),
    INDEX idx_lat_lng (latitude, longitude),
    INDEX idx_reporter_phone (reporter_phone),
    INDEX idx_submitted_by (submitted_by),
    INDEX idx_police_informed (police_informed),
    INDEX idx_dispatched (team_dispatched_at),
    CONSTRAINT fk_closed_by FOREIGN KEY (closed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_submitted_by FOREIGN KEY (submitted_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(80) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_report (report_id),
    CONSTRAINT fk_photo_report FOREIGN KEY (report_id) REFERENCES body_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    old_status VARCHAR(40) NULL,
    new_status VARCHAR(40) NOT NULL,
    note TEXT NULL,
    changed_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_report (report_id),
    CONSTRAINT fk_status_report FOREIGN KEY (report_id) REFERENCES body_reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_admin FOREIGN KEY (changed_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id VARCHAR(64) NULL,
    details TEXT NULL,
    ip_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_admin (admin_id),
    INDEX idx_audit_created (created_at),
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
