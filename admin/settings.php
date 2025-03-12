<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
  header("Location: ../login.php");
  exit();
}

// Vérifier si la table settings existe et la créer si nécessaire
$check_table = $conn->query("SHOW TABLES LIKE 'settings'");
if ($check_table->num_rows == 0) {
  // Créer la table si elle n'existe pas
  $create_table_sql = "CREATE TABLE settings (
    id INT(11) NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(255) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY (setting_key)
  )";
  
  if (!$conn->query($create_table_sql)) {
    $error_message = "Erreur lors de la création de la table settings: " . $conn->error;
  } else {
    // Insérer les valeurs par défaut
    $default_settings = [
      'site_name' => 'Omnes Marketplace',
      'site_description' => 'Votre plateforme de commerce en ligne',
      'admin_email' => 'admin@omnesmarketplace.com',
      'items_per_page' => 10,
      'currency' => 'EUR',
      'payment_methods' => 'card,paypal',
      'tax_rate' => 20,
      'theme_color' => '#4CAF50',
      'show_featured' => 1,
      'show_bestsellers' => 1,
      'show_new_products' => 1
    ];
    
    foreach ($default_settings as $key => $value) {
      $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')");
    }
  }
}

// Traitement du formulaire de mise à jour des paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Paramètres généraux
  if (isset($_POST['update_general'])) {
    $site_name = $conn->real_escape_string($_POST['site_name']);
    $site_description = $conn->real_escape_string($_POST['site_description']);
    $admin_email = $conn->real_escape_string($_POST['admin_email']);
    $items_per_page = intval($_POST['items_per_page']);
    
    // Mettre à jour ou insérer les paramètres
    $settings = [
      'site_name' => $site_name,
      'site_description' => $site_description,
      'admin_email' => $admin_email,
      'items_per_page' => $items_per_page
    ];
    
    foreach ($settings as $key => $value) {
      $check = $conn->query("SELECT * FROM settings WHERE setting_key = '$key'");
      if ($check->num_rows > 0) {
        $conn->query("UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
      } else {
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')");
      }
    }
    
    $success_message = "Paramètres généraux mis à jour avec succès.";
  }
  
  // Paramètres de paiement
  if (isset($_POST['update_payment'])) {
    $currency = $conn->real_escape_string($_POST['currency']);
    $payment_methods = isset($_POST['payment_methods']) ? implode(',', $_POST['payment_methods']) : '';
    $tax_rate = floatval($_POST['tax_rate']);
    
    $settings = [
      'currency' => $currency,
      'payment_methods' => $payment_methods,
      'tax_rate' => $tax_rate
    ];
    
    foreach ($settings as $key => $value) {
      $check = $conn->query("SELECT * FROM settings WHERE setting_key = '$key'");
      if ($check->num_rows > 0) {
        $conn->query("UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
      } else {
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')");
      }
    }
    
    $success_message = "Paramètres de paiement mis à jour avec succès.";
  }
  
  // Paramètres d'affichage
  if (isset($_POST['update_display'])) {
    $theme_color = $conn->real_escape_string($_POST['theme_color']);
    $show_featured = isset($_POST['show_featured']) ? 1 : 0;
    $show_bestsellers = isset($_POST['show_bestsellers']) ? 1 : 0;
    $show_new_products = isset($_POST['show_new_products']) ? 1 : 0;
    
    $settings = [
      'theme_color' => $theme_color,
      'show_featured' => $show_featured,
      'show_bestsellers' => $show_bestsellers,
      'show_new_products' => $show_new_products
    ];
    
    foreach ($settings as $key => $value) {
      $check = $conn->query("SELECT * FROM settings WHERE setting_key = '$key'");
      if ($check->num_rows > 0) {
        $conn->query("UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
      } else {
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')");
      }
    }
    
    $success_message = "Paramètres d'affichage mis à jour avec succès.";
  }
}

// Récupérer les paramètres actuels
$settings = [];
$default_settings = [
  'site_name' => 'Omnes Marketplace',
  'site_description' => 'Votre plateforme de commerce en ligne',
  'admin_email' => 'admin@omnesmarketplace.com',
  'items_per_page' => 10,
  'currency' => 'EUR',
  'payment_methods' => 'card,paypal',
  'tax_rate' => 20,
  'theme_color' => '#4CAF50',
  'show_featured' => 1,
  'show_bestsellers' => 1,
  'show_new_products' => 1
];

// Récupérer les paramètres de la base de données
$result = $conn->query("SELECT * FROM settings");
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
  }
}

