<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Rediriger si non connecté
if (!isLoggedIn()) {
  header("Location: login.php");
  exit();
}

// Vérifier si l'ID de commande est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header("Location: account.php");
  exit();
}

$order_id = $_GET['id'];
$order = getOrderDetails($conn, $order_id);

// Vérifier si la commande existe et appartient à l'utilisateur
if (!$order || $order['user_id'] != $_SESSION['user_id']) {
  header("Location: account.php");
  exit();
}

// Récupérer les statuts d'envoi des confirmations
$email_sent = isset($_SESSION['email_confirmation_sent']) ? $_SESSION['email_confirmation_sent'] : false;
$sms_sent = isset($_SESSION['sms_confirmation_sent']) ? $_SESSION['sms_confirmation_sent'] : false;

// Nettoyer les variables de session après utilisation
unset($_SESSION['email_confirmation_sent']);
unset($_SESSION['sms_confirmation_sent']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirmation de commande - Omnes MarketPlace</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
  <?php include("includes/header.php"); ?>

  <div class="container mt-5">
      <div class="card">
          <div class="card-header bg-success text-white">
              <h3 class="mb-0">Commande confirmée</h3>
          </div>
          <div class="card-body">
              <div class="text-center mb-4">
                  <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                  <h4 class="mt-3">Merci pour votre commande !</h4>
                  <p>Votre commande a été traitée avec succès.</p>
                  
                  <?php if ($email_sent || $sms_sent): ?>
                      <div class="alert alert-info">
                          <?php if ($email_sent): ?>
                              <p><i class="fas fa-envelope"></i> Un email de confirmation a été envoyé à votre adresse email.</p>
                          <?php endif; ?>
                          <?php if ($sms_sent): ?>
                              <p><i class="fas fa-sms"></i> Un SMS de confirmation a été envoyé à votre numéro de téléphone.</p>
                          <?php endif; ?>
                      </div>
                  <?php endif; ?>
              </div>
              
              <div class="row">
                  <div class="col-md-6">
                      <h5>Détails de la commande</h5>
                      <p><strong>Numéro de commande:</strong> #<?php echo $order['id']; ?></p>
                      <p><strong>Date:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                      <p><strong>Statut de la commande:</strong> <span class="badge bg-info"><?php echo ucfirst($order['order_status']); ?></span></p>
                      <p><strong>Statut du paiement:</strong> <span class="badge bg-success"><?php echo ucfirst($order['payment_status']); ?></span></p>
                      <p><strong>Méthode de paiement:</strong> <?php echo ucfirst($order['payment_method']); ?></p>
                  </div>
                  
                  <div class="col-md-6">
                      <h5>Adresse de livraison</h5>
                      <p><?php echo $_SESSION['username']; ?></p>
                      <p><?php echo $order['shipping_address']; ?></p>
                      <p><?php echo $order['shipping_city'] . ' ' . $order['shipping_postal_code']; ?></p>
                      <p><?php echo $order['shipping_country']; ?></p>
                      <p><?php echo $order['shipping_phone']; ?></p>
                  </div>
              </div>
              
              <h5 class="mt-4">Articles commandés</h5>
              <div class="table-responsive">
                  <table class="table table-bordered">
                      <thead>
                          <tr>
                              <th>Produit</th>
                              <th>Prix unitaire</th>
                              <th>Quantité</th>
                              <th>Total</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php foreach ($order['items'] as $item): ?>
                              <tr>
                                  <td>
                                      <div class="d-flex align-items-center">
                                          <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['product_name']; ?>" class="img-thumbnail me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                          <?php echo $item['product_name']; ?>
                                      </div>
                                  </td>
                                  <td><?php echo $item['price']; ?> €</td>
                                  <td><?php echo $item['quantity']; ?></td>
                                  <td><?php echo $item['price'] * $item['quantity']; ?> €</td>
                              </tr>
                          <?php endforeach; ?>
                      </tbody>
                      <tfoot>
                          <tr>
                              <td colspan="3" class="text-end"><strong>Sous-total</strong></td>
                              <td><?php echo $order['total_amount']; ?> €</td>
                          </tr>
                          <tr>
                              <td colspan="3" class="text-end"><strong>Frais de livraison</strong></td>
                              <td>Gratuit</td>
                          </tr>
                          <tr>
                              <td colspan="3" class="text-end"><strong>Total</strong></td>
                              <td><strong><?php echo $order['total_amount']; ?> €</strong></td>
                          </tr>
                      </tfoot>
                  </table>
              </div>
              
              <div class="text-center mt-4">
                  <a href="index.php" class="btn btn-primary">Continuer vos achats</a>
                  <a href="account.php" class="btn btn-outline-primary">Voir mes commandes</a>
              </div>
          </div>
      </div>
  </div>

  <?php include("includes/footer.php"); ?>
</body>
</html>

