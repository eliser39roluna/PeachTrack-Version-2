-- PeachTrack: add Tip_Time to tip entries (for Recent Tips time column)
-- Run once in your peachtrack database.

ALTER TABLE tip
  ADD COLUMN Tip_Time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Optional backfill (set to shift start time for older rows)
UPDATE tip t
JOIN shift s ON s.Shift_ID = t.Shift_ID
SET t.Tip_Time = COALESCE(t.Tip_Time, s.Start_Time);
