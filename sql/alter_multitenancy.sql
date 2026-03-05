-- PeachTrack Multi-Tenancy Migration
-- Adds company table + Company_ID to employee table.
-- Run ONCE after schema.sql + all other alter_*.sql scripts.

-- 1) Create the company table
CREATE TABLE IF NOT EXISTS company (
    Company_ID    INT AUTO_INCREMENT PRIMARY KEY,
    Company_Name  VARCHAR(100) NOT NULL,
    Admin_Email   VARCHAR(100) NOT NULL UNIQUE,
    Admin_Password VARCHAR(255) NOT NULL,
    Created_At    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2) Seed existing data as the first company (Fuzzy Peach Salon)
--    Password = 'password' (bcrypt)
INSERT INTO company (Company_ID, Company_Name, Admin_Email, Admin_Password)
VALUES (1, 'Fuzzy Peach Salon', 'admin@fuzzypeach.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE Company_Name = Company_Name;

-- 3) Add Company_ID column to employee (default 1 = Fuzzy Peach Salon)
ALTER TABLE employee
    ADD COLUMN Company_ID INT NOT NULL DEFAULT 1 AFTER Employee_ID;

-- 4) Tag all existing employees as belonging to company 1
UPDATE employee SET Company_ID = 1 WHERE Company_ID IS NULL OR Company_ID = 0;

-- 5) Add foreign key constraint
ALTER TABLE employee
    ADD CONSTRAINT fk_employee_company
    FOREIGN KEY (Company_ID) REFERENCES company(Company_ID)
    ON DELETE CASCADE;
