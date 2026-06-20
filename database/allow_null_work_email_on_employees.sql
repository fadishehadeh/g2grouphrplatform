-- Allow employee imports to leave work_email blank.
-- Run this once on the target database before using the upgraded importer.

ALTER TABLE employees
    MODIFY work_email VARCHAR(150) NULL;
