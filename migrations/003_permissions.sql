--
--  Database permissions
-- ---------------------
CREATE TABLE permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resource VARCHAR(100) NOT NULL,
  privilege VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
