-- ============================================
-- KeySynx — Migration: v3 -> v4
-- Run this INSTEAD of re-importing schema.sql if you
-- already have data you don't want to lose (e.g. real
-- submissions/votes/comments from testing).
-- If you're fine wiping and starting fresh, just re-import
-- schema.sql instead and skip this file.
-- ============================================

USE keysynx;

-- Allow tracks with no fixed tempo (a few rubato/unsteady tracks in the
-- new artist data have no single BPM)
ALTER TABLE songs MODIFY bpm DECIMAL(6,2) DEFAULT NULL;

-- YouTube link support (link only — never store the audio file itself)
ALTER TABLE songs ADD COLUMN youtube_url VARCHAR(255) DEFAULT NULL AFTER footnote;

-- Top up to 10 demo users (skipped automatically if usernames already exist)
INSERT IGNORE INTO users (username, email, password_hash, role, reputation_points, avatar_initials) VALUES
('julianav',  'juliana@keysynx.com',   '$2y$10$examplehashuser4', 'user', 48, 'JV'),
('keannahmh', 'keannah@keysynx.com',   '$2y$10$examplehashuser5', 'user', 27, 'KM'),
('mixmaster', 'mixmaster@keysynx.com', '$2y$10$examplehashuser6', 'user', 91, 'MM'),
('djsynx',    'djsynx@keysynx.com',    '$2y$10$examplehashuser7', 'user', 15, 'DS'),
('harmonyfan','harmony@keysynx.com',   '$2y$10$examplehashuser8', 'user', 8,  'HF'),
('beatlab',   'beatlab@keysynx.com',   '$2y$10$examplehashuser9', 'user', 56, 'BL');
