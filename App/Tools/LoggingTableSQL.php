<?php
// Extracted from App/Tools/build for reuse in tests and build

function getLoggingTableSQL($dbType) {
    switch (strtolower($dbType)) {
        case 'mysql':
            return "
CREATE TABLE IF NOT EXISTS __LOG_ACTIVITY (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  date DATETIME NOT NULL,
  user VARCHAR(255) DEFAULT NULL,
  method VARCHAR(64) DEFAULT NULL,
  status_code INT DEFAULT 200,
  response_time INT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT DEFAULT NULL,
  request_uri TEXT DEFAULT NULL,
  trace_id VARCHAR(64) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS __LOG_ACTIVITY_OPTIONS (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_log_activity INT NOT NULL,
  subOption VARCHAR(255) NOT NULL,
  value VARCHAR(255) NOT NULL,
  KEY id_log_activity (id_log_activity),
  FOREIGN KEY (id_log_activity) REFERENCES __LOG_ACTIVITY(id)
);

CREATE TABLE IF NOT EXISTS __LOG_DATA (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_log_activity INT NOT NULL,
  `table` VARCHAR(255) NOT NULL,
  pk VARCHAR(255) NULL,
  method VARCHAR(10) NULL,
  field VARCHAR(255) NOT NULL,
  dataBefore VARCHAR(255) NULL,
  dataAfter VARCHAR(255) NULL,
  KEY id_log_activity (id_log_activity),
  FOREIGN KEY (id_log_activity) REFERENCES __LOG_ACTIVITY(id)
);

CREATE TABLE IF NOT EXISTS __LOG_ERRORS (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_log_activity INT NULL,
  trace_id VARCHAR(64) DEFAULT NULL,
  error_code VARCHAR(50) NOT NULL,
  error_message TEXT NOT NULL,
  http_status INT DEFAULT 500,
  exception_class VARCHAR(255) DEFAULT NULL,
  exception_file VARCHAR(500) DEFAULT NULL,
  exception_line INT DEFAULT NULL,
  exception_trace TEXT DEFAULT NULL,
  context VARCHAR(50) DEFAULT 'API',
  severity VARCHAR(20) DEFAULT 'ERROR',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_trace_id (trace_id),
  KEY idx_error_code (error_code),
  KEY idx_http_status (http_status),
  KEY idx_created_at (created_at),
  FOREIGN KEY (id_log_activity) REFERENCES __LOG_ACTIVITY(id) ON DELETE SET NULL
);
";
        case 'sqlite':
            return "
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS __LOG_ACTIVITY (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  date TEXT NOT NULL,
  user TEXT,
  method TEXT,
  status_code INTEGER DEFAULT 200,
  response_time INTEGER,
  ip_address TEXT,
  user_agent TEXT,
  request_uri TEXT,
  trace_id TEXT
);

CREATE TABLE IF NOT EXISTS __LOG_ACTIVITY_OPTIONS (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_log_activity INTEGER NOT NULL,
  subOption TEXT NOT NULL,
  value TEXT NOT NULL,
  FOREIGN KEY (id_log_activity) REFERENCES __LOG_ACTIVITY(id)
);

CREATE TABLE IF NOT EXISTS __LOG_DATA (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_log_activity INTEGER NOT NULL,
  `table` TEXT NOT NULL,
  pk TEXT,
  method TEXT,
  field TEXT NOT NULL,
  dataBefore TEXT,
  dataAfter TEXT,
  FOREIGN KEY (id_log_activity) REFERENCES __LOG_ACTIVITY(id)
);

CREATE TABLE IF NOT EXISTS __LOG_ERRORS (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_log_activity INTEGER,
  trace_id TEXT,
  error_code TEXT NOT NULL,
  error_message TEXT NOT NULL,
  http_status INTEGER DEFAULT 500,
  exception_class TEXT,
  exception_file TEXT,
  exception_line INTEGER,
  exception_trace TEXT,
  context TEXT DEFAULT 'API',
  severity TEXT DEFAULT 'ERROR',
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (id_log_activity) REFERENCES __LOG_ACTIVITY(id)
);
";
        case 'mssql':
        case 'sqlsrv':
            return "
CREATE TABLE __LOG_ACTIVITY (
  id INT IDENTITY(1,1) PRIMARY KEY,
  date DATETIME NOT NULL,
  [user] NVARCHAR(255) NULL,
  method NVARCHAR(64) NULL,
  status_code INT DEFAULT 200,
  response_time INT NULL,
  ip_address NVARCHAR(45) NULL,
  user_agent NVARCHAR(MAX) NULL,
  request_uri NVARCHAR(MAX) NULL,
  trace_id NVARCHAR(64) NULL
);

CREATE TABLE __LOG_ACTIVITY_OPTIONS (
  id INT IDENTITY(1,1) PRIMARY KEY,
  id_log_activity INT NOT NULL,
  subOption NVARCHAR(255) NOT NULL,
  value NVARCHAR(255) NOT NULL,
  FOREIGN KEY (id_log_activity) REFERENCES __LOG_ACTIVITY(id)
);

CREATE TABLE __LOG_DATA (
  id INT IDENTITY(1,1) PRIMARY KEY,
  id_log_activity INT NOT NULL,
  [table] NVARCHAR(255) NOT NULL,
  pk NVARCHAR(255) NULL,
  method NVARCHAR(10) NULL,
  field NVARCHAR(255) NOT NULL,
  dataBefore NVARCHAR(255) NULL,
  dataAfter NVARCHAR(255) NULL,
  FOREIGN KEY (id_log_activity) REFERENCES __LOG_ACTIVITY(id)
);

CREATE TABLE __LOG_ERRORS (
  id INT IDENTITY(1,1) PRIMARY KEY,
  id_log_activity INT NULL,
  trace_id NVARCHAR(64) NULL,
  error_code NVARCHAR(50) NOT NULL,
  error_message NVARCHAR(MAX) NOT NULL,
  http_status INT DEFAULT 500,
  exception_class NVARCHAR(255) NULL,
  exception_file NVARCHAR(500) NULL,
  exception_line INT NULL,
  exception_trace NVARCHAR(MAX) NULL,
  context NVARCHAR(50) DEFAULT 'API',
  severity NVARCHAR(20) DEFAULT 'ERROR',
  created_at DATETIME DEFAULT GETDATE(),
  FOREIGN KEY (id_log_activity) REFERENCES __LOG_ACTIVITY(id)
);
";
        default:
            throw new Exception("Unsupported DB type: $dbType");
    }
} 