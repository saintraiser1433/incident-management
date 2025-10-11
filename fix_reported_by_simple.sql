-- Simple fix: Allow NULL values in reported_by column for guest users
-- This changes the column from "int NOT NULL" to "int NULL"
ALTER TABLE incident_reports MODIFY COLUMN reported_by INT NULL;
