-- Dead Body Mapping System v1.6 migration for an existing v1.2-v1.5 database.
-- Adds Animal Carcass Assessment fields to body_reports, plus a
-- body_reports.submitted_by column recording which admin/operator account
-- filed the report (report filing is staff-only -- there is no separate
-- public reporter-account system).
-- Run this ONCE against an existing database. It is NOT safe to re-run:
-- real MySQL (unlike MariaDB) has no "ADD COLUMN IF NOT EXISTS", so a second
-- run will fail with a duplicate-column error. Prefer migrate_v1_6.php, which
-- checks column existence first and is safe to re-run. Not needed on a fresh
-- install -- database.sql already includes these columns.

ALTER TABLE body_reports
  ADD COLUMN submitted_by INT UNSIGNED NULL AFTER reporter_private;

ALTER TABLE body_reports
  ADD INDEX idx_submitted_by (submitted_by),
  ADD CONSTRAINT fk_submitted_by FOREIGN KEY (submitted_by) REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE body_reports
  ADD COLUMN observed_at DATETIME NULL AFTER nearby_objects,
  ADD COLUMN weather_condition ENUM('clear','raining','extreme_heat') NULL AFTER observed_at,
  ADD COLUMN animal_species ENUM('cow_ox','buffalo','goat_sheep','pig_boar','chicken_duck','other') NULL AFTER weather_condition,
  ADD COLUMN animal_species_other VARCHAR(120) NULL AFTER animal_species,
  ADD COLUMN estimated_size ENUM('large','medium','small') NULL AFTER animal_species_other,
  ADD COLUMN carcass_count SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER estimated_size,
  ADD COLUMN decomposition_state ENUM('fresh','decomposing','fully_decomposed','mutilated') NULL AFTER carcass_count,
  ADD COLUMN distance_water_source ENUM('under_50m','50_to_200m','over_200m') NULL AFTER decomposition_state,
  ADD COLUMN distance_settlement ENUM('under_50m','100_to_500m','over_500m') NULL AFTER distance_water_source,
  ADD COLUMN disposal_method ENUM('trench_burial','offsite_transport','other_approved') NULL AFTER distance_settlement,
  ADD COLUMN disposal_method_notes VARCHAR(255) NULL AFTER disposal_method,
  ADD COLUMN equipment_needed VARCHAR(255) NULL AFTER disposal_method_notes,
  ADD COLUMN equipment_needed_other VARCHAR(120) NULL AFTER equipment_needed,
  ADD COLUMN disinfection_materials VARCHAR(255) NULL AFTER equipment_needed_other;
