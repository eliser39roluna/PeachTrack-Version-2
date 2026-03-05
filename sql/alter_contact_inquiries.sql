-- Creates the contact_inquiry table to store demo/business inquiries submitted via contact.php
CREATE TABLE IF NOT EXISTS contact_inquiry (
    Inquiry_ID   INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    Company      VARCHAR(200)     NOT NULL,
    Contact_Name VARCHAR(200)     NOT NULL,
    Email        VARCHAR(254)     NOT NULL,
    Phone        VARCHAR(50)          NULL,
    Employees    VARCHAR(20)          NULL,
    Message      TEXT             NOT NULL,
    Submitted_At DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (Inquiry_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
