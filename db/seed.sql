-- =============================================================================
--  SANDERSON / ANDURIL BACKEND -- DEVELOPMENT SEED DATA
-- =============================================================================
--  Apply after schema.sql:
--      mysql -u root -p < db/seed.sql
--
--  The `controls` row is not optional. Every read is
--  `$query->select('controls')` followed by `[0]`, with no empty check, so a
--  controls-less database breaks the dashboard, deposits, withdrawals,
--  transfers and the referral commission walk.
--
--  Passwords and withdrawal PINs are password_hash() digests as of Phase 2.1
--  and 2.3. The plaintext each one stands for is named beside it below.
--  To change one:
--
--      php -r 'echo password_hash("newpassword", PASSWORD_DEFAULT);'
-- =============================================================================

USE sanderson;


-- -----------------------------------------------------------------------------
-- Platform settings -- exactly one row, forever.
-- -----------------------------------------------------------------------------
INSERT INTO controls
    (minWith, minDep, level1, level2, level3, transactionType, transactionAccount, withFee, minTransfer, tranFee)
VALUES
    ('200', '100', '10', '5', '3', 'Auto', '183', '5', '500', 2);


-- -----------------------------------------------------------------------------
-- Investment products.
-- ID 1 must stay the free trial: invest.php hardcodes `$prodID == 1` as a
-- once-per-account plan regardless of order_limit.
-- Images are the files already present in admin/uploads/.
-- -----------------------------------------------------------------------------
INSERT INTO products (ID, name, returns, min, max, duration, status, description, tier, riskLevel, order_limit, image) VALUES
(1, 'Trial Coop',      '15',  '100',  '100',  '3',  'Active', 'Free starter plan. One per account, three days of returns.',        'Trial',   1, 1, '1774940539_party-wings.webp'),
(2, 'Broiler Starter', '60',  '500',  '2000', '30', 'Active', 'Entry tier. Daily returns for thirty days.',                        'Starter', 1, 3, '1774942808_drumsticks.webp'),
(3, 'Layer Standard',  '180', '2000', '10000','45', 'Active', 'Mid tier with a longer run and higher daily payout.',               'Growth',  2, 3, '1774942900_thighs.webp'),
(4, 'Premium Flock',   '520', '10000','50000','60', 'Active', 'Top tier. Highest daily return over a sixty day cycle.',            'Premium', 3, 2, '1774943516_premium-thighs.webp'),
(5, 'Retired Plan',    '40',  '500',  '1000', '20', 'Inactive','Kept inactive so the product-status filter has something to hide.','Starter', 1, 1, '1774943354_skinless-rib.webp');


-- -----------------------------------------------------------------------------
-- Referral milestone bonuses.
-- type 'users' counts every downline; 'actives' counts only Active ones.
-- -----------------------------------------------------------------------------
INSERT INTO bonus (name, type, reward, target, status, reward_type) VALUES
('First Five',    'users',   '250',  '5',  'Active', 'money'),
('Ten Active',    'actives', '1000', '10', 'Active', 'money'),
('Twenty Strong', 'users',   '2500', '20', 'Active', 'money');


-- -----------------------------------------------------------------------------
-- Salary incentives.
-- -----------------------------------------------------------------------------
INSERT INTO incentives (name, referrals, salary, status, level, bonusItem) VALUES
('Bronze', 20,  '5000',  'Active', 'lvl2', 'Branded jacket'),
('Silver', 50,  '15000', 'Active', 'lvl3', 'Smartphone'),
('Gold',   150, '50000', 'Active', 'lvl4', 'Motorbike');


-- -----------------------------------------------------------------------------
-- A coupon to exercise the redemption path. expiry is in MINUTES.
-- -----------------------------------------------------------------------------
INSERT INTO coupons (code, amount, expiry, status) VALUES
('WELCOME50', '50',  1440, 'Active'),
('EXPIRED',   '100', 1,    'Active');


-- -----------------------------------------------------------------------------
-- Admin account.
--   Panel login:  admin@sanderson.local / admin123
-- admin/login.php authenticates against `users`, then requires a matching
-- `admins` row. Both are needed.
--
-- The permissions string must contain [edit] or the Phase 1.1 guard will
-- (correctly) reject every action endpoint.
-- -----------------------------------------------------------------------------
-- passwrd below is the digest of 'admin123'.
INSERT INTO users (ID, email, phone, passwrd, status, upline, name, username, role, country)
VALUES (1, 'admin@sanderson.local', '254700000001', '$2y$10$gmzfCnzbNS4sAY2w0qfMuOfxhQUVwYH..vu/gQTCgWe43FIYBd4Y2', 'Active', 0, 'Site Admin', 'admin', 'admin', '254');

INSERT INTO wallets (userID) VALUES (1);

-- admins.passwrd is vestigial -- admin/login.php authenticates against
-- users.passwrd. It is seeded consistently anyway so nothing reads a blank.
INSERT INTO admins (userID, roles, permissions, status, username, name, email, phone)
VALUES (1, 'superadmin', '[view][edit][add][finance]', 'Active', 'admin', 'Site Admin', 'admin@sanderson.local', '254700000001');


