-- =====================================================================
-- UmaLink: Agriculture Information System
-- schema.sql  -  Supabase PostgreSQL (also runs on any PostgreSQL 13+)
-- Run order: schema.sql -> lookup_data.sql -> seed.sql
-- =====================================================================

-- Clean re-run (safe on an empty project)
DROP TABLE IF EXISTS audit_log      CASCADE;
DROP TABLE IF EXISTS harvest        CASCADE;
DROP TABLE IF EXISTS planting       CASCADE;
DROP TABLE IF EXISTS farm           CASCADE;
DROP TABLE IF EXISTS crop           CASCADE;
DROP TABLE IF EXISTS farmer         CASCADE;
DROP TABLE IF EXISTS users          CASCADE;
DROP TABLE IF EXISTS harvest_remark CASCADE;
DROP TABLE IF EXISTS quality_grade  CASCADE;
DROP TABLE IF EXISTS planting_status CASCADE;
DROP TABLE IF EXISTS season         CASCADE;
DROP TABLE IF EXISTS crop_category  CASCADE;
DROP TABLE IF EXISTS irrigation_type CASCADE;
DROP TABLE IF EXISTS soil_type      CASCADE;
DROP TABLE IF EXISTS role           CASCADE;
DROP FUNCTION IF EXISTS set_updated_at() CASCADE;
DROP FUNCTION IF EXISTS check_harvest_date() CASCADE;

