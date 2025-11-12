
-- Barangay Profiling (flowchart: Residents + Families)
CREATE DATABASE IF NOT EXISTS barangay_profiliing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE barangay_profiliing;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS person;
DROP TABLE IF EXISTS family;
DROP TABLE IF EXISTS barangay;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE barangay (
  barangay_id INT AUTO_INCREMENT PRIMARY KEY,
  barangay_name VARCHAR(100) NOT NULL
);

CREATE TABLE family (
  family_id INT AUTO_INCREMENT PRIMARY KEY,
  family_name VARCHAR(50) NOT NULL,
  household_number VARCHAR(50),
  address VARCHAR(150),
  barangay_id INT NULL,
  family_head_id INT NULL,
  CONSTRAINT fk_family_barangay FOREIGN KEY (barangay_id) REFERENCES barangay(barangay_id)
    ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE person (
  person_id INT AUTO_INCREMENT PRIMARY KEY,
  family_id INT NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  middle_name VARCHAR(50),
  last_name VARCHAR(50) NOT NULL,
  gender ENUM('Male','Female','Other') NOT NULL,
  date_of_birth DATE NOT NULL,
  relationship_to_head INT NOT NULL, -- 1=head, 2=spouse, 3=child, etc
  occupation VARCHAR(50),
  educational_attainment VARCHAR(50),
  contact_number VARCHAR(50),
  civil_status VARCHAR(50),
  religion VARCHAR(50),
  CONSTRAINT fk_person_family FOREIGN KEY (family_id) REFERENCES family(family_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Seed one Barangay for convenience
INSERT INTO barangay (barangay_name) VALUES ('Sample Barangay');
