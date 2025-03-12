<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
  header("Location: ../login.php");
  exit();
}

$success_message = '';
$error_message = '';

// Traitement de l'approbation d'une demande
if (isset($_POST['approve_request']) && isset($_POST['request_id'])) {
  $request_id = $_POST['request_id'];
  $user_id = $_POST['user_id'];
  
  // Mettre à jour le statut de la demande
  $stmt = $conn->prepare("UPDATE seller_requests SET status = 'approved', updated_at = NOW() WHERE id = ?");
  $stmt->bind_param("i", $request_id);
  
  if ($stmt->execute()) {
    // Mettre à jour le rôle de l'utilisateur
    $stmt = $conn->prepare("UPDATE users SET role = 'seller' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
      $success_message = "La demande a été approuvée et l'utilisateur a été promu en vendeur.";
      
      // Envoyer une notification à l'utilisateur
      $notification_message = "Félicitations ! Votre demande de compte vendeur a été approuvée. Vous pouvez maintenant accéder à votre espace vendeur.";
      sendNotification($conn, $user_id, $notification_message, "seller/index.php");
    } else {
      $error_message = "Erreur lors de la mise à jour du rôle de l'utilisateur.";
    }
  } else {
    $error_message = "Erreur lors de la mise à jour du statut de la demande.";
  }
}

// Traitement du refus d'une demande
if (isset($_POST['reject_request']) && isset($_POST['request_id'])) {
  $request_id = $_POST['request_id'];
  $user_id = $_POST['user_id'];
  $admin_comment = $_POST['admin_comment'];
  
  // Mettre à jour le statut de la demande
  $stmt = $conn->prepare("UPDATE seller_requests SET status = 'rejected', admin_comment = ?, updated_at = NOW() WHERE id = ?");
  $stmt->bind_param("si", $admin_comment, $request_id);
  
  if ($stmt->execute()) {
    $success_message = "La demande a été refusée.";
    
    // Envoyer une notification à l'utilisateur
    $notification_message = "Votre demande de compte vendeur a été refusée. Consultez votre profil pour plus d'informations.";
    sendNotification($conn, $user_id, $notification_message, "account.php#seller-request");
  } else {
    $error_message = "Erreur lors de la mise à jour du statut de la demande.";
  }
}

// Récupérer les demandes en attente
$pending_requests = [];
$sql = "SELECT sr.*, u.username, u.email, u.first_name, u.last_name 
        FROM seller_requests sr 
        JOIN users u ON sr.user_id = u.id 
        WHERE sr.status = 'pending' 
        ORDER BY sr.created_at ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
  }
}

