-- ============================================
-- KeySynx Database Schema (v2)
-- Web-Based Music Analysis Platform — Key & BPM
-- Now includes: section-based BPM/key timelines,
-- contributor reputation, confidence score support,
-- and contributor feedback (comments).
-- Run this in phpMyAdmin (XAMPP) or via:
--   mysql -u root -p < schema.sql
-- ============================================

CREATE DATABASE IF NOT EXISTS keysynx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE keysynx;

-- ---------- USERS ----------
-- reputation_points drives the Contributor Reputation System.
-- role_tier is NOT stored — it's derived from reputation_points
-- at read time (see api/db.php::reputationTier()), so thresholds
-- can change without a data migration.
CREATE TABLE users (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    username          VARCHAR(50)  NOT NULL UNIQUE,
    email             VARCHAR(120) NOT NULL UNIQUE,
    password_hash     VARCHAR(255) NOT NULL,
    role              ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    reputation_points INT NOT NULL DEFAULT 0,
    avatar_initials   VARCHAR(4)   DEFAULT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- ALBUMS ----------
CREATE TABLE albums (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(150) NOT NULL,
    artist       VARCHAR(150) NOT NULL,
    release_year INT DEFAULT NULL
) ENGINE=InnoDB;

-- ---------- SONGS ----------
CREATE TABLE songs (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    album_id         INT DEFAULT NULL,
    title            VARCHAR(150) NOT NULL,
    artist           VARCHAR(150) NOT NULL,
    bpm              DECIMAL(6,2) DEFAULT NULL,      -- NULL allowed: a few tracks are rubato/unsteady with no fixed tempo
    musical_key      VARCHAR(30)  NOT NULL,
    camelot_code     VARCHAR(3)   DEFAULT NULL,      -- derived from musical_key, see api/camelot.php
    time_signature   VARCHAR(10)  DEFAULT NULL,
    has_variation    BOOLEAN      NOT NULL DEFAULT 0,
    section_keys     JSON         DEFAULT NULL,       -- legacy quick-view, e.g. [{"section":"Rest","key":"Bb Minor"}]
    footnote         VARCHAR(255) DEFAULT NULL,
    youtube_url      VARCHAR(255) DEFAULT NULL,       -- official YouTube link only — no audio file storage (proposal limitation #2)
    tags             VARCHAR(255) DEFAULT NULL,
    submitted_by     INT DEFAULT NULL,
    status           ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    upvotes          INT NOT NULL DEFAULT 0,
    downvotes        INT NOT NULL DEFAULT 0,
    verified_at      TIMESTAMP NULL DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE SET NULL,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_search (title, artist),
    INDEX idx_bpm (bpm),
    INDEX idx_key (musical_key),
    INDEX idx_camelot (camelot_code)
) ENGINE=InnoDB;

-- ---------- SONG SECTIONS ----------
-- Powers "Section-Based Key/BPM Transitions" (Core Feature #3).
-- bpm/musical_key are NULLABLE — leave NULL when a section doesn't
-- deviate from the song's overall bpm/key (saves re-typing repeats).
CREATE TABLE song_sections (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    song_id      INT NOT NULL,
    section_name VARCHAR(60) NOT NULL,      -- e.g. 'Intro', 'Verse & Bridge', 'Rest'
    order_index  INT NOT NULL DEFAULT 0,
    bpm          DECIMAL(6,2) DEFAULT NULL,
    musical_key  VARCHAR(30)  DEFAULT NULL,
    camelot_code VARCHAR(3)   DEFAULT NULL,
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    INDEX idx_song (song_id, order_index)
) ENGINE=InnoDB;

-- ---------- VOTES ----------
CREATE TABLE votes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    song_id     INT NOT NULL,
    user_id     INT NOT NULL,
    vote_type   ENUM('up','down') NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote (song_id, user_id)
) ENGINE=InnoDB;

-- ---------- CONTRIBUTOR FEEDBACK (comments) ----------
-- Powers the "contributor feedback" part of Community Validation (Core Feature #4).
CREATE TABLE song_comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    song_id     INT NOT NULL,
    user_id     INT NOT NULL,
    comment     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- REPUTATION LOG ----------
-- Audit trail for the Contributor Reputation System's point events:
--   Correct submission  +10  (awarded when a new analysis is submitted)
--   Verified analysis   +15  (awarded when status flips to 'verified')
--   Rejected analysis    -5  (awarded when status flips to 'rejected')
CREATE TABLE reputation_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    song_id     INT DEFAULT NULL,
    points      INT NOT NULL,
    reason      VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- ADMIN MODERATION LOG ----------
CREATE TABLE moderation_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    song_id     INT NOT NULL,
    admin_id    INT NOT NULL,
    action      ENUM('approved','rejected','edited','deleted','role_changed') NOT NULL,
    notes       VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- TRIGGERS
-- ============================================
DELIMITER //

-- +10 reputation the moment a submission is created.
CREATE TRIGGER trg_submission_reward
AFTER INSERT ON songs
FOR EACH ROW
BEGIN
    IF NEW.submitted_by IS NOT NULL THEN
        UPDATE users SET reputation_points = reputation_points + 10 WHERE id = NEW.submitted_by;
        INSERT INTO reputation_log (user_id, song_id, points, reason)
        VALUES (NEW.submitted_by, NEW.id, 10, 'Correct submission');
    END IF;
END//

-- Auto-verify at net upvotes >= 5, and award/deduct reputation on any
-- status transition (this fires again, once, for the nested UPDATE
-- below — that second pass is what actually grants the +15).
CREATE TRIGGER trg_song_status_change
AFTER UPDATE ON songs
FOR EACH ROW
BEGIN
    IF (NEW.upvotes - NEW.downvotes) >= 5 AND NEW.status = 'pending' THEN
        UPDATE songs SET status = 'verified', verified_at = NOW() WHERE id = NEW.id;
    END IF;

    IF NEW.status = 'verified' AND OLD.status <> 'verified' AND NEW.submitted_by IS NOT NULL THEN
        UPDATE users SET reputation_points = reputation_points + 15 WHERE id = NEW.submitted_by;
        INSERT INTO reputation_log (user_id, song_id, points, reason)
        VALUES (NEW.submitted_by, NEW.id, 15, 'Verified analysis');
    END IF;

    IF NEW.status = 'rejected' AND OLD.status <> 'rejected' AND NEW.submitted_by IS NOT NULL THEN
        UPDATE users SET reputation_points = reputation_points - 5 WHERE id = NEW.submitted_by;
        INSERT INTO reputation_log (user_id, song_id, points, reason)
        VALUES (NEW.submitted_by, NEW.id, -5, 'Rejected analysis');
    END IF;
END//

DELIMITER ;

-- ============================================
-- SEED DATA
-- ============================================
INSERT INTO users (username, email, password_hash, role, reputation_points, avatar_initials) VALUES
('admin',     'admin@keysynx.com',     '$2y$10$examplehashadmin', 'admin', 0,   'AD'),
('djkurt',    'kurt@keysynx.com',      '$2y$10$examplehashuser1', 'user',  35,  'DK'),
('romanmix',  'roman@keysynx.com',     '$2y$10$examplehashuser2', 'user',  62,  'RM'),
('community', 'community@keysynx.com', '$2y$10$examplehashcomm',  'user',  120, 'CM'),
('julianav',  'juliana@keysynx.com',   '$2y$10$examplehashuser4', 'user',  48,  'JV'),
('keannahmh', 'keannah@keysynx.com',   '$2y$10$examplehashuser5', 'user',  27,  'KM'),
('mixmaster', 'mixmaster@keysynx.com', '$2y$10$examplehashuser6', 'user',  91,  'MM'),
('djsynx',    'djsynx@keysynx.com',    '$2y$10$examplehashuser7', 'user',  15,  'DS'),
('harmonyfan','harmony@keysynx.com',   '$2y$10$examplehashuser8', 'user',  8,   'HF'),
('beatlab',   'beatlab@keysynx.com',   '$2y$10$examplehashuser9', 'user',  56,  'BL');

INSERT INTO albums (title, artist, release_year) VALUES
('Eternal Sunshine', 'Ariana Grande', 2024);

-- NOTE: inserting these rows fires trg_submission_reward (+10 each to user id 4 "community").
INSERT INTO songs (album_id, title, artist, bpm, musical_key, camelot_code, time_signature, has_variation, section_keys, footnote, submitted_by, status, upvotes, downvotes) VALUES
(1, 'intro (end of the world)', 'Ariana Grande', 85.00, 'Bb Major', '6B', NULL, 0, NULL,
 'Borrows Gmaj & Dmaj throughout', 4, 'verified', 12, 0),

(1, 'bye', 'Ariana Grande', 110.00, 'D Minor', '7A', NULL, 1,
 '[{"section":"Bridge","key":"F Minor"},{"section":"Rest","key":"D Minor"}]',
 NULL, 4, 'verified', 10, 0),

(1, "don't wanna break up again", 'Ariana Grande', 97.00, 'F Major', '7B', NULL, 0, NULL,
 'Borrows B7 throughout; bridge borrows Abmaj & F#maj', 4, 'verified', 8, 0),

(1, 'eternal sunshine', 'Ariana Grande', 80.00, 'A Major', '11B', NULL, 0, NULL,
 'Instrumental subtly borrows Dbmaj throughout', 4, 'verified', 15, 0),

(1, 'supernatural', 'Ariana Grande', 153.00, 'Ab Major', '4B', NULL, 0, NULL,
 NULL, 4, 'verified', 11, 0),

(1, 'true story', 'Ariana Grande', 138.00, 'A Minor', '8A', NULL, 0, NULL,
 'Borrows Emaj throughout; would work well as harmonic minor', 4, 'pending', 4, 1),

(1, 'the boy is mine', 'Ariana Grande', 98.00, 'G Minor', '6A', NULL, 0, NULL,
 'Quick ritard before the chorus; borrows Dmaj at end of bridge', 4, 'verified', 9, 0),

(1, 'yes, and?', 'Ariana Grande', 119.00, 'Bb Minor', '3A', NULL, 1,
 '[{"section":"Verse & Bridge","key":"Bb Mixolydian"},{"section":"Rest","key":"Bb Minor"}]',
 NULL, 4, 'verified', 18, 0),

(1, "we can't be friends (wait for your love)", 'Ariana Grande', 116.00, 'F Major', '7B', NULL, 0, NULL,
 NULL, 4, 'verified', 21, 0),

(1, 'i wish i hated you', 'Ariana Grande', 98.25, 'G Major', '9B', '6/8', 0, NULL,
 NULL, 4, 'pending', 5, 0),

(1, 'imperfect for you', 'Ariana Grande', 75.00, 'E Major', '12B', '6/8', 0, NULL,
 'Borrows Fmaj, Bbm, F#maj', 4, 'pending', 3, 0),

(1, 'ordinary things', 'Ariana Grande', 115.00, 'Db Major', '3B', NULL, 0, NULL,
 'Borrows G#m throughout', 4, 'verified', 13, 0);

-- Section timelines for the two tracks that actually have section-specific data
INSERT INTO song_sections (song_id, section_name, order_index, bpm, musical_key, camelot_code) VALUES
(2, 'Bridge', 1, NULL, 'F Minor', '4A'),
(2, 'Rest', 2, NULL, 'D Minor', '7A'),
(8, 'Verse & Bridge', 1, NULL, 'Bb Mixolydian', NULL),
(8, 'Rest', 2, NULL, 'Bb Minor', '3A');

-- Sample contributor feedback
INSERT INTO song_comments (song_id, user_id, comment) VALUES
(8, 2, 'Can confirm the Bb Mixolydian shift in the verse — the bassline gives it away.'),
(6, 3, 'I hear this more as harmonic minor in the bridge, matches the footnote.');
