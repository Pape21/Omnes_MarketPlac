<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
  header("Location: ../login.php");
  exit();
}

// Vérifier si la colonne status existe dans la table orders
$checkColumnQuery = "SHOW COLUMNS FROM orders LIKE 'status'";
$columnResult = $conn->query($checkColumnQuery);

if ($columnResult && $columnResult->num_rows == 0) {
  // La colonne status n'existe pas, on la crée
  $addColumnQuery = "ALTER TABLE orders ADD COLUMN status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'";
  $conn->query($addColumnQuery);
  // Mettre à jour les commandes existantes
  $conn->query("UPDATE orders SET status = 'pending' WHERE status IS NULL");
}

// Récupérer la liste des commandes avec gestion d'erreur
$orders = [];
$query = "SELECT o.*, u.username, u.email 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC";
$result = $conn->query($query);

if ($result) {
  while ($row = $result->fetch_assoc()) {
      // Vérifier si les clés existent avant de les utiliser
      $row['status'] = isset($row['status']) ? $row['status'] : 'pending';
      $row['username'] = isset($row['username']) ? $row['username'] : 'Utilisateur inconnu';
      $row['email'] = isset($row['email']) ? $row['email'] : 'Email inconnu';
      $orders[] = $row;
  }
}

// Gérer la mise à jour du statut d'une commande
if (isset($_POST['update_status'])) {
  $order_id = $_POST['order_id'];
  $status = $_POST['status'];
  
  $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
  $stmt->bind_param("si", $status, $order_id);
  $stmt->execute();
  
  header("Location: orders.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion des Commandes - Administration</title>
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
                  <h1 class="h2">Gestion des Commandes</h1>
              </div>

              <!-- Statistiques -->
              <div class="row mb-4">
                  <div class="col-md-3">
                      <div class="card">
                          <div class="card-body">
                              <h5 class="card-title">Total Commandes</h5>
                              <p class="card-text fs-2"><?php echo count($orders); ?></p>
                          </div>
                      </div>
                  </div>
                  <div class="col-md-3">
                      <div class="card">
                          <div class="card-body">
                              <h5 class="card-title">En attente</h5>
                              <?php
                              $pending_orders = array_filter($orders, function($order) {
                                  return $order['status'] == 'pending';
                              });
                              ?>
                              <p class="card-text fs-2"><?php echo count($pending_orders); ?></p>
                          </div>
                      </div>
                  </div>
                  <div class="col-md-3">
                      <div class="card">
                          <div class="card-body">
                              <h5 class="card-title">Livrées</h5>
                              <?php
                              $delivered_orders = array_filter($orders, function($order) {
                                  return $order['status'] == 'delivered';
                              });
                              ?>
                              <p class="card-text fs-2"><?php echo count($delivered_orders); ?></p>
                          </div>
                      </div>
                  </div>
                  <div class="col-md-3">
                      <div class="card">
                          <div class="card-body">
                              <h5 class="card-title">Chiffre d'affaires</h5>
                              <?php
                              $total_revenue = array_reduce($orders, function($carry, $order) {
                                  return $carry + (isset($order['total_amount']) ? $order['total_amount'] : 0);
                              }, 0);
                              ?>
                              <p class="card-text fs-2"><?php echo number_format($total_revenue, 2); ?> €</p>
                          </div>
                      </div>
                  </div>
              </div>

              <div class="table-responsive">
                  <table class="table table-striped table-sm">
                      <thead>
                          <tr>
                              <th>ID</th>
                              <th>Client</th>
                              <th>Email</th>
                              <th>Montant</th>
                              <th>Date</th>
                              <th>Statut</th>
                              <th>Actions</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php if (count($orders) > 0): ?>
                              <?php foreach ($orders as $order): ?>
                              <tr>
                                  <td><?php echo $order['id']; ?></td>
                                  <td><?php echo htmlspecialchars($order['username']); ?></td>
                                  <td><?php echo htmlspecialchars($order['email']); ?></td>
                                  <td><?php echo number_format(isset($order['total_amount']) ? $order['total_amount'] : 0, 2); ?> €</td>
                                  <td><?php echo isset($order['created_at']) ? date('d/m/Y H:i', strtotime($order['created_at'])) : 'N/A'; ?></td>
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
                                  </td>
                                  <td>
                                      <a href="javascript:void(0)" onclick="viewOrderDetails(<?php echo $order['id']; ?>)" class="text-decoration-none me-2">Détails</a>
                                      <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo $order['id']; ?>" class="text-decoration-none">Statut</a>
                                      
                                      <!-- Modal de mise à jour du statut -->
                                      <div class="modal fade" id="updateStatusModal<?php echo $order['id']; ?>" tabindex="-1" aria-labelledby="updateStatusModalLabel<?php echo $order['id']; ?>" aria-hidden="true">
                                          <div class="modal-dialog">
                                              <div class="modal-content">
                                                  <div class="modal-header">
                                                      <h5 class="modal-title" id="updateStatusModalLabel<?php echo $order['id']; ?>">Mettre à jour le statut de la commande #<?php echo $order['id']; ?></h5>
                                                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                  </div>
                                                  <div class="modal-body">
                                                      <form id="updateStatusForm<?php echo $order['id']; ?>" action="" method="POST">
                                                          <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                          <div class="mb-3">
                                                              <label for="status<?php echo $order['id']; ?>" class="form-label">Statut</label>
                                                              <select class="form-select" id="status<?php echo $order['id']; ?>" name="status">
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
                                                      <button type="submit" form="updateStatusForm<?php echo $order['id']; ?>" name="update_status" class="btn btn-primary">Mettre à jour</button>
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                  </td>
                              </tr>
                              <?php endforeach; ?>
                          <?php else: ?>
                              <tr>
                                  <td colspan="7" class="text-center">Aucune commande trouvée</td>
                              </tr>
                          <?php endif; ?>
                      </tbody>
                  </table>
              </div>
          </main>
      </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
      function viewOrderDetails(orderId) {
          // Rediriger vers la page de détails de la commande
          window.location.href = 'order_details.php?id=' + orderId;
      }
  </script>
</body>
</html>