// Récupérer l'historique des demandes traitées
$processed_requests = [];
$sql = "SELECT sr.*, u.username, u.email, u.first_name, u.last_name 
        FROM seller_requests sr 
        JOIN users u ON sr.user_id = u.id 
        WHERE sr.status != 'pending' 
        ORDER BY sr.updated_at DESC 
        LIMIT 20";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $processed_requests[] = $row;
  }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion des demandes vendeur - Omnes MarketPlace</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
  <div class="container-fluid">
      <div class="row">
          <!-- Sidebar -->
          <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
              <div class="position-sticky pt-3">
                  <div class="text-center mb-4">
                      <h5 class="text-white">Omnes MarketPlace</h5>
                      <p class="text-white-50">Administration</p>
                  </div>
                  <ul class="nav flex-column">
                      <li class="nav-item">
                          <a class="nav-link" href="index.php">
                              <i class="fas fa-tachometer-alt"></i> Tableau de bord
                          </a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link" href="users.php">
                              <i class="fas fa-users"></i> Utilisateurs
                          </a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link" href="sellers.php">
                              <i class="fas fa-store"></i> Vendeurs
                          </a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link active" href="seller_requests.php">
                              <i class="fas fa-user-plus"></i> Demandes vendeur
                              <?php if (count($pending_requests) > 0): ?>
                                  <span class="badge bg-danger"><?php echo count($pending_requests); ?></span>
                              <?php endif; ?>
                          </a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link" href="products.php">
                              <i class="fas fa-box"></i> Produits
                          </a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link" href="categories.php">
                              <i class="fas fa-tags"></i> Catégories
                          </a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link" href="orders.php">
                              <i class="fas fa-shopping-cart"></i> Commandes
                          </a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link" href="reports.php">
                              <i class="fas fa-chart-bar"></i> Rapports
                          </a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link" href="settings.php">
                              <i class="fas fa-cog"></i> Paramètres
                          </a>
                      </li>
                  </ul>
                  
                  <hr class="text-white-50">
                  
                  <ul class="nav flex-column">
                      <li class="nav-item">
                          <a class="nav-link" href="../index.php">
                              <i class="fas fa-home"></i> Retour au site
                          </a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link" href="../logout.php">
                              <i class="fas fa-sign-out-alt"></i> Déconnexion
                          </a>
                      </li>
                  </ul>
              </div>
          </nav>
          
          <!-- Main content -->
          <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
              <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                  <h1 class="h2">Gestion des demandes de compte vendeur</h1>
              </div>
              
              <?php if (!empty($success_message)): ?>
                  <div class="alert alert-success alert-dismissible fade show" role="alert">
                      <?php echo $success_message; ?>
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
              <?php endif; ?>
              
              <?php if (!empty($error_message)): ?>
                  <div class="alert alert-danger alert-dismissible fade show" role="alert">
                      <?php echo $error_message; ?>
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
              <?php endif; ?>
              
              <!-- Demandes en attente -->
              <div class="card shadow mb-4">
                  <div class="card-header py-3 d-flex justify-content-between align-items-center">
                      <h6 class="m-0 font-weight-bold text-primary">Demandes en attente</h6>
                      <span class="badge bg-primary"><?php echo count($pending_requests); ?> demande(s)</span>
                  </div>
                  <div class="card-body">
                      <?php if (empty($pending_requests)): ?>
                          <div class="alert alert-info">
                              <p class="mb-0">Aucune demande en attente.</p>
                          </div>
                      <?php else: ?>
                          <div class="table-responsive">
                              <table class="table table-bordered" width="100%" cellspacing="0">
                                  <thead>
                                      <tr>
                                          <th>ID</th>
                                          <th>Utilisateur</th>
                                          <th>Date de demande</th>
                                          <th>Actions</th>
                                      </tr>
                                  </thead>
                                  <tbody>
                                      <?php foreach ($pending_requests as $request): ?>
                                          <tr>
                                              <td><?php echo $request['id']; ?></td>
                                              <td>
                                                  <strong><?php echo $request['username']; ?></strong><br>
                                                  <small><?php echo $request['email']; ?></small>
                                              </td>
                                              <td><?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></td>
                                              <td>
                                                  <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewRequestModal<?php echo $request['id']; ?>">
                                                      <i class="fas fa-eye"></i> Voir
                                                  </button>
                                                  <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveRequestModal<?php echo $request['id']; ?>">
                                                      <i class="fas fa-check"></i> Approuver
                                                  </button>
                                                  <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectRequestModal<?php echo $request['id']; ?>">
                                                      <i class="fas fa-times"></i> Refuser
                                                  </button>
                                              </td>
                                          </tr>
                                          
                                          <!-- Modal pour voir les détails -->
                                          <div class="modal fade" id="viewRequestModal<?php echo $request['id']; ?>" tabindex="-1" aria-labelledby="viewRequestModalLabel<?php echo $request['id']; ?>" aria-hidden="true">
                                              <div class="modal-dialog modal-lg">
                                                  <div class="modal-content">
                                                      <div class="modal-header">
                                                          <h5 class="modal-title" id="viewRequestModalLabel<?php echo $request['id']; ?>">Détails de la demande #<?php echo $request['id']; ?></h5>
                                                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                      </div>
                                                      <div class="modal-body">
                                                          <div class="row mb-3">
                                                              <div class="col-md-6">
                                                                  <h6>Informations utilisateur</h6>
                                                                  <p><strong>Nom d'utilisateur:</strong> <?php echo $request['username']; ?></p>
                                                                  <p><strong>Email:</strong> <?php echo $request['email']; ?></p>
                                                                  <p><strong>Nom complet:</strong> <?php echo $request['first_name'] . ' ' . $request['last_name']; ?></p>
                                                              </div>
                                                              <div class="col-md-6">
                                                                  <h6>Informations demande</h6>
                                                                  <p><strong>Date de demande:</strong> <?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></p>
                                                                  <p><strong>Statut:</strong> <span class="badge bg-warning">En attente</span></p>
                                                              </div>
                                                          </div>
                                                          
                                                          <div class="mb-3">
                                                              <h6>Motivation</h6>
                                                              <div class="p-3 bg-light rounded">
                                                                  <?php echo nl2br($request['motivation']); ?>
                                                              </div>
                                                          </div>
                                                          
                                                          <div class="mb-3">
                                                              <h6>Informations professionnelles</h6>
                                                              <div class="p-3 bg-light rounded">
                                                                  <?php echo nl2br($request['business_info']); ?>
                                                              </div>
                                                          </div>
                                                      </div>
                                                      <div class="modal-footer">
                                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                          <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveRequestModal<?php echo $request['id']; ?>" data-bs-dismiss="modal">Approuver</button>
                                                          <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectRequestModal<?php echo $request['id']; ?>" data-bs-dismiss="modal">Refuser</button>
                                                      </div>
                                                  </div>
                                              </div>
                                          </div>
                                          
                                          <!-- Modal pour approuver -->
                                          <div class="modal fade" id="approveRequestModal<?php echo $request['id']; ?>" tabindex="-1" aria-labelledby="approveRequestModalLabel<?php echo $request['id']; ?>" aria-hidden="true">
                                              <div class="modal-dialog">
                                                  <div class="modal-content">
                                                      <div class="modal-header">
                                                          <h5 class="modal-title" id="approveRequestModalLabel<?php echo $request['id']; ?>">Approuver la demande</h5>
                                                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                      </div>
                                                      <div class="modal-body">
                                                          <p>Êtes-vous sûr de vouloir approuver la demande de compte vendeur de <strong><?php echo $request['username']; ?></strong> ?</p>
                                                          <p>L'utilisateur sera promu en vendeur et pourra accéder à l'espace vendeur.</p>
                                                      </div>
                                                      <div class="modal-footer">
                                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                          <form method="POST" action="seller_requests.php">
                                                              <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                              <input type="hidden" name="user_id" value="<?php echo $request['user_id']; ?>">
                                                              <button type="submit" name="approve_request" class="btn btn-success">Approuver</button>
                                                          </form>
                                                      </div>
                                                  </div>
                                              </div>
                                          </div>
                                          
                                          <!-- Modal pour refuser -->
                                          <div class="modal fade" id="rejectRequestModal<?php echo $request['id']; ?>" tabindex="-1" aria-labelledby="rejectRequestModalLabel<?php echo $request['id']; ?>" aria-hidden="true">
                                              <div class="modal-dialog">
                                                  <div class="modal-content">
                                                      <div class="modal-header">
                                                          <h5 class="modal-title" id="rejectRequestModalLabel<?php echo $request['id']; ?>">Refuser la demande</h5>
                                                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                      </div>
                                                      <form method="POST" action="seller_requests.php">
                                                          <div class="modal-body">
                                                              <p>Vous êtes sur le point de refuser la demande de compte vendeur de <strong><?php echo $request['username']; ?></strong>.</p>
                                                              
                                                              <div class="mb-3">
                                                                  <label for="admin_comment" class="form-label">Motif du refus</label>
                                                                  <textarea class="form-control" id="admin_comment" name="admin_comment" rows="3" required></textarea>
                                                                  <div class="form-text">Ce commentaire sera visible par l'utilisateur pour l'aider à comprendre pourquoi sa demande a été refusée.</div>
                                                              </div>
                                                          </div>
                                                          <div class="modal-footer">
                                                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                              <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                              <input type="hidden" name="user_id" value="<?php echo $request['user_id']; ?>">
                                                              <button type="submit" name="reject_request" class="btn btn-danger">Refuser</button>
                                                          </div>
                                                      </form>
                                                  </div>
                                              </div>
                                          </div>
                                      <?php endforeach; ?>
                                  </tbody>
                              </table>
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
              
              <!-- Historique des demandes traitées -->
              <div class="card shadow mb-4">
                  <div class="card-header py-3">
                      <h6 class="m-0 font-weight-bold text-primary">Historique des demandes traitées</h6>
                  </div>
                  <div class="card-body">
                      <?php if (empty($processed_requests)): ?>
                          <div class="alert alert-info">
                              <p class="mb-0">Aucune demande traitée.</p>
                          </div>
                      <?php else: ?>
                          <div class="table-responsive">
                              <table class="table table-bordered" width="100%" cellspacing="0">
                                  <thead>
                                      <tr>
                                          <th>ID</th>
                                          <th>Utilisateur</th>
                                          <th>Date de demande</th>
                                          <th>Date de traitement</th>
                                          <th>Statut</th>
                                          <th>Actions</th>
                                      </tr>
                                  </thead>
                                  <tbody>
                                      <?php foreach ($processed_requests as $request): ?>
                                          <tr>
                                              <td><?php echo $request['id']; ?></td>
                                              <td>
                                                  <strong><?php echo $request['username']; ?></strong><br>
                                                  <small><?php echo $request['email']; ?></small>
                                              </td>
                                              <td><?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></td>
                                              <td><?php echo date('d/m/Y H:i', strtotime($request['updated_at'])); ?></td>
                                              <td>
                                                  <?php if ($request['status'] == 'approved'): ?>
                                                      <span class="badge bg-success">Approuvée</span>
                                                  <?php else: ?>
                                                      <span class="badge bg-danger">Refusée</span>
                                                  <?php endif; ?>
                                              </td>
                                              <td>
                                                  <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewHistoryModal<?php echo $request['id']; ?>">
                                                      <i class="fas fa-eye"></i> Détails
                                                  </button>
                                              </td>
                                          </tr>
                                          
                                          <!-- Modal pour voir les détails -->
                                          <div class="modal fade" id="viewHistoryModal<?php echo $request['id']; ?>" tabindex="-1" aria-labelledby="viewHistoryModalLabel<?php echo $request['id']; ?>" aria-hidden="true">
                                              <div class="modal-dialog modal-lg">
                                                  <div class="modal-content">
                                                      <div class="modal-header">
                                                          <h5 class="modal-title" id="viewHistoryModalLabel<?php echo $request['id']; ?>">Détails de la demande #<?php echo $request['id']; ?></h5>
                                                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                      </div>
                                                      <div class="modal-body">
                                                          <div class="row mb-3">
                                                              <div class="col-md-6">
                                                                  <h6>Informations utilisateur</h6>
                                                                  <p><strong>Nom d'utilisateur:</strong> <?php echo $request['username']; ?></p>
                                                                  <p><strong>Email:</strong> <?php echo $request['email']; ?></p>
                                                                  <p><strong>Nom complet:</strong> <?php echo $request['first_name'] . ' ' . $request['last_name']; ?></p>
                                                              </div>
                                                              <div class="col-md-6">
                                                                  <h6>Informations demande</h6>
                                                                  <p><strong>Date de demande:</strong> <?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></p>
                                                                  <p><strong>Date de traitement:</strong> <?php echo date('d/m/Y H:i', strtotime($request['updated_at'])); ?></p>
                                                                  <p><strong>Statut:</strong> 
                                                                      <?php if ($request['status'] == 'approved'): ?>
                                                                          <span class="badge bg-success">Approuvée</span>
                                                                      <?php else: ?>
                                                                          <span class="badge bg-danger">Refusée</span>
                                                                      <?php endif; ?>
                                                                  </p>
                                                              </div>
                                                          </div>
                                                          
                                                          <div class="mb-3">
                                                              <h6>Motivation</h6>
                                                              <div class="p-3 bg-light rounded">
                                                                  <?php echo nl2br($request['motivation']); ?>
                                                              </div>
                                                          </div>
                                                          
                                                          <div class="mb-3">
                                                              <h6>Informations professionnelles</h6>
                                                              <div class="p-3 bg-light rounded">
                                                                  <?php echo nl2br($request['business_info']); ?>
                                                              </div>
                                                          </div>
                                                          
                                                          <?php if ($request['status'] == 'rejected' && !empty($request['admin_comment'])): ?>
                                                              <div class="mb-3">
                                                                  <h6>Motif du refus</h6>
                                                                  <div class="p-3 bg-light rounded">
                                                                      <?php echo nl2br($request['admin_comment']); ?>
                                                                  </div>
                                                              </div>
                                                          <?php endif; ?>
                                                      </div>
                                                      <div class="modal-footer">
                                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                      </div>
                                                  </div>
                                              </div>
                                          </div>
                                      <?php endforeach; ?>
                                  </tbody>
                              </table>
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
          </main>
      </div>
  </div>
</body>
</html>

