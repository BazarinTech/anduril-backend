-- =============================================================================
--  REQUIRED SEED -- SAFE FOR PRODUCTION
-- =============================================================================
--  The platform settings row, and nothing else.
--
--  This is separated from db/seed.sql because that file is *development* data:
--  it creates two admin accounts and five users whose passwords are written in
--  its own comments (admin123, 445566gh, test1234). Loading it into a live
--  environment would put known credentials on a system that moves money.
--
--  The row below is not optional. Every read is
--  `$query->select('controls')` followed by `[0]` with no empty check, so a
--  controls-less database breaks the dashboard, deposits, withdrawals,
--  transfers and the referral commission walk.
--
--      mysql -u root -p my_database < db/seed-required.sql
--
--  bin/db-init.php applies this automatically when the table is empty.
-- =============================================================================

INSERT INTO controls
    (minWith, minDep, level1, level2, level3, transactionType, transactionAccount, withFee, minTransfer, tranFee,
     claimWindowOn, claimOpensAt, claimClosesAt, claimsPerDay)
VALUES
    ('200', '100', '10', '5', '3', 'Auto', '183', '5', '500', 2,
     -- Claiming opens at 07:00 and stays open until it opens again, one claim
     -- per investment per day. Changed on the admin Platform Control page.
     '1', '07:00:00', NULL, 1);
