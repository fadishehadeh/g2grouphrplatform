CREATE TABLE IF NOT EXISTS letter_templates (
    id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    letter_type  VARCHAR(50)  NOT NULL UNIQUE,
    body_content TEXT         NOT NULL,
    updated_by   INT          NULL,
    updated_at   DATETIME     NULL,
    CONSTRAINT fk_lt_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);
