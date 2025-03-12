<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
  header("Location: ../login.php");
  exit();
}

// Récupérer la liste des utilisateurs
$users = [];
$query = "SELECT * FROM users ORDER BY created_at DESC";
$result = $conn->query($query);

if ($result) {
  while ($row = $result->fetch_assoc()) {
      $users[] = $row;
  }
}

// Gérer la suppression d'un utilisateur
if (isset($_POST['delete_user'])) {
  $user_id = $_POST['user_id'];
  $conn->query("DELETE FROM users WHERE id = " . intval($user_id));
  header("Location: users.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion des Utilisateurs - Administration</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
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
                  <h1 class="h2">Gestion des Utilisateurs</h1>
                  <div class="btn-toolbar mb-2 mb-md-0">
                      <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                          <i class="fas fa-plus"></i> Ajouter un utilisateur
                      </button>
                  </div>
              </div>

              <!-- Statistiques -->
              <div class="row mb-4">
                  <div class="col-md-3">
                      <div class="card">
                          <div class="card-body">
                              <h5 class="card-title">Total Utilisateurs</h5>
                              <p class="card-text fs-2"><?php echo count($users); ?></p>
                          </div>
                      </div>
                  </div>
                  <div class="col-md-3">
                      <div class="card">
                          <div class="card-body">
                              <h5 class="card-title">Acheteurs</h5>
                              <?php
                              $buyers = array_filter($users, function($user) {
                                  return $user['role'] == 'user';
                              });
                              ?>
                              <p class="card-text fs-2"><?php echo count($buyers); ?></p>
                          </div>
                      </div>
                  </div>
                  <div class="col-md-3">
                      <div class="card">
                          <div class="card-body">
                              <h5 class="card-title">Vendeurs</h5>
                              <?php
                              $sellers = array_filter($users, function($user) {
                                  return $user['role'] == 'seller';
                              });
                              ?>
                              <p class="card-text fs-2"><?php echo count($sellers); ?></p>
                          </div>
                      </div>
                  </div>
                  <div class="col-md-3">
                      <div class="card">
                          <div class="card-body">
                              <h5 class="card-title">Administrateurs</h5>
                              <?php
                              $admins = array_filter($users, function($user) {
                                  return $user['role'] == 'admin';
                              });
                              ?>
                              <p class="card-text fs-2"><?php echo count($admins); ?></p>
                          </div>
                      </div>
                  </div>
              </div>

              <div class="table-responsive">
                  <table class="table table-striped table-sm">
                      <thead>
                          <tr>
                              <th>ID</th>
                              <th>Nom d'utilisateur</th>
                              <th>Email</th>
                              <th>Rôle</th>
                              <th>Date d'inscription</th>
                              <th>Actions</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php foreach ($users as $user): ?>
                          <tr>
                              <td><?php echo $user['id']; ?></td>
                              <td><?php echo htmlspecialchars($user['username']); ?></td>
                              <td><?php echo htmlspecialchars($user['email']); ?></td>
                              <td>
                                  <?php if ($user['role'] == 'admin'): ?>
                                      <span class="badge bg-danger">Administrateur</span>
                                  <?php elseif ($user['role'] == 'seller'): ?>
                                      <span class="badge bg-success">Vendeur</span>
                                  <?php else: ?>
                                      <span class="badge bg-info">Acheteur</span>
                                  <?php endif; ?>
                              </td>
                              <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                              <td>
                                  <a href="javascript:void(0)" onclick="editUser(<?php echo $user['id']; ?>)" class="text-decoration-none me-2">Modifier</a>
                                  <form action="" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                      <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                      <button type="submit" name="delete_user" class="btn btn-link text-decoration-none p-0">Supprimer</button>
                                  </form>
                              </td>
                          </tr>
                          <?php endforeach; ?>
                      </tbody>
                  </table>
              </div>
          </main>
      </div>
  </div>

  <!-- Modal d'ajout d'utilisateur -->
  <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="addUserModalLabel">Ajouter un utilisateur</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                  <form id="addUserForm" action="add_user.php" method="POST">
                      <div class="mb-3">
                          <label for="username" class="form-label">Nom d'utilisateur</label>
                          <input type="text" class="form-control" id="username" name="username" required>
                      </div>
                      <div class="mb-3">
                          <label for="email" class="form-label">Email</label>
                          <input type="email" class="form-control" id="email" name="email" required>
                      </div>
                      <div class="mb-3">
                          <label for="password" class="form-label">Mot de passe</label>
                          <input type="password" class="form-control" id="password" name="password" required>
                      </div>
                      <div class="mb-3">
                          <label for="role" class="form-label">Rôle</label>
                          <select class="form-select" id="role" name="role" required>
                              <option value="user">Acheteur</option>
                              <option value="seller">Vendeur</option>
                              <option value="admin">Administrateur</option>
                          </select>
                      </div>
                  </form>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                  <button type="submit" form="addUserForm" class="btn btn-primary">Ajouter</button>
              </div>
          </div>
      </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
      function editUser(userId) {
          // Rediriger vers la page d'édition
          window.location.href = 'edit_user.php?id=' + userId;
      }
  </script>
</body>
</html>

