CREATE TABLE IF NOT EXISTS backup_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    initiated_by_user_id INT NULL,
    trigger_source VARCHAR(32) NOT NULL DEFAULT 'daily',
    status ENUM('running', 'success', 'partial', 'failed') NOT NULL DEFAULT 'running',
    summary_message TEXT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_backup_runs_status (status),
    KEY idx_backup_runs_started_at (started_at),
    CONSTRAINT fk_backup_runs_user FOREIGN KEY (initiated_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS backup_artifacts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    backup_run_id INT UNSIGNED NOT NULL,
    artifact_type ENUM('database', 'uploads') NOT NULL,
    status ENUM('success', 'failed') NOT NULL DEFAULT 'success',
    relative_path VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    size_bytes BIGINT UNSIGNED NULL,
    checksum_sha256 CHAR(64) NULL,
    error_message TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_backup_artifacts_run (backup_run_id),
    KEY idx_backup_artifacts_type (artifact_type),
    KEY idx_backup_artifacts_created_at (created_at),
    CONSTRAINT fk_backup_artifacts_run FOREIGN KEY (backup_run_id) REFERENCES backup_runs(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS backup_download_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token CHAR(64) NOT NULL,
    backup_artifact_id INT UNSIGNED NOT NULL,
    created_by_user_id INT NULL,
    ip_address VARCHAR(45) NULL,
    expires_at DATETIME NOT NULL,
    last_downloaded_at DATETIME NULL,
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_backup_download_tokens_token (token),
    KEY idx_backup_download_tokens_artifact (backup_artifact_id),
    KEY idx_backup_download_tokens_expires_at (expires_at),
    CONSTRAINT fk_backup_download_tokens_artifact FOREIGN KEY (backup_artifact_id) REFERENCES backup_artifacts(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_backup_download_tokens_user FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
);
