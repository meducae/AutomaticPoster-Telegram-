-- Database Setup for Movie Bot
-- Run this script in your MySQL instance to prepare the database.

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS movie_bot_db
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE movie_bot_db;

-- Settings table for tracking the last movie ID safely
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    last_movie_id INT NOT NULL
);

-- Insert the initial value for the unique movie ID as requested
-- If the row exists, this will prevent duplicates (though typically we run this once).
INSERT INTO settings (id, last_movie_id) VALUES (1, 1356) 
ON DUPLICATE KEY UPDATE last_movie_id = last_movie_id;
