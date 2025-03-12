-- Création de la base de données
CREATE DATABASE IF NOT EXISTS omnes_marketplace;
USE omnes_marketplace;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  address VARCHAR(255) NOT NULL,
  city VARCHAR(50) NOT NULL,
  postal_code VARCHAR(20) NOT NULL,
  country VARCHAR(50) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  role ENUM('admin', 'seller', 'buyer') NOT NULL DEFAULT 'buyer',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des demandes de compte vendeur
CREATE TABLE IF NOT EXISTS seller_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  motivation TEXT NOT NULL,
  business_info TEXT NOT NULL,
  admin_comment TEXT,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des catégories
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  description TEXT,
  parent_id INT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Table des produits
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10, 2) NOT NULL,
  stock INT NOT NULL DEFAULT 1,
  image VARCHAR(255) NOT NULL,
  additional_images JSON,
  video VARCHAR(255),
  category_id INT NOT NULL,
  seller_id INT NOT NULL,
  sale_type ENUM('immediate', 'negotiation', 'auction') NOT NULL DEFAULT 'immediate',
  auction_end DATETIME,
  featured BOOLEAN NOT NULL DEFAULT FALSE,
  qualities JSON,
  defects JSON,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Remplacer la table des enchères
-- Ancienne table:
-- CREATE TABLE IF NOT EXISTS bids (
--   id INT AUTO_INCREMENT PRIMARY KEY,
--   product_id INT NOT NULL,
--   user_id INT NOT NULL,
--   amount DECIMAL(10, 2) NOT NULL,
--   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
--   FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
--   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
-- );

