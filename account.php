<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Rediriger si non connecté
if (!isLoggedIn()) {
  header("Location: login.php");
  exit();
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

// Récupérer les commandes de l'utilisateur
$orders = getUserOrders($conn, $user_id);

// Traitement de la mise à jour du profil
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
  // Récupérer et valider les données
  $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
  $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
  $address = isset($_POST['address']) ? trim($_POST['address']) : '';
  $city = isset($_POST['city']) ? trim($_POST['city']) : '';
  $postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : '';
  $country = isset($_POST['country']) ? trim($_POST['country']) : '';
  $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
  
  $sql = "UPDATE users SET 
          first_name = ?, 
          last_name = ?, 
          address = ?, 
          city = ?, 
          postal_code = ?, 
          country = ?, 
          phone = ? 
          WHERE id = ?";
  
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("sssssssi", $first_name, $last_name, $address, $city, $postal_code, $country, $phone, $user_id);
  
  if ($stmt->execute()) {
      $success = "Profil mis à jour avec succès.";
      
      // Mettre à jour les informations de l'utilisateur
      $sql = "SELECT * FROM users WHERE id = $user_id";
      $result = $conn->query($sql);
      $user = $result->fetch_assoc();
  } else {
      $error = "Une erreur est survenue lors de la mise à jour du profil.";
  }
}

// Traitement du changement de mot de passe
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
  $current_password = $_POST['current_password'];
  $new_password = $_POST['new_password'];
  $confirm_password = $_POST['confirm_password'];
  
  // Vérifier si le mot de passe actuel est correct
  if (password_verify($current_password, $user['password'])) {
      // Vérifier si les nouveaux mots de passe correspondent
      if ($new_password == $confirm_password) {
          // Hacher le nouveau mot de passe
          $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
          
          // Mettre à jour le mot de passe
          $sql = "UPDATE users SET password = ? WHERE id = ?";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("si", $hashed_password, $user_id);
          
          if ($stmt->execute()) {
              $success = "Mot de passe changé avec succès.";
          } else {
              $error = "Une erreur est survenue lors du changement de mot de passe.";
          }
      } else {
          $error = "Les nouveaux mots de passe ne correspondent pas.";
      }
  } else {
      $error = "Mot de passe actuel incorrect.";
  }
}

// Traitement de la demande de compte vendeur
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_seller'])) {
  $motivation = isset($_POST['motivation']) ? trim($_POST['motivation']) : '';
  $business_info = isset($_POST['business_info']) ? trim($_POST['business_info']) : '';
  
  // Vérifier si l'utilisateur a déjà une demande en cours
  $check_sql = "SELECT * FROM seller_requests WHERE user_id = $user_id AND status = 'pending'";
  $check_result = $conn->query($check_sql);
  
  if ($check_result && $check_result->num_rows > 0) {
      $error = "Vous avez déjà une demande de compte vendeur en attente.";
  } else {
      // Insérer la demande dans la base de données
      $sql = "INSERT INTO seller_requests (user_id, motivation, business_info, created_at, status) 
              VALUES (?, ?, ?, NOW(), 'pending')";
      
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("iss", $user_id, $motivation, $business_info);
      
      if ($stmt->execute()) {
          $success = "Votre demande de compte vendeur a été soumise avec succès. Vous serez notifié lorsqu'elle sera traitée.";
          
          // Notifier les administrateurs
          $admin_sql = "SELECT id FROM users WHERE role = 'admin'";
          $admin_result = $conn->query($admin_sql);
          
          if ($admin_result && $admin_result->num_rows > 0) {
              while ($admin = $admin_result->fetch_assoc()) {
                  $admin_id = $admin['id'];
                  $notification_message = "Nouvelle demande de compte vendeur de " . $user['username'];
                  $notification_link = "admin/seller_requests.php";
                  
                  sendNotification($conn, $admin_id, $notification_message, $notification_link);
              }
          }
      } else {
          $error = "Une erreur est survenue lors de la soumission de votre demande.";
      }
  }
}

