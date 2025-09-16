-- MySQL schema for RecipeDB - Complete Version
-- Create database (optional; adjust name as needed)
CREATE DATABASE IF NOT EXISTS recipedb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE recipedb;

-- Users table
CREATE TABLE IF NOT EXISTS users (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(100) NOT NULL,
	email VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	avatar_path VARCHAR(500) NULL,
	bio TEXT NULL,
	website_url VARCHAR(255) NULL,
	instagram_url VARCHAR(255) NULL,
	twitter_url VARCHAR(255) NULL,
	facebook_url VARCHAR(255) NULL,
	tiktok_url VARCHAR(255) NULL,
	youtube_url VARCHAR(255) NULL,
	email_verified_at TIMESTAMP NULL DEFAULT NULL,
	otp_code VARCHAR(6) NULL,
	otp_expires_at TIMESTAMP NULL DEFAULT NULL,
	password_reset_token VARCHAR(255) NULL,
	password_reset_expires_at TIMESTAMP NULL DEFAULT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recipe categories table for better organization
CREATE TABLE IF NOT EXISTS recipe_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    color VARCHAR(7) NULL DEFAULT '#3B82F6' COMMENT 'Hex color for category display',
    icon VARCHAR(50) NULL DEFAULT 'fa-utensils' COMMENT 'Font Awesome icon class',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recipes table (with portions and category)
CREATE TABLE IF NOT EXISTS recipes (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	title VARCHAR(200) NOT NULL,
	description TEXT NOT NULL,
	difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy',
	duration_minutes INT UNSIGNED NOT NULL DEFAULT 0,
	portions INT UNSIGNED NULL DEFAULT NULL COMMENT 'Number of portions this recipe serves',
	category VARCHAR(100) NULL DEFAULT NULL COMMENT 'Recipe category',
	likes_count INT UNSIGNED NOT NULL DEFAULT 0,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT fk_recipes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_recipes_category FOREIGN KEY (category) REFERENCES recipe_categories(name) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recipe images (for carousel)
CREATE TABLE IF NOT EXISTS recipe_images (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	recipe_id INT UNSIGNED NOT NULL,
	file_path VARCHAR(500) NOT NULL,
	sort_order INT NOT NULL DEFAULT 0,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_recipe_images_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
	INDEX idx_recipe_images_recipe (recipe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Likes (one per user per recipe)
CREATE TABLE IF NOT EXISTS recipe_likes (
	recipe_id INT UNSIGNED NOT NULL,
	user_id INT UNSIGNED NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (recipe_id, user_id),
	CONSTRAINT fk_likes_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
	CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Favorites table for recipes
CREATE TABLE IF NOT EXISTS recipe_favorites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    recipe_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_recipe (user_id, recipe_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comments on recipes
CREATE TABLE IF NOT EXISTS recipe_comments (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	recipe_id INT UNSIGNED NOT NULL,
	user_id INT UNSIGNED NOT NULL,
	content TEXT NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_comments_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
	CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	INDEX idx_comments_recipe (recipe_id),
	INDEX idx_comments_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recipe Ingredients
CREATE TABLE IF NOT EXISTS recipe_ingredients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(10, 2) NULL,
    unit VARCHAR(50) NULL,
    name VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ingredients_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recipe Steps
CREATE TABLE IF NOT EXISTS recipe_steps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_steps_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User follows
CREATE TABLE IF NOT EXISTS follows (
    follower_id INT UNSIGNED NOT NULL,
    followee_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followee_id),
    CONSTRAINT fk_follows_follower FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_follows_followee FOREIGN KEY (followee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO recipe_categories (name, description, color, icon, sort_order) VALUES
('Hauptgericht', 'Fleisch, Fisch, vegetarische Hauptgerichte', '#EF4444', 'fa-drumstick-bite', 1),
('Vorspeise', 'Suppen, Salate, Antipasti', '#10B981', 'fa-leaf', 2),
('Dessert', 'Kuchen, Eis, Süßspeisen', '#F59E0B', 'fa-ice-cream', 3),
('Frühstück', 'Müsli, Eier, Brötchen', '#8B5CF6', 'fa-egg', 4),
('Snack', 'Kleine Gerichte, Fingerfood', '#06B6D4', 'fa-cookie-bite', 5),
('Getränk', 'Cocktails, Smoothies, Tee', '#84CC16', 'fa-wine-glass', 6),
('Backen', 'Brot, Kuchen, Gebäck', '#F97316', 'fa-birthday-cake', 7),
('Beilage', 'Reis, Kartoffeln, Gemüse', '#22C55E', 'fa-carrot', 8);

-- Additional indexes for better performance
CREATE INDEX idx_favorites_user ON recipe_favorites(user_id);
CREATE INDEX idx_favorites_recipe ON recipe_favorites(recipe_id);
CREATE INDEX idx_follows_follower ON follows(follower_id);
CREATE INDEX idx_follows_followee ON follows(followee_id);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_recipes_user ON recipes(user_id);
CREATE INDEX idx_recipes_created ON recipes(created_at);
CREATE INDEX idx_recipes_category ON recipes(category);
CREATE INDEX idx_recipes_portions ON recipes(portions);
CREATE INDEX idx_categories_sort ON recipe_categories(sort_order);
CREATE INDEX idx_users_email ON users(email);
