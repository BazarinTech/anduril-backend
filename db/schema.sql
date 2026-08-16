-- =============================================================================
--  SANDERSON / ANDURIL BACKEND -- DATABASE SCHEMA
-- =============================================================================
--  Reconstructed from the queries in this codebase, using the deployed sibling
--  database (`grover`) as the structural baseline, then reconciled against
--  every column this project actually reads and writes.
--
--  Apply with:
--      mysql -u root -p < db/schema.sql
--
--  DELIBERATE FIDELITY CHOICES
--  ---------------------------
--  Money is stored in VARCHAR columns. That is wrong, and it is also what
--  production does; PHP coerces on the way in and out so the arithmetic works.
--  It is kept here so local behaviour matches the server rather than quietly
--  diverging. Converting these to DECIMAL(14,2) belongs with the Phase 3
--  locking work, where the money paths are being touched anyway.
--
--  `orders.prodID` is INT, matching production. admin/user-reward.php inserts
--  the string 'REWARD' into it, which fails under STRICT_TRANS_TABLES. That is
--  a real bug in the application, reproduced faithfully rather than papered
--  over by widening the column.
--
--  DELIBERATE DIVERGENCES FROM `grover`
--  ------------------------------------
--   * transactions.type widened 10 -> 30. backend/mains/returns.php inserts
--     'Product Income' (14 chars), which errors under strict mode at 10.
--   * admins gains name, email, passwrd, phone -- written by admin/register.php
--     and admin/actions/update_admins.php, absent from grover.
--   * products gains image -- written by admin/products.php, read by mains.php.
--   * incentives gains bonusItem -- read by backend/mains/mains.php:268.
--   * incentives_requests gains status -- written by incentive-application.php.
--   * Secondary indexes added on the foreign-key-ish and lookup columns. These
--     change performance only, never results.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- NO DROP/CREATE/USE DATABASE, deliberately.
--
-- This file used to open by dropping and recreating a database called
-- `sanderson`. That is wrong anywhere the database is provisioned for you --
-- a managed MySQL hands you a database whose name you do not choose, and
-- creating a second one named `sanderson` inside it puts every table
-- somewhere the application will never look. The symptom is the worst kind:
-- healthy container, healthy database, correct credentials, and every page
-- 500ing on a missing table.
--
-- It also made this file destructive. Piped at the wrong host it silently
-- discarded a live database.
--
-- Every table below is created only if absent, so this file is safe to
-- apply repeatedly and safe to apply to a database that is already populated.
--
-- Apply it against a named database:
--     mysql -u root -p my_database < db/schema.sql
--
-- To reset a local database from scratch, drop it explicitly first:
--     mysql -u root -p -e "DROP DATABASE IF EXISTS sanderson; CREATE DATABASE sanderson"
-- -----------------------------------------------------------------------------


-- -----------------------------------------------------------------------------
-- users
-- Registration inserts email, passwrd, phone, upline, name, country.
-- `upline` is a self-reference; 0 means "no referrer" and the referral walk
-- tests for it explicitly, so it is not a nullable FK.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    ID           INT AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(255) NOT NULL,
    phone        VARCHAR(13)  NOT NULL,
    passwrd      VARCHAR(255) NOT NULL,
    date_created TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status       VARCHAR(10)  NOT NULL DEFAULT 'Inactive',
    last_active  VARCHAR(255) NOT NULL DEFAULT '',
    upline       INT          NOT NULL DEFAULT 0,
    name         VARCHAR(255) NOT NULL DEFAULT '',
    username     VARCHAR(16)  NOT NULL DEFAULT '',
    role         VARCHAR(10)  NOT NULL DEFAULT 'user',
    country      VARCHAR(10)  NOT NULL DEFAULT '254',
    KEY idx_users_phone    (phone),
    KEY idx_users_email    (email),
    KEY idx_users_upline   (upline),
    KEY idx_users_username (username),
    KEY idx_users_status   (status)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- wallets
