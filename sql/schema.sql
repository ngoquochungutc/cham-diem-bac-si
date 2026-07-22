-- ============================================================
--  Hệ thống Đánh giá Nhân viên v2 – MySQL
--  Tiêu chí mới: CM×5, GQCV×2, GTTV×2, DTHS×1 (tổng hệ số=10)
--  Tổng điểm = c1×5 + c2×2 + c3×2 + c4×1 (KHÔNG chia trung bình)
-- ============================================================

CREATE TABLE IF NOT EXISTS employees (
    id              VARCHAR(50)  NOT NULL,
    name            VARCHAR(200) NOT NULL,
    dept            VARCHAR(200) NOT NULL DEFAULT '',
    title           VARCHAR(200) NOT NULL DEFAULT '',
    role            ENUM('employee','council') NOT NULL DEFAULT 'employee',
    password_hash   VARCHAR(255) NOT NULL DEFAULT '',
    must_change_pw  TINYINT(1)   NOT NULL DEFAULT 1,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phiếu nhân viên chấm nhân viên (kể cả tự chấm)
CREATE TABLE IF NOT EXISTS emp_scores (
    id         INT          NOT NULL AUTO_INCREMENT,
    rater_id   VARCHAR(50)  NOT NULL,
    rated_id   VARCHAR(50)  NOT NULL,
    c1         TINYINT      NOT NULL COMMENT 'Năng lực chuyên môn ×5',
    c2         TINYINT      NOT NULL COMMENT 'Giải quyết công việc ×2',
    c3         TINYINT      NOT NULL COMMENT 'Giao tiếp, tư vấn ×2',
    c4         TINYINT      NOT NULL COMMENT 'Đào tạo, hỗ trợ ×1',
    total      DECIMAL(6,1) NOT NULL COMMENT 'c1×5+c2×2+c3×2+c4×1',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_emp_rater_rated (rater_id, rated_id),
    CONSTRAINT fk_es_rater FOREIGN KEY (rater_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_es_rated FOREIGN KEY (rated_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phiếu hội đồng chấm nhân viên
CREATE TABLE IF NOT EXISTS council_scores (
    id         INT          NOT NULL AUTO_INCREMENT,
    rater_id   VARCHAR(50)  NOT NULL COMMENT 'Thành viên hội đồng',
    rated_id   VARCHAR(50)  NOT NULL COMMENT 'Nhân viên được chấm',
    c1         TINYINT      NOT NULL,
    c2         TINYINT      NOT NULL,
    c3         TINYINT      NOT NULL,
    c4         TINYINT      NOT NULL,
    total      DECIMAL(6,1) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cs_rater_rated (rater_id, rated_id),
    CONSTRAINT fk_cs_rater FOREIGN KEY (rater_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_rated FOREIGN KEY (rated_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trạng thái nộp phiếu
CREATE TABLE IF NOT EXISTS submissions (
    employee_id  VARCHAR(50)             NOT NULL,
    ballot_type  ENUM('employee','council') NOT NULL DEFAULT 'employee',
    submitted_at DATETIME                NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id, ballot_type),
    CONSTRAINT fk_sub_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
