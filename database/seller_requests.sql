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

-- Ajouter quelques demandes de test
INSERT INTO seller_requests (user_id, motivation, business_info, status, created_at) 
VALUES 
(4, 'Je souhaite vendre des produits électroniques reconditionnés. J\'ai une expérience de 5 ans dans ce domaine et je pense pouvoir apporter une valeur ajoutée à la plateforme.', 'Je possède une petite entreprise de reconditionnement de smartphones et ordinateurs. Je garantis tous mes produits pendant 1 an.', 'pending', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 'Je suis artisan et je crée des bijoux faits main. Je souhaite élargir ma clientèle en vendant sur votre plateforme.', 'Je fabrique des bijoux en argent et en pierres semi-précieuses depuis 3 ans. Chaque pièce est unique et fabriquée avec soin.', 'approved', DATE_SUB(NOW(), INTERVAL 5 DAY));