-- One row per user, created immediately after registration.
-- `level` is the incentive tier ('lvl1'), not a referral depth.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wallets (
    ID                 INT AUTO_INCREMENT PRIMARY KEY,
    userID             INT         NOT NULL,
    balance            VARCHAR(10) NOT NULL DEFAULT '0',
    income             VARCHAR(10) NOT NULL DEFAULT '0',
    status             VARCHAR(10) NOT NULL DEFAULT 'Active',
    invite_income      VARCHAR(10) NOT NULL DEFAULT '0',
    today_income       VARCHAR(10) NOT NULL DEFAULT '0',
    -- Which claim period today_income was last written in. The nightly job
    -- used to zero that column; it now rolls over on the first credit of a
    -- new period, so nothing has to run at midnight for it to read correctly.
    income_period      DATE        NULL     DEFAULT NULL,
    level              VARCHAR(20) NOT NULL DEFAULT 'lvl1',
    withdrawal_account VARCHAR(15)  NOT NULL DEFAULT '',
    withdrawal_name    VARCHAR(50)  NOT NULL DEFAULT '',
    -- Holds a password_hash() digest, not the four digits the user types.
    -- bcrypt is 60 chars; 255 leaves room for a future algorithm change.
    withdrawal_pin     VARCHAR(255) NOT NULL DEFAULT '',
    KEY idx_wallets_user (userID)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- transactions
-- type:   Deposit | Withdraw | Transfer | Product Income | Commission | Bonus
-- status: Pending | Processing | Success | Failed | Completed
-- `trackingID` is the correlation key the payment callbacks match on, so it
-- carries an index -- every callback looks a row up by it.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    ID          INT AUTO_INCREMENT PRIMARY KEY,
    userID      VARCHAR(10)  NOT NULL,
    type        VARCHAR(30)  NOT NULL,
    amount      VARCHAR(10)  NOT NULL,
    time        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status      VARCHAR(10)  NOT NULL DEFAULT 'Pending',
    description VARCHAR(255) NOT NULL DEFAULT '',
    fees        VARCHAR(10)  NOT NULL DEFAULT '0',
    -- The provider's own transaction UUID for M-Pesa deposits. It is what
    -- Palpluss quotes in callbacks and in support, so it is the reference we
    -- key on. Non-provider rows (transfers, commissions) still carry a
    -- locally generated value or nothing at all.
    trackingID  VARCHAR(50)  NOT NULL DEFAULT '',
    -- The reference we generated and sent as accountReference and
    -- Idempotency-Key. Kept so a deposit can be traced from our side even
    -- before the provider answers, and so a callback quoting our reference
    -- rather than theirs still finds the row.
    local_ref   VARCHAR(50)  NOT NULL DEFAULT '',
    account     VARCHAR(255) NOT NULL DEFAULT '',
    name        VARCHAR(50)  NOT NULL DEFAULT '',
    method      VARCHAR(10)  NOT NULL DEFAULT 'mpesa',
    KEY idx_tx_user     (userID),
    KEY idx_tx_tracking (trackingID),
    KEY idx_tx_local    (local_ref),
    KEY idx_tx_type     (type),
    KEY idx_tx_status   (status)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- products
-- The investment plans. `returns` is a flat daily payout, not a percentage.
-- `order_limit` caps concurrent active orders per user; product ID 1 is
-- additionally hardcoded in invest.php as a once-ever free trial.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    ID          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    returns     VARCHAR(10)  NOT NULL,
    min         VARCHAR(10)  NOT NULL,
    max         VARCHAR(10)  NOT NULL,
    duration    VARCHAR(10)  NOT NULL,
    status      VARCHAR(10)  NOT NULL DEFAULT 'Active',
    description VARCHAR(255) NOT NULL DEFAULT '',
    time        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tier        VARCHAR(20)  NOT NULL DEFAULT 'Starter',
    riskLevel   INT          NOT NULL DEFAULT 1,
    order_limit INT          NOT NULL DEFAULT 1,
    image       VARCHAR(255) NOT NULL DEFAULT '',
    KEY idx_products_status (status)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- orders
-- One table, four meanings, discriminated by `type`:
--   investment -> prodID references products.ID
--   bonus      -> prodID references bonus.ID
--   coupon     -> prodID references coupons.ID
--   reward     -> prodID is the literal string 'REWARD' (see header note)
-- `rolls` gates the daily claim: 1 = claimable, 0 = already claimed today.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    ID        INT AUTO_INCREMENT PRIMARY KEY,
    userID    INT         NOT NULL,
    prodID    INT         NOT NULL,
    status    VARCHAR(10) NOT NULL DEFAULT 'Active',
    remaining VARCHAR(10) NOT NULL DEFAULT '0',
    duration  VARCHAR(10) NOT NULL DEFAULT '0',
    time      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    returns   VARCHAR(10) NOT NULL DEFAULT '0',
    type      VARCHAR(10) NOT NULL,
    -- Superseded by the claim_period / claims_in_period pair below. Kept so a
    -- rollback has something to fall back on; every claim still zeroes it.
    rolls     INT         NOT NULL DEFAULT 1,
    -- Which claim period the counter belongs to, keyed by the date the window
    -- opened. NULL means this order has never been claimed.
    claim_period     DATE     NULL DEFAULT NULL,
    claims_in_period INT      NOT NULL DEFAULT 0,
    last_claim_at    DATETIME NULL DEFAULT NULL,
    amount    VARCHAR(10) NOT NULL DEFAULT '0',
    KEY idx_orders_user   (userID),
    KEY idx_orders_type   (type),
    KEY idx_orders_prod   (prodID),
    KEY idx_orders_status (status),
    KEY idx_orders_claim_period (claim_period)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- controls
-- Single-row settings table. Every read is `select('controls')` followed by
-- `[0]`, so exactly one row must always exist or the whole API breaks.
-- Percentages are whole numbers: level1 = 10 means 10%.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS controls (
    ID                 INT AUTO_INCREMENT PRIMARY KEY,
    minWith            VARCHAR(10) NOT NULL DEFAULT '200',
    minDep             VARCHAR(10) NOT NULL DEFAULT '100',
    level1             VARCHAR(10) NOT NULL DEFAULT '10',
    level2             VARCHAR(10) NOT NULL DEFAULT '5',
    level3             VARCHAR(2)  NOT NULL DEFAULT '3',
    transactionType    VARCHAR(10) NOT NULL DEFAULT 'Auto',
    transactionAccount VARCHAR(10) NOT NULL DEFAULT '183',
    withFee            VARCHAR(10) NOT NULL DEFAULT '5',
    minTransfer        VARCHAR(10) NOT NULL DEFAULT '500',
    tranFee            INT         NOT NULL DEFAULT 2,
    -- Daily claim window. '0' on claimWindowOn accepts claims at any hour,
    -- which is how the platform behaved before the window existed.
    claimWindowOn      VARCHAR(1)  NOT NULL DEFAULT '1',
    claimOpensAt       TIME        NOT NULL DEFAULT '07:00:00',
    -- NULL means the window stays open until it next opens.
    claimClosesAt      TIME        NULL     DEFAULT NULL,
    claimsPerDay       INT         NOT NULL DEFAULT 1
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- bonus
-- Referral milestones. type: 'users' (total downlines) | 'actives' (active
-- downlines). reward_type is 'money' -- the code has no other branch.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bonus (
    ID          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    type        VARCHAR(20)  NOT NULL,
    reward      VARCHAR(10)  NOT NULL,
    target      VARCHAR(10)  NOT NULL,
    status      VARCHAR(10)  NOT NULL DEFAULT 'Active',
    time        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reward_type VARCHAR(255) NOT NULL DEFAULT 'money',
    KEY idx_bonus_status (status)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- coupons
-- `expiry` is a lifetime in MINUTES measured from time_created, not a date.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
    ID           INT AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(10) NOT NULL,
    amount       VARCHAR(10) NOT NULL,
    time_created TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expiry       INT         NOT NULL DEFAULT 60,
    status       VARCHAR(10) NOT NULL DEFAULT 'Active',
    KEY idx_coupons_code (code)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- incentives
-- Salary tiers users apply for once they hit a referral count.
-- `bonusItem` is a physical reward description shown in the app.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS incentives (
    ID        INT AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(20)  NOT NULL,
    referrals INT          NOT NULL,
    salary    VARCHAR(10)  NOT NULL,
    date      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status    VARCHAR(10)  NOT NULL DEFAULT 'Active',
    level     VARCHAR(10)  NOT NULL,
    bonusItem VARCHAR(255) NOT NULL DEFAULT '',
    KEY idx_incentives_status (status)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- incentives_requests
-- One application per user per incentive; the API enforces that by counting.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS incentives_requests (
    ID          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50) NOT NULL,
    phone       VARCHAR(15) NOT NULL,
    personal_ID VARCHAR(10) NOT NULL,
    date        TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    userID      VARCHAR(10) NOT NULL,
    incentiveID VARCHAR(12) NOT NULL,
    status      VARCHAR(10) NOT NULL DEFAULT 'Pending',
    KEY idx_ir_user      (userID),
    KEY idx_ir_incentive (incentiveID),
    KEY idx_ir_status    (status)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- verification_codes
-- `expiry` is minutes from time_created. Nothing enforces it yet (Phase 2.4).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS verification_codes (
    ID           INT AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(20) NOT NULL,
    expiry       INT         NOT NULL DEFAULT 5,
    time_created TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    phone        VARCHAR(15) NOT NULL,
    -- Pending -> Verified -> Used. 'Used' is terminal: a code that has already
    -- reset a password cannot reset another one (Phase 2.5).
    status       VARCHAR(10) NOT NULL DEFAULT 'Pending',
    type         VARCHAR(20) NOT NULL,
    KEY idx_vc_phone  (phone),
    KEY idx_vc_code   (code),
    KEY idx_vc_lookup (phone, status, time_created)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- admins
-- `userID` points at users.ID -- admin login authenticates against `users`,
-- then checks for a matching row here. `permissions` is a bracketed string
-- such as '[view][edit][add][finance]', parsed with a regex.
-- name/email/passwrd/phone are written by admin/register.php.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    ID           INT AUTO_INCREMENT PRIMARY KEY,
    userID       INT          NOT NULL,
    roles        VARCHAR(255) NOT NULL DEFAULT 'admin',
    permissions  VARCHAR(255) NOT NULL DEFAULT '',
    status       VARCHAR(10)  NOT NULL DEFAULT 'Active',
    date_created TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    username     VARCHAR(20)  NOT NULL,
    name         VARCHAR(255) NOT NULL DEFAULT '',
    email        VARCHAR(255) NOT NULL DEFAULT '',
    passwrd      VARCHAR(255) NOT NULL DEFAULT '',
    phone        VARCHAR(15)  NOT NULL DEFAULT '',
    KEY idx_admins_user (userID),
    KEY idx_admins_username (username)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- sessions
-- Backing store for DbSessionHandler in bootstrap/admin.php.
-- `timestamp` is a unix epoch int, used by the GC sweep.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id        VARCHAR(128) NOT NULL PRIMARY KEY,
    data      TEXT         NOT NULL,
    timestamp INT          NOT NULL,
    KEY idx_sessions_ts (timestamp)
) ENGINE=InnoDB;
