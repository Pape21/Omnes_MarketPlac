-- Table pour les paramètres du site
CREATE TABLE IF NOT EXISTS settings (
  id INT(11) NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(255) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY (setting_key)
);

-- Insérer les valeurs par défaut
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Omnes Marketplace'),
('site_description', 'Votre plateforme de commerce en ligne'),
('admin_email', 'admin@omnesmarketplace.com'),
('items_per_page', '10'),
('currency', 'EUR'),
('payment_methods', 'card,paypal'),
('tax_rate', '20'),
('theme_color', '#4CAF50'),
('show_featured', '1'),
('show_bestsellers', '1'),
('show_new_products', '1');

