-- PeachTrack: add Sale_Amount to tip entries (per-service sale logged alongside each tip)
-- Run once in your peachtrack database.

ALTER TABLE tip
  ADD COLUMN Sale_Amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;

CREATE INDEX idx_tip_sale_amount ON tip (Sale_Amount);