// Vérifier si l'utilisateur a une demande de compte vendeur en cours
$seller_request = null;
$has_pending_request = false;

$request_sql = "SELECT * FROM seller_requests WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 1";
$request_result = $conn->query($request_sql);

if ($request_result && $request_result->num_rows > 0) {
  $seller_request = $request_result->fetch_assoc();
  $has_pending_request = ($seller_request['status'] == 'pending');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mon compte - Omnes MarketPlace</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
  <?php include("includes/header.php"); ?>

  <div class="container mt-5">
      <h1>Mon compte</h1>
      
      <?php if (!empty($success)): ?>
          <div class="alert alert-success"><?php echo $success; ?></div>
      <?php endif; ?>
      
      <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?php echo $error; ?></div>
      <?php endif; ?>
      
      <div class="row">
          <div class="col-md-3">
              <div class="list-group">
                  <a href="#profile" class="list-group-item list-group-item-action active" data-bs-toggle="list">Profil</a>
                  <a href="#orders" class="list-group-item list-group-item-action" data-bs-toggle="list">Mes commandes</a>
                  <a href="#password" class="list-group-item list-group-item-action" data-bs-toggle="list">Changer le mot de passe</a>
                  <?php if ($user['role'] == 'buyer'): ?>
                      <a href="#seller-request" class="list-group-item list-group-item-action" data-bs-toggle="list">Devenir vendeur</a>
                  <?php endif; ?>
                  <?php if (isSeller()): ?>
                      <a href="seller/index.php" class="list-group-item list-group-item-action">Espace vendeur</a>
                  <?php endif; ?>
                  <?php if (isAdmin()): ?>
                      <a href="admin/index.php" class="list-group-item list-group-item-action">Administration</a>
                  <?php endif; ?>
                  <a href="logout.php" class="list-group-item list-group-item-action text-danger">Déconnexion</a>
              </div>
          </div>
          
          <div class="col-md-9">
              <div class="tab-content">
                  <div class="tab-pane fade show active" id="profile">
                      <div class="card">
                          <div class="card-header bg-primary text-white">
                              <h5 class="mb-0">Informations personnelles</h5>
                          </div>
                          <div class="card-body">
                              <form method="POST" action="account.php">
                                  <div class="row">
                                      <div class="col-md-6 mb-3">
                                          <label for="username" class="form-label">Nom d'utilisateur</label>
                                          <input type="text" class="form-control" id="username" value="<?php echo $user['username']; ?>" readonly>
                                      </div>
                                      <div class="col-md-6 mb-3">
                                          <label for="email" class="form-label">Adresse e-mail</label>
                                          <input type="email" class="form-control" id="email" value="<?php echo $user['email']; ?>" readonly>
                                      </div>
                                  </div>
                                  
                                  <div class="row">
                                      <div class="col-md-6 mb-3">
                                          <label for="first_name" class="form-label">Prénom *</label>
                                          <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $user['first_name']; ?>" required>
                                      </div>
                                      <div class="col-md-6 mb-3">
                                          <label for="last_name" class="form-label">Nom *</label>
                                          <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $user['last_name']; ?>" required>
                                      </div>
                                  </div>
                                  
                                  <div class="mb-3">
                                      <label for="address" class="form-label">Adresse *</label>
                                      <input type="text" class="form-control" id="address" name="address" value="<?php echo $user['address']; ?>" required>
                                  </div>
                                  
                                  <div class="row">
                                      <div class="col-md-6 mb-3">
                                          <label for="city" class="form-label">Ville *</label>
                                          <input type="text" class="form-control" id="city" name="city" value="<?php echo $user['city']; ?>" required>
                                      </div>
                                      <div class="col-md-6 mb-3">
                                          <label for="postal_code" class="form-label">Code postal *</label>
                                          <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo $user['postal_code']; ?>" required>
                                      </div>
                                  </div>
                                  
                                  <div class="row">
                                      <div class="col-md-6 mb-3">
                                          <label for="country" class="form-label">Pays *</label>
                                          <input type="text" class="form-control" id="country" name="country" value="<?php echo $user['country']; ?>" required>
                                      </div>
                                      <div class="col-md-6 mb-3">
                                          <label for="phone" class="form-label">Téléphone *</label>
                                          <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $user['phone']; ?>" required>
                                      </div>
                                  </div>
                                  
                                  <div class="d-flex align-items-center">
                                      <button type="submit" name="update_profile" class="btn btn-primary">Mettre à jour le profil</button>
                                      
                                      <div class="ms-3 account-type-badge">
                                          <span class="badge <?php echo $user['role'] == 'admin' ? 'bg-danger' : ($user['role'] == 'seller' ? 'bg-success' : 'bg-info'); ?>">
                                              <?php 
                                              if ($user['role'] == 'admin') {
                                                  echo 'Administrateur';
                                              } elseif ($user['role'] == 'seller') {
                                                  echo 'Vendeur';
                                              } else {
                                                  echo 'Acheteur';
                                              }
                                              ?>
                                          </span>
                                      </div>
                                  </div>
                              </form>
                          </div>
                      </div>
                  </div>
                  
                  <div class="tab-pane fade" id="orders">
                      <div class="card">
                          <div class="card-header bg-primary text-white">
                              <h5 class="mb-0">Mes commandes</h5>
                          </div>
                          <div class="card-body">
                              <?php if (empty($orders)): ?>
                                  <div class="alert alert-info">
                                      <p>Vous n'avez pas encore passé de commande.</p>
                                      <a href="index.php" class="btn btn-primary">Commencer vos achats</a>
                                  </div>
                              <?php else: ?>
                                  <div class="table-responsive">
                                      <table class="table table-striped">
                                          <thead>
                                              <tr>
                                                  <th>Commande #</th>
                                                  <th>Date</th>
                                                  <th>Total</th>
                                                  <th>Statut</th>
                                                  <th>Actions</th>
                                              </tr>
                                          </thead>
                                          <tbody>
                                              <?php foreach ($orders as $order): ?>
                                                  <tr>
                                                      <td>#<?php echo $order['id']; ?></td>
                                                      <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                                      <td><?php echo $order['total_amount']; ?> €</td>
                                                      <td>
                                                          <span class="badge bg-<?php echo $order['order_status'] == 'pending' ? 'warning' : ($order['order_status'] == 'shipped' ? 'info' : 'success'); ?>">
                                                              <?php echo ucfirst($order['order_status']); ?>
                                                          </span>
                                                      </td>
                                                      <td>
                                                          <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">Détails</a>
                                                      </td>
                                                  </tr>
                                              <?php endforeach; ?>
                                          </tbody>
                                      </table>
                                  </div>
                              <?php endif; ?>
                          </div>
                      </div>
                  </div>
                  
                  <div class="tab-pane fade" id="password">
                      <div class="card">
                          <div class="card-header bg-primary text-white">
                              <h5 class="mb-0">Changer le mot de passe</h5>
                          </div>
                          <div class="card-body">
                              <form method="POST" action="account.php">
                                  <div class="mb-3">
                                      <label for="current_password" class="form-label">Mot de passe actuel</label>
                                      <input type="password" class="form-control" id="current_password" name="current_password" required>
                                  </div>
                                  
                                  <div class="mb-3">
                                      <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                      <input type="password" class="form-control" id="new_password" name="new_password" required>
                                  </div>
                                  
                                  <div class="mb-3">
                                      <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                      <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                  </div>
                                  
                                  <button type="submit" name="change_password" class="btn btn-primary">Changer le mot de passe</button>
                              </form>
                          </div>
                      </div>
                  </div>
                  
                  <?php if ($user['role'] == 'buyer'): ?>
                  <div class="tab-pane fade" id="seller-request">
                      <div class="card">
                          <div class="card-header bg-primary text-white">
                              <h5 class="mb-0">Devenir vendeur</h5>
                          </div>
                          <div class="card-body">
                              <?php if ($has_pending_request): ?>
                                  <div class="alert alert-info">
                                      <h5><i class="fas fa-info-circle me-2"></i>Demande en cours de traitement</h5>
                                      <p>Votre demande de compte vendeur a été soumise le <?php echo date('d/m/Y H:i', strtotime($seller_request['created_at'])); ?> et est en attente d'approbation.</p>
                                      <p>Vous recevrez une notification dès que votre demande aura été traitée.</p>
                                  </div>
                                  <div class="card mt-3">
                                      <div class="card-header">
                                          <h6 class="mb-0">Détails de votre demande</h6>
                                      </div>
                                      <div class="card-body">
                                          <h6>Motivation :</h6>
                                          <p><?php echo nl2br($seller_request['motivation']); ?></p>
                                          <h6>Informations professionnelles :</h6>
                                          <p><?php echo nl2br($seller_request['business_info']); ?></p>
                                      </div>
                                  </div>
                              <?php elseif ($seller_request && $seller_request['status'] == 'rejected'): ?>
                                  <div class="alert alert-warning">
                                      <h5><i class="fas fa-exclamation-triangle me-2"></i>Demande précédente refusée</h5>
                                      <p>Votre dernière demande de compte vendeur a été refusée le <?php echo date('d/m/Y H:i', strtotime($seller_request['updated_at'])); ?>.</p>
                                      <p><strong>Motif :</strong> <?php echo $seller_request['admin_comment']; ?></p>
                                      <p>Vous pouvez soumettre une nouvelle demande en tenant compte des remarques ci-dessus.</p>
                                  </div>
                                  <form method="POST" action="account.php" class="mt-4">
                                      <div class="mb-3">
                                          <label for="motivation" class="form-label">Pourquoi souhaitez-vous devenir vendeur ?</label>
                                          <textarea class="form-control" id="motivation" name="motivation" rows="4" required></textarea>
                                          <div class="form-text">Expliquez vos motivations et ce que vous souhaitez vendre sur notre plateforme.</div>
                                      </div>
                                      
                                      <div class="mb-3">
                                          <label for="business_info" class="form-label">Informations professionnelles</label>
                                          <textarea class="form-control" id="business_info" name="business_info" rows="4" required></textarea>
                                          <div class="form-text">Fournissez des informations sur votre activité, votre expérience et vos produits.</div>
                                      </div>
                                      
                                      <button type="submit" name="request_seller" class="btn btn-primary">Soumettre ma demande</button>
                                  </form>
                              <?php else: ?>
                                  <div class="alert alert-info mb-4">
                                      <h5><i class="fas fa-store me-2"></i>Devenez vendeur sur Omnes MarketPlace</h5>
                                      <p>En tant que vendeur, vous pourrez :</p>
                                      <ul>
                                          <li>Mettre en vente vos produits</li>
                                          <li>Gérer vos enchères et négociations</li>
                                          <li>Suivre vos ventes et revenus</li>
                                          <li>Développer votre activité au sein de la communauté Omnes</li>
                                      </ul>
                                  </div>
                                  
                                  <form method="POST" action="account.php">
                                      <div class="mb-3">
                                          <label for="motivation" class="form-label">Pourquoi souhaitez-vous devenir vendeur ?</label>
                                          <textarea class="form-control" id="motivation" name="motivation" rows="4" required></textarea>
                                          <div class="form-text">Expliquez vos motivations et ce que vous souhaitez vendre sur notre plateforme.</div>
                                      </div>
                                      
                                      <div class="mb-3">
                                          <label for="business_info" class="form-label">Informations professionnelles</label>
                                          <textarea class="form-control" id="business_info" name="business_info" rows="4" required></textarea>
                                          <div class="form-text">Fournissez des informations sur votre activité, votre expérience et vos produits.</div>
                                      </div>
                                      
                                      <button type="submit" name="request_seller" class="btn btn-primary">Soumettre ma demande</button>
                                  </form>
                              <?php endif; ?>
                          </div>
                      </div>
                  </div>
                  <?php endif; ?>
              </div>
          </div>
      </div>
  </div>

  <?php include("includes/footer.php"); ?>
</body>
</html>

