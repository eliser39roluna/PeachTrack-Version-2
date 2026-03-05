-- PeachTrack: audit fields for manager force-end
-- Run once in your peachtrack database.

ALTER TABLE shift
  ADD COLUMN Ended_By INT NULL,
  ADD COLUMN End_Reason VARCHAR(100) NULL;

CREATE INDEX idx_shift_end_reason ON shift (End_Reason);
