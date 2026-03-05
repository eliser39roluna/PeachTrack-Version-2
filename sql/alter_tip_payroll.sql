-- PeachTrack: Tip payout / payroll tracking
-- Run once in your peachtrack database.

CREATE TABLE IF NOT EXISTS tip_pay_period (
  Pay_Period_ID INT NOT NULL AUTO_INCREMENT,
  Period_Start DATE NOT NULL,
  Period_End DATE NOT NULL,
  Paid_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Paid_By INT NULL,
  Notes VARCHAR(255) NULL,
  PRIMARY KEY (Pay_Period_ID),
  KEY idx_tip_pay_period_range (Period_Start, Period_End)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Link tips to a pay period when paid
ALTER TABLE tip
  ADD COLUMN Pay_Period_ID INT NULL,
  ADD COLUMN Paid_At DATETIME NULL,
  ADD COLUMN Paid_By INT NULL;

CREATE INDEX idx_tip_pay_period_id ON tip (Pay_Period_ID);

-- Optional FK (safe if employee table exists)
-- ALTER TABLE tip
--   ADD CONSTRAINT fk_tip_pay_period FOREIGN KEY (Pay_Period_ID) REFERENCES tip_pay_period(Pay_Period_ID);
