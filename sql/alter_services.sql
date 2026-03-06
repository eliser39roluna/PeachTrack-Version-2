-- PeachTrack: Add services table and link tips to services
-- Run once in your peachtrack database.

-- 1. Create the service table (scoped per company)
CREATE TABLE IF NOT EXISTS service (
  Service_ID    INT          NOT NULL AUTO_INCREMENT,
  Company_ID    INT          NOT NULL,
  Service_Name  VARCHAR(100) NOT NULL,
  Price         DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  Is_Active     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (Service_ID),
  CONSTRAINT fk_service_company FOREIGN KEY (Company_ID) REFERENCES company (Company_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. Add Service_ID column to the tip table (nullable for backward compat)
ALTER TABLE tip
  ADD COLUMN Service_ID INT NULL,
  ADD CONSTRAINT fk_tip_service FOREIGN KEY (Service_ID) REFERENCES service (Service_ID);
