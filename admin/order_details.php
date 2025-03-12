<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
  header("Location: ../login.php");
  exit();
}

// Vérifier si l'ID de la commande est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header("Location: orders.php");
  exit();
}

$order_id = intval($_GET['id']);

// Récupérer les détails de la commande
$order = null;
$items = [];

// Vérifier si la table order_items existe
$checkTableQuery = "SHOW TABLES LIKE 'order_items'";
$tableResult = $conn->query($checkTableQuery);
$orderItemsTableExists = ($tableResult && $tableResult->num_rows > 0);

// Récupérer les informations de base de la commande
$query = "SELECT o.*, u.username, u.email, u.phone 
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          WHERE o.id = $order_id";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
  $order = $result->fetch_assoc();
  
  // Si la table order_items existe, récupérer les articles de la commande
  if ($orderItemsTableExists) {
    $query = "SELECT oi.*, p.name as product_name, p.image 
              FROM order_items oi 
              LEFT JOIN products p ON oi.product_id = p.id 
              WHERE oi.order_id = $order_id";
    $result = $conn->query($query);
    
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $items[] = $row;
      }
    }
  }
}

// Si la commande n'existe pas, rediriger
if (!$order) {
  header("Location: orders.php");
  exit();
}

// Gérer la mise à jour du statut
if (isset($_POST['update_status'])) {
  $status = $_POST['status'];
  
  $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
  $stmt->bind_param("si", $status, $order_id);
  $stmt->execute();
  
  // Rediriger pour éviter la resoumission du formulaire
  header("Location: order_details.php?id=$order_id&updated=1");
  exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Détails de la Commande #<?php echo $order_id; ?> - Administration</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
  <div class="container-fluid">
      <div class="row">
          <!-- Sidebar -->
          <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
              <?php include('includes/admin_sidebar.php'); ?>
          </nav>
          
          <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
              <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                  <h1 class="h2">Détails de la Commande #<?php echo $order_id; ?></h1>
                  <div class="btn-toolbar mb-2 mb-md-0">
                      <a href="orders.php" class="btn btn-sm btn-outline-secondary">
                          <i class="fas fa-arrow-left"></i> Retour aux commandes
                      </a>
                  </div>
              </div>
              
              <?php if (isset($_GET['updated'])): ?>
                  <div class="alert alert-success alert-dismissible fade show" role="alert">
                      Le statut de la commande a été mis à jour avec succès.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
              <?php endif; ?>
              
              <div class="row mb-4">
                  <div class="col-md-6">
                      <div class="card">
                          <div class="card-header">
                              <h5 class="card-title mb-0">Informations de la Commande</h5>
                          </div>
                          <div class="card-body">
                              <table class="table table-borderless">
                                  <tr>
                                      <th>ID de Commande:</th>
                                      <td>#<?php echo $order['id']; ?></td>
                                  </tr>
                                  <tr>
                                      <th>Date:</th>
                                      <td><?php echo isset($order['created_at']) ? date('d/m/Y H:i', strtotime($order['created_at'])) : 'N/A'; ?></td>
                                  </tr>
                                  <tr>
                                      <th>Statut:</th>
                                      <td>
                                          <?php 
                                          $status_class = '';
                                          $status_text = 'Inconnu';
                                          
                                          if (isset($order['status'])) {
                                              switch($order['status']) {
                                                  case 'pending':
                                                      $status_class = 'bg-warning';
                                                      $status_text = 'En attente';
                                                      break;
                                                  case 'processing':
                                                      $status_class = 'bg-info';
                                                      $status_text = 'En traitement';
                                                      break;
                                                  case 'shipped':
                                                      $status_class = 'bg-primary';
                                                      $status_text = 'Expédiée';
                                                      break;
                                                  case 'delivered':
                                                      $status_class = 'bg-success';
                                                      $status_text = 'Livrée';
                                                      break;
                                                  case 'cancelled':
                                                      $status_class = 'bg-danger';
                                                      $status_text = 'Annulée';
                                                      break;
                                                  default:
                                                      $status_class = 'bg-secondary';
                                                      $status_text = $order['status'];
                                              }
                                          }
                                          ?>
                                          <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                          <button class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                              Modifier
                                          </button>
                                      </td>
                                  </tr>
                                  <tr>
                                      <th>Montant Total:</th>
                                      <td><?php echo number_format(isset($order['total_amount']) ? $order['total_amount'] : 0, 2); ?> €</td>
                                  </tr>
                                  <tr>
                                      <th>Méthode de Paiement:</th>
                                      <td><?php echo isset($order['payment_method']) ? ucfirst($order['payment_method']) : 'N/A'; ?></td>
                                  </tr>
                                  <tr>
                                      <th>Statut du Paiement:</th>
                                      <td>
                                          <?php 
                                          $payment_status = isset($order['payment_status']) ? $order['payment_status'] : 'N/A';
                                          $payment_class = ($payment_status == 'completed') ? 'bg-success' : 'bg-warning';
                                          ?>
                                          <span class="badge <?php echo $payment_class; ?>">
                                              <?php echo ($payment_status == 'completed') ? 'Payé' : 'En attente'; ?>
                                          </span>
                                      </td>
                                  </tr>
                              </table>
                          </div>
                      </div>
                  </div>
                  
                  <div class="col-md-6">
                      <div class="card">
                          <div class="card-header">
                              <h5 class="card-title mb-0">Informations du Client</h5>
                          </div>
                          <div class="card-body">
                              <table class="table table-borderless">
                                  <tr>
                                      <th>Nom:</th>
                                      <td><?php echo htmlspecialchars($order['username']); ?></td>
                                  </tr>
                                  <tr>
                                      <th>Email:</th>
                                      <td><?php echo htmlspecialchars($order['email']); ?></td>
                                  </tr>
                                  <tr>
                                      <th>Téléphone:</th>
                                      <td><?php echo isset($order['phone']) ? htmlspecialchars($order['phone']) : 'N/A'; ?></td>
                                  </tr>
                                  <tr>
                                      <th>Adresse de Livraison:</th>
                                      <td>
                                          <?php if (isset($order['shipping_address'])): ?>
                                              <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                                              <?php echo htmlspecialchars($order['shipping_postal_code'] . ' ' . $order['shipping_city']); ?><br>
                                              <?php echo htmlspecialchars($order['shipping_country']); ?>
                                          <?php else: ?>
                                              N/A
                                          <?php endif; ?>
                                      </td>
                                  </tr>
                              </table>
                          </div>
                      </div>
                  </div>
              </div>
              
              <div class="card mb-4">
                  <div class="card-header">
                      <h5 class="card-title mb-0">Articles Commandés</h5>
                  </div>
                  <div class="card-body">
                      <?php if (count($items) > 0): ?>
                          <div class="table-responsive">
                              <table class="table table-striped">
                                  <thead>
                                      <tr>
                                          <th>Produit</th>
                                          <th>Image</th>
                                          <th>Prix Unitaire</th>
                                          <th>Quantité</th>
                                          <th>Total</th>
                                      </tr>
                                  </thead>
                                  <tbody>
                                      <?php foreach ($items as $item): ?>
                                          <tr>
                                              <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                              <td>
                                                  <?php if (isset($item['image']) && !empty($item['image'])): ?>
                                                      <img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" width="50">
                                                  <?php else: ?>
                                                      <img src="../images/placeholder.jpg" alt="Placeholder" width="50">
                                                  <?php endif; ?>
                                              </td>
                                              <td><?php echo number_format($item['price'], 2); ?> €</td>
                                              <td><?php echo $item['quantity']; ?></td>
                                              <td><?php echo number_format($item['price'] * $item['quantity'], 2); ?> €</td>
                                          </tr>
                                      <?php endforeach; ?>
                                  </tbody>
                                  <tfoot>
                                      <tr>
                                          <th colspan="4" class="text-end">Total:</th>
                                          <th><?php echo number_format(isset($order['total_amount']) ? $order['total_amount'] : 0, 2); ?> €</th>
                                      </tr>
                                  </tfoot>
                              </table>
                          </div>
                      <?php else: ?>
                          <div class="alert alert-info">
                              Aucun article trouvé pour cette commande.
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
              
              <!-- Modal de mise à jour du statut -->
              <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
                  <div class="modal-dialog">
                      <div class="modal-content">
                          <div class="modal-header">
                              <h5 class="modal-title" id="updateStatusModalLabel">Mettre à jour le statut de la commande #<?php echo $order_id; ?></h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body">
                              <form id="updateStatusForm" action="" method="POST">
                                  <div class="mb-3">
                                      <label for="status" class="form-label">Statut</label>
                                      <select class="form-select" id="status" name="status">
                                          <option value="pending" <?php echo (isset($order['status']) && $order['status'] == 'pending') ? 'selected' : ''; ?>>En attente</option>
                                          <option value="processing" <?php echo (isset($order['status']) && $order['status'] == 'processing') ? 'selected' : ''; ?>>En traitement</option>
                                          <option value="shipped" <?php echo (isset($order['status']) && $order['status'] == 'shipped') ? 'selected' : ''; ?>>Expédiée</option>
                                          <option value="delivered" <?php echo (isset($order['status']) && $order['status'] == 'delivered') ? 'selected' : ''; ?>>Livrée</option>
                                          <option value="cancelled" <?php echo (isset($order['status']) && $order['status'] == 'cancelled') ? 'selected' : ''; ?>>Annulée</option>
                                      </select>
                                  </div>
                              </form>
                          </div>
                          <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                              <button type="submit" form="updateStatusForm" name="update_status" class="btn btn-primary">Mettre à jour</button>
                          </div>
                      </div>
                  </div>
              </div>
          </main>
      </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