-- Nouvelle table avec le nom en français:
CREATE TABLE IF NOT EXISTS encheres (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  amount DECIMAL(10, 2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des négociations
CREATE TABLE IF NOT EXISTS negotiations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  amount DECIMAL(10, 2) NOT NULL,
  message TEXT,
  seller_response TINYINT DEFAULT 0, -- 0: en attente, 1: accepté, 2: refusé
  counter_offer DECIMAL(10, 2),
  response_date DATETIME,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des commandes
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  total_amount DECIMAL(10, 2) NOT NULL,
  shipping_address VARCHAR(255) NOT NULL,
  shipping_city VARCHAR(50) NOT NULL,
  shipping_postal_code VARCHAR(20) NOT NULL,
  shipping_country VARCHAR(50) NOT NULL,
  shipping_phone VARCHAR(20) NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  payment_status ENUM('pending', 'paid', 'failed', 'completed') NOT NULL DEFAULT 'pending',
  order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des articles de commande
CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  price DECIMAL(10, 2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Table des notifications
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  link VARCHAR(255),
  is_read BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des cartes de paiement pour la validation
CREATE TABLE IF NOT EXISTS payment_cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  card_type ENUM('visa', 'mastercard', 'amex', 'paypal') NOT NULL,
  card_number VARCHAR(255) NOT NULL, -- Stocké de manière sécurisée en production
  card_name VARCHAR(100) NOT NULL,
  expiry_date VARCHAR(7) NOT NULL, -- Format MM/YY
  cvv VARCHAR(255) NOT NULL, -- Stocké de manière sécurisée en production
  user_id INT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insertion de données de test

-- Administrateur
INSERT INTO users (username, email, password, first_name, last_name, address, city, postal_code, country, phone, role)
VALUES ('admin', 'admin@omnesmarketplace.fr', '$2y$10$8Gg0xwrQlvZNaE4ivEH.9uXWLgX0lR/Rj1XcO/AJiQJ4TdJ9y0oOi', 'Admin', 'User', '10 Rue Sextius Michel', 'Paris', '75015', 'France', '+33123456789', 'admin');

-- Vendeurs
INSERT INTO users (username, email, password, first_name, last_name, address, city, postal_code, country, phone, role)
VALUES 
('vendeur1', 'vendeur1@omnesmarketplace.fr', '$2y$10$8Gg0xwrQlvZNaE4ivEH.9uXWLgX0lR/Rj1XcO/AJiQJ4TdJ9y0oOi', 'Jean', 'Dupont', '15 Rue de la Paix', 'Paris', '75001', 'France', '+33123456780', 'seller'),
('vendeur2', 'vendeur2@omnesmarketplace.fr', '$2y$10$8Gg0xwrQlvZNaE4ivEH.9uXWLgX0lR/Rj1XcO/AJiQJ4TdJ9y0oOi', 'Marie', 'Martin', '25 Avenue des Champs-Élysées', 'Paris', '75008', 'France', '+33123456781', 'seller');

-- Acheteurs
INSERT INTO users (username, email, password, first_name, last_name, address, city, postal_code, country, phone, role)
VALUES 
('acheteur1', 'acheteur1@omnesmarketplace.fr', '$2y$10$8Gg0xwrQlvZNaE4ivEH.9uXWLgX0lR/Rj1XcO/AJiQJ4TdJ9y0oOi', 'Pierre', 'Durand', '5 Rue du Commerce', 'Lyon', '69002', 'France', '+33123456782', 'buyer'),
('acheteur2', 'acheteur2@omnesmarketplace.fr', '$2y$10$8Gg0xwrQlvZNaE4ivEH.9uXWLgX0lR/Rj1XcO/AJiQJ4TdJ9y0oOi', 'Sophie', 'Lefebvre', '10 Rue de la République', 'Marseille', '13001', 'France', '+33123456783', 'buyer');

-- Catégories
INSERT INTO categories (name, description)
VALUES 
('Électronique', 'Produits électroniques, gadgets et accessoires'),
('Mode', 'Vêtements, chaussures et accessoires de mode'),
('Maison', 'Meubles, décoration et articles pour la maison'),
('Sports & Loisirs', 'Équipements sportifs et articles de loisirs'),
('Livres & Médias', 'Livres, films, musique et autres médias');

-- Sous-catégories
INSERT INTO categories (name, description, parent_id)
VALUES 
('Smartphones', 'Téléphones mobiles et accessoires', 1),
('Ordinateurs', 'Ordinateurs portables et de bureau', 1),
('Vêtements Homme', 'Vêtements pour homme', 2),
('Vêtements Femme', 'Vêtements pour femme', 2),
('Meubles', 'Meubles pour la maison', 3),
('Décoration', 'Articles de décoration', 3),
('Équipements de fitness', 'Matériel de sport et fitness', 4),
('Jeux de société', 'Jeux de société et puzzles', 4),
('Livres', 'Livres imprimés et numériques', 5),
('Films & Séries', 'DVDs, Blu-rays et contenus numériques', 5);

-- Produits
INSERT INTO products (name, description, price, stock, image, category_id, seller_id, sale_type, featured, qualities, defects)
VALUES 
('iPhone 13 Pro', 'Smartphone Apple iPhone 13 Pro 128Go Graphite', 999.99, 10, '/images/products/iphone13pro.jpg', 6, 2, 'immediate', TRUE, '["Écran Super Retina XDR", "Puce A15 Bionic", "Triple appareil photo"]', '[]'),
('MacBook Pro 14"', 'Ordinateur portable Apple MacBook Pro 14" M1 Pro 512Go', 1999.99, 5, '/images/products/macbookpro.jpg', 7, 2, 'immediate', TRUE, '["Puce M1 Pro", "Écran Liquid Retina XDR", "Autonomie jusqu\'à 17 heures"]', '[]'),
('Chemise en lin', 'Chemise en lin pour homme, coupe régulière', 49.99, 20, '/images/products/chemise.jpg', 8, 3, 'negotiation', FALSE, '["100% lin", "Respirant", "Facile à entretenir"]', '[]'),
('Robe d\'été', 'Robe légère pour l\'été, motif floral', 39.99, 15, '/images/products/robe.jpg', 9, 3, 'immediate', FALSE, '["Tissu léger", "Motif floral", "Confortable"]', '[]'),
('Canapé d\'angle', 'Canapé d\'angle convertible en tissu gris', 699.99, 3, '/images/products/canape.jpg', 10, 2, 'auction', TRUE, '["Convertible", "Tissu résistant", "Rangement intégré"]', '["Petite tache sur l\'accoudoir"]'),
('Lampe de chevet', 'Lampe de chevet design en métal et bois', 59.99, 8, '/images/products/lampe.jpg', 11, 3, 'immediate', FALSE, '["Design moderne", "Matériaux de qualité", "Ampoule LED incluse"]', '[]'),
('Tapis de yoga', 'Tapis de yoga antidérapant 6mm', 29.99, 25, '/images/products/tapis.jpg', 12, 2, 'immediate', FALSE, '["Antidérapant", "Épaisseur 6mm", "Facile à nettoyer"]', '[]'),
('Monopoly', 'Jeu de société Monopoly Classique', 24.99, 12, '/images/products/monopoly.jpg', 13, 3, 'negotiation', FALSE, '["Jeu complet", "Édition classique", "Pour toute la famille"]', '["Boîte légèrement abîmée"]'),
('Harry Potter - Coffret', 'Coffret intégral des 7 tomes de Harry Potter', 79.99, 7, '/images/products/harrypotter.jpg', 14, 2, 'immediate', TRUE, '["Collection complète", "Édition spéciale", "Comme neuf"]', '[]'),
('Intégrale Game of Thrones', 'Coffret DVD intégral des 8 saisons de Game of Thrones', 99.99, 4, '/images/products/got.jpg', 15, 3, 'auction', FALSE, '["Édition collector", "Bonus exclusifs", "Sous-titres français"]', '[]');

-- Modifier les insertions de données de test pour utiliser la nouvelle table
-- Remplacer:
-- INSERT INTO bids (product_id, user_id, amount, created_at)
-- VALUES 
-- (5, 4, 720.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
-- (5, 5, 750.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),
-- (5, 4, 780.00, DATE_SUB(NOW(), INTERVAL 12 HOUR)),
-- (10, 5, 110.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
-- (10, 4, 120.00, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Par:
INSERT INTO encheres (product_id, user_id, amount, created_at)
VALUES 
(5, 4, 720.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 5, 750.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 4, 780.00, DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(10, 5, 110.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(10, 4, 120.00, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Négociations
INSERT INTO negotiations (product_id, user_id, amount, message, seller_response, counter_offer, response_date, created_at)
VALUES 
(3, 4, 40.00, 'Serait-il possible de faire un prix pour 2 chemises ?', 1, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(8, 5, 20.00, 'Je suis intéressé mais le prix me semble un peu élevé.', 2, 22.50, DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Commandes
INSERT INTO orders (user_id, total_amount, shipping_address, shipping_city, shipping_postal_code, shipping_country, shipping_phone, payment_method, payment_status, order_status)
VALUES 
(4, 1049.98, '5 Rue du Commerce', 'Lyon', '69002', 'France', '+33123456782', 'visa', 'paid', 'shipped'),
(5, 139.98, '10 Rue de la République', 'Marseille', '13001', 'France', '+33123456783', 'mastercard', 'paid', 'delivered');

-- Articles de commande
INSERT INTO order_items (order_id, product_id, quantity, price)
VALUES 
(1, 1, 1, 999.99),
(1, 7, 1, 29.99),
(2, 4, 1, 39.99),
(2, 6, 1, 59.99),
(2, 7, 1, 29.99);

-- Notifications
INSERT INTO notifications (user_id, message, link, is_read)
VALUES 
(2, 'Nouvelle commande pour votre produit iPhone 13 Pro', 'seller/orders.php', FALSE),
(2, 'Nouvelle commande pour votre produit Tapis de yoga', 'seller/orders.php', FALSE),
(3, 'Nouvelle commande pour votre produit Robe d\'été', 'seller/orders.php', TRUE),
(3, 'Nouvelle commande pour votre produit Lampe de chevet', 'seller/orders.php', TRUE),
(4, 'Votre commande #1 a été expédiée', 'order_details.php?id=1', FALSE),
(5, 'Votre commande #2 a été livrée', 'order_details.php?id=2', TRUE);

-- Insérer quelques cartes de test
-- Note: En production, ces informations seraient chiffrées
INSERT INTO payment_cards (card_type, card_number, card_name, expiry_date, cvv, user_id) VALUES
('visa', '4111111111111111', 'JOHN DOE', '12/25', '123', 4),
('mastercard', '5555555555554444', 'JANE SMITH', '10/24', '321', 5),
('amex', '378282246310005', 'ROBERT BROWN', '06/26', '4321', NULL),
('paypal', '4222222222222', 'ALICE JOHNSON', '08/23', '567', NULL);

-- Ajouter quelques demandes de test pour les comptes vendeur
INSERT INTO seller_requests (user_id, motivation, business_info, status, created_at) 
VALUES 
(4, 'Je souhaite vendre des produits électroniques reconditionnés. J\'ai une expérience de 5 ans dans ce domaine et je pense pouvoir apporter une valeur ajoutée à la plateforme.', 'Je possède une petite entreprise de reconditionnement de smartphones et ordinateurs. Je garantis tous mes produits pendant 1 an.', 'pending', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 'Je suis artisan et je crée des bijoux faits main. Je souhaite élargir ma clientèle en vendant sur votre plateforme.', 'Je fabrique des bijoux en argent et en pierres semi-précieuses depuis 3 ans. Chaque pièce est unique et fabriquée avec soin.', 'approved', DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Note: Le mot de passe haché pour tous les utilisateurs est 'password123'