-- ---------------------------------------------------------------------
-- LOOKUP / REFERENCE TABLES
-- ---------------------------------------------------------------------
CREATE TABLE role (
    role_id          SMALLINT    PRIMARY KEY,
    role_description VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE soil_type (
    soil_type_id          SMALLINT    PRIMARY KEY,
    soil_type_description VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE irrigation_type (
    irrigation_type_id          SMALLINT    PRIMARY KEY,
    irrigation_type_description VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE crop_category (
    category_id          SMALLINT    PRIMARY KEY,
    category_description VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE season (
    season_id          SMALLINT    PRIMARY KEY,
    season_description VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE planting_status (
    status_id          SMALLINT    PRIMARY KEY,
    status_description VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE quality_grade (
    grade_id          SMALLINT    PRIMARY KEY,
    grade_description VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE harvest_remark (
    remark_id          SMALLINT    PRIMARY KEY,
    remark_description VARCHAR(50) NOT NULL UNIQUE
);

-- ---------------------------------------------------------------------
-- USERS (authentication + roles)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id       INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    full_name     VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id       SMALLINT     NOT NULL REFERENCES role(role_id),
    is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_username_len CHECK (char_length(username) >= 3)
);

-- ---------------------------------------------------------------------
-- CORE TABLES
-- ---------------------------------------------------------------------
CREATE TABLE farmer (
    farmer_id       INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    contact_number  VARCHAR(20)  NOT NULL UNIQUE,
    address         VARCHAR(150) NOT NULL,
    date_registered DATE         NOT NULL DEFAULT CURRENT_DATE,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    updated_by      INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_farmer_contact CHECK (contact_number ~ '^[0-9+() -]{7,20}$')
);

CREATE TABLE farm (
    farm_id            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    farmer_id          INTEGER      NOT NULL REFERENCES farmer(farmer_id)
                                    ON DELETE RESTRICT ON UPDATE CASCADE,
    farm_name          VARCHAR(100) NOT NULL,
    location           VARCHAR(150) NOT NULL,
    area_hectares      NUMERIC(8,2) NOT NULL,
    soil_type_id       SMALLINT     NOT NULL REFERENCES soil_type(soil_type_id),
    irrigation_type_id SMALLINT     NOT NULL REFERENCES irrigation_type(irrigation_type_id),
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by         INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    updated_by         INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_farm_area CHECK (area_hectares > 0 AND area_hectares <= 10000),
    CONSTRAINT uq_farm_per_farmer UNIQUE (farmer_id, farm_name)
);

CREATE TABLE crop (
    crop_id             INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    crop_name           VARCHAR(80) NOT NULL,
    variety             VARCHAR(80) NOT NULL,
    category_id         SMALLINT    NOT NULL REFERENCES crop_category(category_id),
    growing_period_days INTEGER     NOT NULL,
    season_id           SMALLINT    NOT NULL REFERENCES season(season_id),
    created_at          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    updated_by          INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_crop_period CHECK (growing_period_days BETWEEN 1 AND 1500),
    CONSTRAINT uq_crop_variety UNIQUE (crop_name, variety)
);

CREATE TABLE planting (
    planting_id           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    farm_id               INTEGER      NOT NULL REFERENCES farm(farm_id)
                                       ON DELETE RESTRICT ON UPDATE CASCADE,
    crop_id               INTEGER      NOT NULL REFERENCES crop(crop_id)
                                       ON DELETE RESTRICT ON UPDATE CASCADE,
    planting_date         DATE         NOT NULL,
    area_planted          NUMERIC(8,2) NOT NULL,
    seed_quantity         NUMERIC(8,2) NOT NULL,
    expected_harvest_date DATE         NOT NULL,
    status_id             SMALLINT     NOT NULL DEFAULT 1 REFERENCES planting_status(status_id),
    created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by            INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    updated_by            INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_planting_area  CHECK (area_planted > 0),
    CONSTRAINT chk_planting_seed  CHECK (seed_quantity > 0),
    CONSTRAINT chk_planting_dates CHECK (expected_harvest_date > planting_date)
);

CREATE TABLE harvest (
    harvest_id           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    planting_id          INTEGER       NOT NULL REFERENCES planting(planting_id)
                                       ON DELETE RESTRICT ON UPDATE CASCADE,
    harvest_date         DATE          NOT NULL,
    quantity_kg          NUMERIC(10,2) NOT NULL,
    selling_price_per_kg NUMERIC(10,2) NOT NULL,
    grade_id             SMALLINT      NOT NULL REFERENCES quality_grade(grade_id),
    remark_id            SMALLINT      NOT NULL REFERENCES harvest_remark(remark_id),
    created_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by           INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    updated_by           INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_harvest_qty   CHECK (quantity_kg > 0),
    CONSTRAINT chk_harvest_price CHECK (selling_price_per_kg > 0)
);

-- ---------------------------------------------------------------------
-- AUDIT LOG (who did what, and when)
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
    audit_id    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id     INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    action      VARCHAR(20)  NOT NULL,
    table_name  VARCHAR(30)  NOT NULL,
    record_id   INTEGER,
    details     VARCHAR(255),
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_audit_action CHECK (action IN ('LOGIN','LOGOUT','CREATE','UPDATE','DELETE'))
);

-- ---------------------------------------------------------------------
-- INDEXES on foreign keys and frequently filtered columns
-- ---------------------------------------------------------------------
CREATE INDEX idx_farm_farmer      ON farm(farmer_id);
CREATE INDEX idx_planting_farm    ON planting(farm_id);
CREATE INDEX idx_planting_crop    ON planting(crop_id);
CREATE INDEX idx_planting_status  ON planting(status_id);
CREATE INDEX idx_planting_date    ON planting(planting_date);
CREATE INDEX idx_harvest_planting ON harvest(planting_id);
CREATE INDEX idx_harvest_date     ON harvest(harvest_date);
CREATE INDEX idx_audit_created    ON audit_log(created_at);

-- ---------------------------------------------------------------------
-- TRIGGERS
-- ---------------------------------------------------------------------
-- 1) Keep updated_at current on every UPDATE
CREATE FUNCTION set_updated_at() RETURNS trigger AS $$
BEGIN
    NEW.updated_at := CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_users_upd    BEFORE UPDATE ON users    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER trg_farmer_upd   BEFORE UPDATE ON farmer   FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER trg_farm_upd     BEFORE UPDATE ON farm     FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER trg_crop_upd     BEFORE UPDATE ON crop     FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER trg_planting_upd BEFORE UPDATE ON planting FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER trg_harvest_upd  BEFORE UPDATE ON harvest  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- 2) A harvest cannot happen before its planting date (cross-table rule,
--    which a CHECK constraint cannot express)
CREATE FUNCTION check_harvest_date() RETURNS trigger AS $$
DECLARE
    p_date DATE;
BEGIN
    SELECT planting_date INTO p_date FROM planting WHERE planting_id = NEW.planting_id;
    IF NEW.harvest_date < p_date THEN
        RAISE EXCEPTION 'Harvest date (%) cannot be earlier than the planting date (%).',
            NEW.harvest_date, p_date USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_harvest_date BEFORE INSERT OR UPDATE ON harvest
    FOR EACH ROW EXECUTE FUNCTION check_harvest_date();