// Fusionner les paramètres existants avec les valeurs par défaut
$settings = array_merge($default_settings, $settings);
?>

<?php include("includes/admin_header.php"); ?>

<div class="container-fluid">
  <div class="row">
    <!-- Sidebar -->
    <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
      <?php include("includes/admin_sidebar.php"); ?>
    </div>

    <!-- Main Content -->
    <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Paramètres du site</h1>
      </div>
      
      <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?php echo $error_message; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?php echo $success_message; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
      
      <!-- Stats Cards -->
      <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
          <div class="card bg-primary text-white mb-4">
            <div class="card-body">
              <h5 class="card-title">Paramètres généraux</h5>
              <p class="card-text">Configuration de base du site</p>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card bg-success text-white mb-4">
            <div class="card-body">
              <h5 class="card-title">Paramètres de paiement</h5>
              <p class="card-text">Options de paiement et taxes</p>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card bg-warning text-white mb-4">
            <div class="card-body">
              <h5 class="card-title">Paramètres d'affichage</h5>
              <p class="card-text">Apparence et mise en page</p>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card bg-danger text-white mb-4">
            <div class="card-body">
              <h5 class="card-title">Maintenance</h5>
              <p class="card-text">Sauvegarde et optimisation</p>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Settings Tabs -->
      <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">Général</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab" aria-controls="payment" aria-selected="false">Paiement</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="display-tab" data-bs-toggle="tab" data-bs-target="#display" type="button" role="tab" aria-controls="display" aria-selected="false">Affichage</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab" aria-controls="maintenance" aria-selected="false">Maintenance</button>
        </li>
      </ul>
      
      <div class="tab-content" id="settingsTabsContent">
        <!-- General Settings -->
        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
          <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
              <h5 class="card-title">Paramètres généraux</h5>
              <form method="post" action="">
                <div class="mb-3">
                  <label for="site_name" class="form-label">Nom du site</label>
                  <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                </div>
                <div class="mb-3">
                  <label for="site_description" class="form-label">Description du site</label>
                  <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                </div>
                <div class="mb-3">
                  <label for="admin_email" class="form-label">Email administrateur</label>
                  <input type="email" class="form-control" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email']); ?>" required>
                </div>
                <div class="mb-3">
                  <label for="items_per_page" class="form-label">Éléments par page</label>
                  <input type="number" class="form-control" id="items_per_page" name="items_per_page" value="<?php echo intval($settings['items_per_page']); ?>" min="5" max="100" required>
                </div>
                <button type="submit" name="update_general" class="btn btn-primary">Enregistrer les modifications</button>
              </form>
            </div>
          </div>
        </div>
        
        <!-- Payment Settings -->
        <div class="tab-pane fade" id="payment" role="tabpanel" aria-labelledby="payment-tab">
          <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
              <h5 class="card-title">Paramètres de paiement</h5>
              <form method="post" action="">
                <div class="mb-3">
                  <label for="currency" class="form-label">Devise</label>
                  <select class="form-select" id="currency" name="currency">
                    <option value="EUR" <?php echo $settings['currency'] == 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                    <option value="USD" <?php echo $settings['currency'] == 'USD' ? 'selected' : ''; ?>>Dollar US ($)</option>
                    <option value="GBP" <?php echo $settings['currency'] == 'GBP' ? 'selected' : ''; ?>>Livre Sterling (£)</option>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Méthodes de paiement acceptées</label>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="payment_card" name="payment_methods[]" value="card" <?php echo strpos($settings['payment_methods'], 'card') !== false ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="payment_card">Carte bancaire</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="payment_paypal" name="payment_methods[]" value="paypal" <?php echo strpos($settings['payment_methods'], 'paypal') !== false ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="payment_paypal">PayPal</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="payment_bank" name="payment_methods[]" value="bank" <?php echo strpos($settings['payment_methods'], 'bank') !== false ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="payment_bank">Virement bancaire</label>
                  </div>
                </div>
                <div class="mb-3">
                  <label for="tax_rate" class="form-label">Taux de TVA (%)</label>
                  <input type="number" class="form-control" id="tax_rate" name="tax_rate" value="<?php echo floatval($settings['tax_rate']); ?>" min="0" max="100" step="0.1" required>
                </div>
                <button type="submit" name="update_payment" class="btn btn-primary">Enregistrer les modifications</button>
              </form>
            </div>
          </div>
        </div>
        
        <!-- Display Settings -->
        <div class="tab-pane fade" id="display" role="tabpanel" aria-labelledby="display-tab">
          <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
              <h5 class="card-title">Paramètres d'affichage</h5>
              <form method="post" action="">
                <div class="mb-3">
                  <label for="theme_color" class="form-label">Couleur principale</label>
                  <input type="color" class="form-control form-control-color" id="theme_color" name="theme_color" value="<?php echo htmlspecialchars($settings['theme_color']); ?>" title="Choisir la couleur principale">
                </div>
                <div class="mb-3">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="show_featured" name="show_featured" <?php echo $settings['show_featured'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="show_featured">Afficher les produits en vedette sur la page d'accueil</label>
                  </div>
                </div>
                <div class="mb-3">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="show_bestsellers" name="show_bestsellers" <?php echo $settings['show_bestsellers'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="show_bestsellers">Afficher les meilleures ventes sur la page d'accueil</label>
                  </div>
                </div>
                <div class="mb-3">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="show_new_products" name="show_new_products" <?php echo $settings['show_new_products'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="show_new_products">Afficher les nouveaux produits sur la page d'accueil</label>
                  </div>
                </div>
                <button type="submit" name="update_display" class="btn btn-primary">Enregistrer les modifications</button>
              </form>
            </div>
          </div>
        </div>
        
        <!-- Maintenance Settings -->
        <div class="tab-pane fade" id="maintenance" role="tabpanel" aria-labelledby="maintenance-tab">
          <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
              <h5 class="card-title">Maintenance du site</h5>
              <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Ces fonctionnalités permettent de gérer la maintenance et l'optimisation de votre site.
              </div>
              
              <div class="row mb-4">
                <div class="col-md-6">
                  <div class="card">
                    <div class="card-body">
                      <h5 class="card-title">Sauvegarde de la base de données</h5>
                      <p class="card-text">Créez une sauvegarde complète de votre base de données.</p>
                      <a href="#" class="btn btn-primary">Créer une sauvegarde</a>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="card">
                    <div class="card-body">
                      <h5 class="card-title">Optimisation</h5>
                      <p class="card-text">Optimisez les tables de la base de données pour améliorer les performances.</p>
                      <a href="#" class="btn btn-success">Optimiser la base de données</a>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="card">
                    <div class="card-body">
                      <h5 class="card-title">Mode maintenance</h5>
                      <p class="card-text">Activez le mode maintenance pour effectuer des mises à jour.</p>
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="maintenance_mode">
                        <label class="form-check-label" for="maintenance_mode">Activer le mode maintenance</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="card">
                    <div class="card-body">
                      <h5 class="card-title">Nettoyage</h5>
                      <p class="card-text">Supprimez les données temporaires et le cache.</p>
                      <a href="#" class="btn btn-warning">Nettoyer le cache</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include("includes/admin_footer.php"); ?>

