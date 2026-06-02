-- Adds company_id and profile_photo_path to intake submissions staging table

USE hr_system;

ALTER TABLE employee_intake_submissions
    ADD COLUMN company_id         BIGINT UNSIGNED NULL AFTER second_nationality,
    ADD COLUMN profile_photo_path VARCHAR(255)    NULL AFTER passport_number;
