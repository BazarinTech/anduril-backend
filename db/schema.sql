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

DROP DATABASE IF EXISTS sanderson;
CREATE DATABASE sanderson CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sanderson;


-- -----------------------------------------------------------------------------
-- users
-- Registration inserts email, passwrd, phone, upline, name, country.
-- `upline` is a self-reference; 0 means "no referrer" and the referral walk
-- tests for it explicitly, so it is not a nullable FK.
-- -----------------------------------------------------------------------------
CREATE TABLE users (
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
CREATE TABLE wallets (
    ID                 INT AUTO_INCREMENT PRIMARY KEY,
    userID             INT         NOT NULL,
    balance            VARCHAR(10) NOT NULL DEFAULT '0',
    income             VARCHAR(10) NOT NULL DEFAULT '0',
    status             VARCHAR(10) NOT NULL DEFAULT 'Active',
    invite_income      VARCHAR(10) NOT NULL DEFAULT '0',
    today_income       VARCHAR(10) NOT NULL DEFAULT '0',
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
CREATE TABLE transactions (
    ID          INT AUTO_INCREMENT PRIMARY KEY,
    userID      VARCHAR(10)  NOT NULL,
    type        VARCHAR(30)  NOT NULL,
    amount      VARCHAR(10)  NOT NULL,
    time        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status      VARCHAR(10)  NOT NULL DEFAULT 'Pending',
    description VARCHAR(255) NOT NULL DEFAULT '',
    fees        VARCHAR(10)  NOT NULL DEFAULT '0',
    trackingID  VARCHAR(50)  NOT NULL DEFAULT '',
    account     VARCHAR(255) NOT NULL DEFAULT '',
    name        VARCHAR(50)  NOT NULL DEFAULT '',
    method      VARCHAR(10)  NOT NULL DEFAULT 'mpesa',
    KEY idx_tx_user     (userID),
    KEY idx_tx_tracking (trackingID),
    KEY idx_tx_type     (type),
    KEY idx_tx_status   (status)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- products
-- The investment plans. `returns` is a flat daily payout, not a percentage.
-- `order_limit` caps concurrent active orders per user; product ID 1 is
-- additionally hardcoded in invest.php as a once-ever free trial.
-- -----------------------------------------------------------------------------
CREATE TABLE products (
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
CREATE TABLE orders (
    ID        INT AUTO_INCREMENT PRIMARY KEY,
    userID    INT         NOT NULL,
    prodID    INT         NOT NULL,
    status    VARCHAR(10) NOT NULL DEFAULT 'Active',
    remaining VARCHAR(10) NOT NULL DEFAULT '0',
    duration  VARCHAR(10) NOT NULL DEFAULT '0',
    time      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    returns   VARCHAR(10) NOT NULL DEFAULT '0',
    type      VARCHAR(10) NOT NULL,
    rolls     INT         NOT NULL DEFAULT 1,
    amount    VARCHAR(10) NOT NULL DEFAULT '0',
    KEY idx_orders_user   (userID),
    KEY idx_orders_type   (type),
    KEY idx_orders_prod   (prodID),
    KEY idx_orders_status (status)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- controls
-- Single-row settings table. Every read is `select('controls')` followed by
-- `[0]`, so exactly one row must always exist or the whole API breaks.
-- Percentages are whole numbers: level1 = 10 means 10%.
-- -----------------------------------------------------------------------------
CREATE TABLE controls (
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
    tranFee            INT         NOT NULL DEFAULT 2
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------------
-- bonus
-- Referral milestones. type: 'users' (total downlines) | 'actives' (active
-- downlines). reward_type is 'money' -- the code has no other branch.
-- -----------------------------------------------------------------------------
CREATE TABLE bonus (
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
CREATE TABLE coupons (
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
CREATE TABLE incentives (
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
CREATE TABLE incentives_requests (
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
CREATE TABLE verification_codes (
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
CREATE TABLE admins (
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
CREATE TABLE sessions (
    id        VARCHAR(128) NOT NULL PRIMARY KEY,
    data      TEXT         NOT NULL,
    timestamp INT          NOT NULL,
    KEY idx_sessions_ts (timestamp)
) ENGINE=InnoDB;