-- -----------------------------------------------------------------------------
-- A small referral tree, so the three-level commission walk and the team page
-- have something real to render.
--
--   2  Grace          (no upline)
--   3  Brian    -> 2  (level 1 under Grace)
--   4  Mercy    -> 3  (level 2 under Grace)
--   5  Dennis   -> 4  (level 3 under Grace)
--   6  Faith    -> 2  (second direct downline, still Inactive)
--
-- All test accounts use the password: test1234
-- (digest below is password_hash('test1234', PASSWORD_DEFAULT))
-- -----------------------------------------------------------------------------
INSERT INTO users (ID, email, phone, passwrd, status, upline, name, username, role, country) VALUES
(2, 'grace@example.com',  '254700000002', '$2y$10$HGTayiYgWQNeN3x.6A2EUudWhdg71IjbpqAGaxTm7cgulSYyiLfAa', 'Active',   0, 'Grace Wanjiru', 'grace',  'user', '254'),
(3, 'brian@example.com',  '254700000003', '$2y$10$HGTayiYgWQNeN3x.6A2EUudWhdg71IjbpqAGaxTm7cgulSYyiLfAa', 'Active',   2, 'Brian Otieno',  'brian',  'user', '254'),
(4, 'mercy@example.com',  '254700000004', '$2y$10$HGTayiYgWQNeN3x.6A2EUudWhdg71IjbpqAGaxTm7cgulSYyiLfAa', 'Active',   3, 'Mercy Achieng', 'mercy',  'user', '254'),
(5, 'dennis@example.com', '254700000005', '$2y$10$HGTayiYgWQNeN3x.6A2EUudWhdg71IjbpqAGaxTm7cgulSYyiLfAa', 'Active',   4, 'Dennis Kip',    'dennis', 'user', '254'),
(6, 'faith@example.com',  '254700000006', '$2y$10$HGTayiYgWQNeN3x.6A2EUudWhdg71IjbpqAGaxTm7cgulSYyiLfAa', 'Inactive', 2, 'Faith Njeri',   'faith',  'user', '254');

-- Grace has a configured payout destination; her withdrawal PIN is 1234.
-- The others have none, which is the state `create` is allowed to act on.
INSERT INTO wallets (userID, balance, income, invite_income, today_income, withdrawal_account, withdrawal_name, withdrawal_pin) VALUES
(2, '8400', '1200', '650', '0', '254700000002', 'Grace Wanjiru', '$2y$10$jhVfH1cOBKpbZPmkVgpQXe1sSjE6oSxU9gVC9AN6CDiXMkJHS9M2C'),
(3, '3200', '540',  '120', '0', '', '', ''),
(4, '1500', '180',  '0',   '0', '', '', ''),
(5, '900',  '60',   '0',   '0', '', '', ''),
(6, '0',    '0',    '0',   '0', '', '', '');


-- -----------------------------------------------------------------------------
-- Orders and transactions, so the dashboard totals are not all zero.
-- Grace holds one claimable order (rolls = 1) and one already claimed today.
-- -----------------------------------------------------------------------------
INSERT INTO orders (userID, prodID, status, remaining, duration, returns, type, rolls, amount) VALUES
(2, 2, 'Active',  '24', '30', '360', 'investment', 1, '2000'),
(2, 3, 'Active',  '40', '45', '900', 'investment', 0, '5000'),
(3, 2, 'Active',  '28', '30', '120', 'investment', 1, '1000'),
(4, 1, 'Expired', '0',  '3',  '45',  'investment', 0, '100');

INSERT INTO transactions (userID, type, amount, status, description, fees, trackingID, account, method) VALUES
('2', 'Deposit',        '5000', 'Success',   'TEST-RCPT-0001',              '0',  'INV-101500-201', '254700000002', 'mpesa'),
('2', 'Deposit',        '3000', 'Success',   'TEST-RCPT-0002',              '0',  'INV-101501-202', '254700000002', 'mpesa'),
('2', 'Withdraw',       '1000', 'Success',   'Payout settled',              '50', 'INV-101502-203', '254700000002', 'mpesa'),
('2', 'Product Income', '360',  'Completed', 'Daily return credited.',      '0',  '',               '',             'mpesa'),
('2', 'Commission',     '500',  'Completed', 'Level 1 commission.',         '0',  '',               '',             'mpesa'),
('3', 'Deposit',        '2000', 'Success',   'TEST-RCPT-0003',              '0',  'INV-101503-204', '254700000003', 'mpesa'),
('3', 'Deposit',        '1200', 'Pending',   '',                            '0',  'INV-101504-205', '254700000003', 'mpesa'),
('4', 'Deposit',        '1000', 'Success',   'TEST-RCPT-0004',              '0',  'INV-101505-206', '254700000004', 'mpesa'),
('5', 'Deposit',        '500',  'Failed',    '',                            '0',  'INV-101506-207', '254700000005', 'mpesa');
