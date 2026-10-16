CREATE DATABASE susu_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;


USE susu_db;

-- 1. Store the savings group configuration.
CREATE TABLE settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    weekly_contribution_pesewas BIGINT UNSIGNED NOT NULL,
    current_week INT UNSIGNED NOT NULL DEFAULT 1,
    next_member_order INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_weekly_amount
        CHECK (weekly_contribution_pesewas > 0)
) ENGINE=InnoDB;

-- Create the group settings.
INSERT INTO settings (
    id,
    weekly_contribution_pesewas
) VALUES (1, 5000);

-- 2. Store members in joining order.
CREATE TABLE members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_key VARCHAR(100)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci
        NOT NULL,
    join_order INT UNSIGNED NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member_name (name_key)
) ENGINE=InnoDB;

-- 3. Store each week's status.
CREATE TABLE weeks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    week_number INT UNSIGNED NOT NULL UNIQUE,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    closed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Week 1 starts open.
INSERT INTO weeks (week_number) VALUES (1);

-- 4. Store every individual contribution.
CREATE TABLE contributions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    week_id INT UNSIGNED NOT NULL,
    amount_pesewas BIGINT UNSIGNED NOT NULL,
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_contribution_amount
        CHECK (amount_pesewas > 0),

    CONSTRAINT fk_contribution_member
        FOREIGN KEY (member_id) REFERENCES members(id),

    CONSTRAINT fk_contribution_week
        FOREIGN KEY (week_id) REFERENCES weeks(id),

    INDEX idx_contribution_week_member (week_id, member_id)
) ENGINE=InnoDB;

-- 5. Store successful payouts.
CREATE TABLE payouts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    week_id INT UNSIGNED NOT NULL UNIQUE,
    recipient_member_id INT UNSIGNED NOT NULL,
    amount_pesewas BIGINT UNSIGNED NOT NULL,
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_payout_amount
        CHECK (amount_pesewas > 0),

    CONSTRAINT fk_payout_week
        FOREIGN KEY (week_id) REFERENCES weeks(id),

    CONSTRAINT fk_payout_member
        FOREIGN KEY (recipient_member_id) REFERENCES members(id)
) ENGINE=InnoDB;
