-- ============================================================
--  Avianna's Inland Resort — Schema Repair Migration
--  Run this ONCE against your live database (phpMyAdmin > SQL tab,
--  or `mysql -u USER -p DBNAME < migration_fix.sql`).
--
--  Why: the live DB (avianna_resort (2).sql) drifted from what the
--  PHP code actually writes. This migration brings the schema back
--  in line so booking, cancellation, announcements, and the
--  reception portal all work without errors.
-- ============================================================

-- 1) bookings.pax is stored by every form as a range string
--    ("1-6", "11-15", etc), not a number — the column was an INT.
ALTER TABLE bookings MODIFY pax VARCHAR(20) NOT NULL DEFAULT '1-6';

-- 2) bookings.num_guests / price_per_night are leftover NOT NULL
--    columns with no default that nothing in the app populates.
--    Giving them defaults stops every booking insert from failing.
ALTER TABLE bookings MODIFY num_guests INT NOT NULL DEFAULT 1;
ALTER TABLE bookings MODIFY price_per_night INT NOT NULL DEFAULT 0;

-- 3) announcements.content is NOT NULL with no default and nothing
--    reads it (the app uses `message`) — give it a safe default.
ALTER TABLE announcements MODIFY content TEXT NOT NULL DEFAULT '';

-- 4) deleted_bookings is missing columns the archiving code needs
--    (guest contact number, cottage add-on, party size, amount paid)
--    so cancellations currently fail outright.
ALTER TABLE deleted_bookings ADD COLUMN IF NOT EXISTS contact VARCHAR(50) DEFAULT NULL AFTER email;
ALTER TABLE deleted_bookings ADD COLUMN IF NOT EXISTS cottage_type VARCHAR(100) DEFAULT NULL AFTER room_type;
ALTER TABLE deleted_bookings ADD COLUMN IF NOT EXISTS pax VARCHAR(20) DEFAULT NULL AFTER cottage_type;
ALTER TABLE deleted_bookings ADD COLUMN IF NOT EXISTS total_price DECIMAL(10,2) DEFAULT 0.00 AFTER payment_method;

-- 5) Add a 'staff' role so reception desk accounts are distinct from
--    admin accounts (the login page now routes staff to the
--    reception portal instead of the admin dashboard).
ALTER TABLE users MODIFY role ENUM('admin','staff','user') NOT NULL DEFAULT 'user';

-- 6) Example: create a reception staff login.
--    Generate a real hash first: open admin/hash.php after editing
--    the password inside it, copy the output, then run:
--
--    INSERT INTO users (username, password, role)
--    VALUES ('reception1', '<paste bcrypt hash here>', 'staff');
