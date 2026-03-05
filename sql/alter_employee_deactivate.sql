-- PeachTrack: add Is_Active flag (soft deactivate instead of deleting employees)
-- Run once in your peachtrack database.

ALTER TABLE employee
  ADD COLUMN Is_Active TINYINT(1) NOT NULL DEFAULT 1;

UPDATE employee SET Is_Active = 1 WHERE Is_Active IS NULL;
