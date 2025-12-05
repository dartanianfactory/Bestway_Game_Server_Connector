CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    level INT DEFAULT 1,
    experience INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    item_type ENUM('weapon', 'armor', 'consumable', 'misc') NOT NULL,
    rarity ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',
    price DECIMAL(10, 2) NOT NULL,
    weight DECIMAL(5, 2) DEFAULT 0.00,
    max_stack INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    equipped BOOLEAN DEFAULT FALSE,
    durability DECIMAL(5, 2) DEFAULT 100.00,
    acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_item_id (item_id),
    UNIQUE KEY unique_user_item (user_id, item_id)
);

INSERT INTO users (username, email, password_hash, balance, level, experience) VALUES
('john_doe', 'john@example.com', '$2y$10$examplehash1', 1500.50, 5, 1250),
('jane_smith', 'jane@example.com', '$2y$10$examplehash2', 2750.00, 8, 3200),
('alex_wong', 'alex@example.com', '$2y$10$examplehash3', 500.75, 3, 450),
('sara_connor', 'sara@example.com', '$2y$10$examplehash4', 10000.00, 15, 12500),
('mike_brown', 'mike@example.com', '$2y$10$examplehash5', 250.25, 2, 150);

INSERT INTO items (name, description, item_type, rarity, price, weight, max_stack) VALUES
('Steel Sword', 'A sharp steel sword for close combat', 'weapon', 'uncommon', 250.00, 3.5, 1),
('Health Potion', 'Restores 50 health points', 'consumable', 'common', 25.00, 0.5, 20),
('Leather Armor', 'Light armor made from leather', 'armor', 'common', 150.00, 8.0, 1),
('Magic Wand', 'A wand that casts basic spells', 'weapon', 'rare', 500.00, 1.0, 1),
('Gold Coin', 'Currency used in the game', 'misc', 'common', 1.00, 0.01, 999),
('Dragon Scale', 'Rare material from a dragon', 'misc', 'epic', 1000.00, 0.2, 10),
('Invisibility Cloak', 'Makes the wearer invisible', 'armor', 'legendary', 5000.00, 2.0, 1),
('Mana Elixir', 'Restores 100 mana points', 'consumable', 'uncommon', 75.00, 0.3, 10),
('Iron Shield', 'A sturdy shield for defense', 'weapon', 'common', 120.00, 5.0, 1),
('Treasure Map', 'Leads to hidden treasures', 'misc', 'rare', 300.00, 0.1, 1);

INSERT INTO user_items (user_id, item_id, quantity, equipped, durability) VALUES
(1, 1, 1, TRUE, 85.00),  -- John has Steel Sword equipped
(1, 2, 5, FALSE, 100.00), -- John has 5 Health Potions
(1, 3, 1, TRUE, 92.00),  -- John has Leather Armor equipped
(2, 4, 1, TRUE, 100.00), -- Jane has Magic Wand equipped
(2, 5, 250, FALSE, 100.00), -- Jane has 250 Gold Coins
(2, 6, 2, FALSE, 100.00), -- Jane has 2 Dragon Scales
(3, 2, 10, FALSE, 100.00), -- Alex has 10 Health Potions
(3, 9, 1, TRUE, 78.00),  -- Alex has Iron Shield equipped
(4, 7, 1, TRUE, 100.00), -- Sara has Invisibility Cloak equipped
(4, 8, 3, FALSE, 100.00), -- Sara has 3 Mana Elixirs
(5, 10, 1, FALSE, 100.00); -- Mike has Treasure Map
