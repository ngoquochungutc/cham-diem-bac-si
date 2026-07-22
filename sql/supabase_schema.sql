-- ============================================================
--  Hệ thống Đánh giá Nhân viên v2 – PostgreSQL (Supabase)
--  Tiêu chí mới: CM×5, GQCV×2, GTTV×2, DTHS×1 (tổng hệ số=10)
--  Tổng điểm = c1×5 + c2×2 + c3×2 + c4×1 (KHÔNG chia trung bình)
-- ============================================================

-- Tạo kiểu ENUM cho vai trò/loại phiếu
CREATE TYPE employee_role AS ENUM ('employee', 'council');

CREATE TABLE IF NOT EXISTS employees (
    id              VARCHAR(50)  NOT NULL,
    name            VARCHAR(200) NOT NULL,
    dept            VARCHAR(200) NOT NULL DEFAULT '',
    title           VARCHAR(200) NOT NULL DEFAULT '',
    role            employee_role NOT NULL DEFAULT 'employee',
    password_hash   VARCHAR(255) NOT NULL DEFAULT '',
    must_change_pw  SMALLINT     NOT NULL DEFAULT 1,
    is_active       SMALLINT     NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- Phiếu nhân viên chấm nhân viên (kể cả tự chấm)
CREATE TABLE IF NOT EXISTS emp_scores (
    id         SERIAL       NOT NULL,
    rater_id   VARCHAR(50)  NOT NULL,
    rated_id   VARCHAR(50)  NOT NULL,
    c1         SMALLINT     NOT NULL, -- Năng lực chuyên môn ×5
    c2         SMALLINT     NOT NULL, -- Giải quyết công việc ×2
    c3         SMALLINT     NOT NULL, -- Giao tiếp, tư vấn ×2
    c4         SMALLINT     NOT NULL, -- Đào tạo, hỗ trợ ×1
    total      NUMERIC(6,1) NOT NULL, -- c1×5+c2×2+c3×2+c4×1
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_emp_rater_rated UNIQUE (rater_id, rated_id),
    CONSTRAINT fk_es_rater FOREIGN KEY (rater_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_es_rated FOREIGN KEY (rated_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Phiếu hội đồng chấm nhân viên
CREATE TABLE IF NOT EXISTS council_scores (
    id         SERIAL       NOT NULL,
    rater_id   VARCHAR(50)  NOT NULL, -- Thành viên hội đồng
    rated_id   VARCHAR(50)  NOT NULL, -- Nhân viên được chấm
    c1         SMALLINT     NOT NULL,
    c2         SMALLINT     NOT NULL,
    c3         SMALLINT     NOT NULL,
    c4         SMALLINT     NOT NULL,
    total      NUMERIC(6,1) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_cs_rater_rated UNIQUE (rater_id, rated_id),
    CONSTRAINT fk_cs_rater FOREIGN KEY (rater_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_rated FOREIGN KEY (rated_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Trạng thái nộp phiếu
CREATE TABLE IF NOT EXISTS submissions (
    employee_id  VARCHAR(50)   NOT NULL,
    ballot_type  employee_role NOT NULL DEFAULT 'employee',
    submitted_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id, ballot_type),
    CONSTRAINT fk_sub_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
