-- =====================================================
-- SCERRS - Smart Citizen Emergency Reporting & Response System
-- Database schema + seed data (MySQL 8.x / utf8mb4)
-- =====================================================

DROP DATABASE IF EXISTS scerrs;
CREATE DATABASE scerrs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scerrs;

-- -----------------------------------------------------
-- Departments (authority agencies)
-- -----------------------------------------------------
CREATE TABLE departments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL UNIQUE,
  code        VARCHAR(20)  NOT NULL UNIQUE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO departments (name, code) VALUES
('Nepal Police', 'police'),
('Fire Brigade', 'fire'),
('Ambulance Services', 'ambulance'),
('Municipality', 'municipality'),
('Disaster Management Agency', 'disaster');

-- -----------------------------------------------------
-- Users: citizen | authority | admin
-- -----------------------------------------------------
CREATE TABLE users (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(120) NOT NULL,
  first_name     VARCHAR(80)  DEFAULT NULL,
  middle_name    VARCHAR(80)  DEFAULT NULL,
  last_name      VARCHAR(80)  DEFAULT NULL,
  email          VARCHAR(150) NOT NULL UNIQUE,
  phone          VARCHAR(20)  DEFAULT NULL,
  country        VARCHAR(60)  DEFAULT 'Nepal',
  identity_type  ENUM('citizenship','passport') DEFAULT 'citizenship',
  identity_no    VARCHAR(50)  DEFAULT NULL,
  province       VARCHAR(60)  DEFAULT NULL,
  district       VARCHAR(60)  DEFAULT NULL,
  municipality   VARCHAR(120) DEFAULT NULL,
  tole           VARCHAR(120) DEFAULT NULL,
  ward_no        SMALLINT UNSIGNED DEFAULT NULL,
  officer_id     VARCHAR(50)  DEFAULT NULL,
  designation    VARCHAR(100) DEFAULT NULL,
  official_email VARCHAR(150) DEFAULT NULL,
  dept_address   VARCHAR(255) DEFAULT NULL,
  password       VARCHAR(255) NOT NULL,
  role           ENUM('citizen','authority','admin') NOT NULL DEFAULT 'citizen',
  department_id  INT UNSIGNED DEFAULT NULL,
  active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Incident categories -> handled by a department
-- -----------------------------------------------------
CREATE TABLE categories (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL UNIQUE,
  department_id INT UNSIGNED NOT NULL,
  icon          VARCHAR(10)  DEFAULT '⚠️',
  active        TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_categories_department FOREIGN KEY (department_id) REFERENCES departments(id),
  INDEX idx_categories_active (active)
) ENGINE=InnoDB;

INSERT INTO categories (name, department_id, icon) VALUES
('Road Accident',        1, '🚗'),
('Crime / Violence',     1, '🚨'),
('Fire Outbreak',        2, '🔥'),
('Flood',                5, '🌊'),
('Landslide',            5, '⛰️'),
('Earthquake Damage',    5, '🏚️'),
('Medical Emergency',    3, '🚑'),
('Fallen Tree / Debris', 4, '🌳'),
('Road Hazard',          4, '🕳️'),
('Electricity Hazard',   4, '⚡');

-- -----------------------------------------------------
-- Incidents
-- -----------------------------------------------------
CREATE TABLE incidents (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref_no       VARCHAR(20) NOT NULL UNIQUE,
  user_id      INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  title        VARCHAR(150) NOT NULL,
  description  TEXT NOT NULL,
  latitude     DECIMAL(10,7) NOT NULL,
  longitude    DECIMAL(10,7) NOT NULL,
  address      VARCHAR(255) DEFAULT NULL,
  severity     ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status       ENUM('pending','acknowledged','in_progress','resolved','rejected') NOT NULL DEFAULT 'pending',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_incidents_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_incidents_category FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_incidents_status   (status),
  INDEX idx_incidents_category (category_id),
  INDEX idx_incidents_created  (created_at)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Photo evidence attached to incidents
-- -----------------------------------------------------
CREATE TABLE incident_media (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  incident_id INT UNSIGNED NOT NULL,
  file_path   VARCHAR(255) NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_media_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Response teams per department
-- -----------------------------------------------------
CREATE TABLE response_teams (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  contact       VARCHAR(50) DEFAULT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_teams_department FOREIGN KEY (department_id) REFERENCES departments(id),
  INDEX idx_teams_dept (department_id)
) ENGINE=InnoDB;

INSERT INTO response_teams (name, department_id, contact) VALUES
('Police Patrol Unit A',   1, '100'),
('Police Patrol Unit B',   1, '100'),
('Fire Engine Kathmandu',  2, '101'),
('Rescue Crew Lalitpur',   2, '101'),
('Ambulance Bhaktapur',    3, '102'),
('Ambulance Kalanki',      3, '102'),
('Ward Cleanup Squad 16',  4, '01-4268500'),
('Rapid Road Repair Team', 4, '01-4268500'),
('NEOC Response Cell',     5, '1180'),
('District Relief Team',   5, '1180');

-- -----------------------------------------------------
-- Team assignments for incidents
-- -----------------------------------------------------
CREATE TABLE assignments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  incident_id INT UNSIGNED NOT NULL,
  team_id     INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED NOT NULL,
  note        VARCHAR(255) DEFAULT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_assignments_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_assignments_team     FOREIGN KEY (team_id)     REFERENCES response_teams(id),
  CONSTRAINT fk_assignments_by       FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Status history (audit trail)
-- -----------------------------------------------------
CREATE TABLE status_histories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  incident_id INT UNSIGNED NOT NULL,
  status      ENUM('pending','acknowledged','in_progress','resolved','rejected') NOT NULL,
  changed_by  INT UNSIGNED NOT NULL,
  note        VARCHAR(255) DEFAULT NULL,
  changed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user     FOREIGN KEY (changed_by)  REFERENCES users(id),
  INDEX idx_history_incident (incident_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- In-app notifications
-- -----------------------------------------------------
CREATE TABLE notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  incident_id INT UNSIGNED DEFAULT NULL,
  message    VARCHAR(255) NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user     FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
  INDEX idx_notifications_user (user_id, is_read)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Citizen feedback after resolution
-- -----------------------------------------------------
CREATE TABLE feedback (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  incident_id INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  comment     VARCHAR(500) DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_feedback_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_feedback_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
  UNIQUE KEY uq_feedback_incident_user (incident_id, user_id)
) ENGINE=InnoDB;

-- =====================================================
-- SEED DATA
-- Passwords: Admin@123 / Authority@123 / Citizen@123
-- =====================================================
INSERT INTO users (name, email, phone, password, role, department_id) VALUES
('System Admin',      'admin@scerrs.np',      '9800000001', '$2y$10$7bzwm35HxJByY2FKpaliGOqG3nhsIvBfzqGjgjh6E4K/cwjXAJaUK', 'admin', NULL),
('Inspector Sharma',  'police@scerrs.np',     '9800000011', '$2y$10$AZgNTr7QOSZXegzAa9KgSer2c3tvfuihehdGxskNw73g/73XbXwZG', 'authority', 1),
('Fire Chief Rai',    'fire@scerrs.np',       '9800000022', '$2y$10$AZgNTr7QOSZXegzAa9KgSer2c3tvfuihehdGxskNw73g/73XbXwZG', 'authority', 2),
('Paramedic Karki',   'ambulance@scerrs.np',  '9800000033', '$2y$10$AZgNTr7QOSZXegzAa9KgSer2c3tvfuihehdGxskNw73g/73XbXwZG', 'authority', 3),
('Ward Officer Gurung','municipality@scerrs.np','9800000044', '$2y$10$AZgNTr7QOSZXegzAa9KgSer2c3tvfuihehdGxskNw73g/73XbXwZG', 'authority', 4),
('NEOC Officer Thapa','disaster@scerrs.np',   '9800000055', '$2y$10$AZgNTr7QOSZXegzAa9KgSer2c3tvfuihehdGxskNw73g/73XbXwZG', 'authority', 5),
('Ram Bahadur Tamang','citizen@scerrs.np',    '9812345678', '$2y$10$GiEhEMxjLTGgq7KMJLNqTuEdEAgZIqTgHkCWkxqbRTtjkKHxCvBQy', 'citizen', NULL);

-- Demo incidents around Kathmandu Valley
INSERT INTO incidents (ref_no, user_id, category_id, title, description, latitude, longitude, address, severity, status) VALUES
('INC-2026-0001', 7, 1, 'Motorbike collision at Chabahil chowk',
 'Two motorbikes collided near the Chabahil intersection. One rider is injured and traffic is blocked on one lane.',
 27.7172000, 85.3489000, 'Chabahil Chowk, Kathmandu', 'high', 'acknowledged'),
('INC-2026-0002', 7, 3, 'Electrical fire in ground-floor shop',
 'Short circuit caused fire in an electrical shop. Fire is spreading to adjacent shutters.',
 27.6798000, 85.3107000, 'Mangal Bazar, Lalitpur', 'critical', 'in_progress'),
('INC-2026-0003', 7, 8, 'Large fallen tree blocking road',
 'A big tree fell during last night''s storm and completely blocks the road near the school.',
 27.6710000, 85.4294000, 'Suryabinayak Road, Bhaktapur', 'medium', 'pending');

INSERT INTO status_histories (incident_id, status, changed_by, note) VALUES
(1, 'pending',      7, 'Incident reported by citizen.'),
(1, 'acknowledged', 2, 'Report verified by Nepal Police control room.'),
(2, 'pending',      7, 'Incident reported by citizen.'),
(2, 'acknowledged', 3, 'Fire brigade alerted.'),
(2, 'in_progress',  3, 'Two fire engines dispatched from Kathmandu station.'),
(3, 'pending',      7, 'Incident reported by citizen.');

INSERT INTO assignments (incident_id, team_id, assigned_by, note) VALUES
(2, 3, 3, 'Primary response unit.'),
(2, 5, 3, 'Backup water supply support.');

INSERT INTO notifications (user_id, incident_id, message) VALUES
(7, 1, 'Your report INC-2026-0001 has been acknowledged by Nepal Police.'),
(7, 2, 'Response team dispatched for INC-2026-0002 (Fire Engine Kathmandu).'),
(7, 3, 'Your report INC-2026-0003 was received and is pending review by Municipality.');
