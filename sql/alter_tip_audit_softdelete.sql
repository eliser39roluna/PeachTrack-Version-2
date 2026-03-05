-- PeachTrack: soft-delete + audit fields for tips (preserve history)
-- Run once in your peachtrack database.

ALTER TABLE tip
  ADD COLUMN Is_Deleted TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN Deleted_At DATETIME NULL,
  ADD COLUMN Deleted_By INT NULL;

-- Optional: index for faster filtering
CREATE INDEX idx_tip_is_deleted ON tip (Is_Deleted);

-- Optional: backfill defaults (usually already 0)
UPDATE tip SET Is_Deleted = 0 WHERE Is_Deleted IS NULL;
