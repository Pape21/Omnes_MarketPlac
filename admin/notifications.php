<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est un administrateur
requireAdmin();

// Traitement de l'envoi d'une notification
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_notification'])) {
    $target = $_POST['target'];
    $title = $_POST['title'];
    $message = $_POST['message'];
    $link = isset($_POST['link']) ? $_POST['link'] : '';
    
    // Validation
    if (empty($title) || empty($message)) {
        $error_message = "Le titre et le message sont obligatoires.";
    } else {
        $count = 0;
        
        if ($target === 'all') {
            // Envoyer à tous les utilisateurs
            $sql = "SELECT id FROM users";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                if (addNotification($conn, $row['id'], 'admin_message', $title . ': ' . $message, $link)) {
                    $count++;
                }
            }
            
            $success_message = "Notification envoyée à $count utilisateurs avec succès.";
        } elseif ($target === 'sellers') {
            // Envoyer à tous les vendeurs
            $count = notifyAllSellers($conn, 'admin_message', $title . ': ' . $message, $link);
            $success_message = "Notification envoyée à $count vendeurs avec succès.";
        } elseif ($target === 'buyers') {
            // Envoyer à tous les acheteurs
            $sql = "SELECT id FROM users WHERE role = 'buyer'";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                if (addNotification($conn, $row['id'], 'admin_message', $title . ': ' . $message, $link)) {
                    $count++;
                }
            }
            
            $success_message = "Notification envoyée à $count acheteurs avec succès.";
        } elseif (is_numeric($target)) {
            // Envoyer à un utilisateur spécifique
            if (addNotification($conn, $target, 'admin_message', $title . ': ' . $message, $link)) {
                $sql = "SELECT username FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $target);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                
                $success_message = "Notification envoyée à " . $user['username'] . " avec succès.";
            } else {
                $error_message = "Erreur lors de l'envoi de la notification.";
            }
        }
        
        // Enregistrer l'action
        $admin_id = $_SESSION['user_id'];
        $log_message = "Notification envoyée par l'administrateur #$admin_id à $count utilisateurs: $title";
        file_put_contents('../logs/admin_actions.log', date('Y-m-d H:i:s') . " - $log_message\n", FILE_APPEND);
    }
}

// Récupérer les notifications récentes avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "SELECT n.*, u.username, u.first_name, u.last_name 
        FROM notifications n 
        JOIN users u ON n.user_id = u.id 
        WHERE n.type = 'admin_message' 
        ORDER BY n.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);

// Compter le nombre total de notifications
$sql = "SELECT COUNT(*) as total FROM notifications WHERE type = 'admin_message'";
$result = $conn->query($sql);
$total_notifications = $result->fetch_assoc()['total'];
$total_pages = ceil($total_notifications / $limit);

// Récupérer les utilisateurs pour le sélecteur
$sql = "SELECT id, username, first_name, last_name, role FROM users ORDER BY username";
$result = $conn->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des notifications - Administration</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include("includes/admin_header.php"); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include("includes/admin_sidebar.php"); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestion des notifications</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                            </a>
                        </div>
                    </div>
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
                
                <div class="row">
                    <!-- Formulaire d'envoi de notification -->
                    <div class="col-md-5">
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Envoyer une notification</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="notifications.php">
                                    <div class="mb-3">
                                        <label for="target" class="form-label">Destinataires</label>
                                        <select class="form-select" id="target" name="target" required>
                                            <option value="" selected disabled>Sélectionner les destinataires</option>
                                            <option value="all">Tous les utilisateurs</option>
                                            <option value="sellers">Tous les vendeurs</option>
                                            <option value="buyers">Tous les acheteurs</option>
                                            <optgroup label="Utilisateur spécifique">
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>">
                                                        <?php echo $user['username']; ?> (<?php echo $user['first_name'] . ' ' . $user['last_name']; ?>) - <?php echo ucfirst($user['role']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Titre</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="message" class="form-label">Message</label>
                                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="link" class="form-label">Lien (optionnel)</label>
                                        <input type="text" class="form-control" id="link" name="link" placeholder="https://...">
                                        <div class="form-text">URL vers laquelle l'utilisateur sera redirigé en cliquant sur la notification.</div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="send_notification" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i> Envoyer la notification
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Modèles de notification</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <button type="button" class="list-group-item list-group-item-action" data-title="Nouvelle fonctionnalité" data-message="Nous avons ajouté une nouvelle fonctionnalité sur la plateforme. Consultez votre tableau de bord pour en savoir plus.">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Nouvelle fonctionnalité</h6>
                                            <small>Tous</small>
                                        </div>
                                        <p class="mb-1 text-truncate">Nous avons ajouté une nouvelle fonctionnalité...</p>
                                    </button>
                                    
                                    <button type="button" class="list-group-item list-group-item-action" data-title="Rappel important" data-message="N'oubliez pas de mettre à jour vos informations de paiement avant la fin du mois.">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Rappel important</h6>
                                            <small>Vendeurs</small>
                                        </div>
                                        <p class="mb-1 text-truncate">N'oubliez pas de mettre à jour vos informations...</p>
                                    </button>
                                    
                                    <button type="button" class="list-group-item list-group-item-action" data-title="Promotion spéciale" data-message="Profitez de notre promotion spéciale ce week-end avec des réductions exclusives sur une sélection de produits.">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Promotion spéciale</h6>
                                            <small>Acheteurs</small>
                                        </div>
                                        <p class="mb-1 text-truncate">Profitez de notre promotion spéciale ce week-end...</p>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Historique des notifications -->
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Historique des notifications</h5>
                                <span class="badge bg-primary"><?php echo $total_notifications; ?> au total</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Destinataire</th>
                                                <th>Message</th>
                                                <th>Date</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($notifications)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4">Aucune notification envoyée</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($notifications as $notification): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div>
                                                                    <div class="fw-bold"><?php echo $notification['first_name'] . ' ' . $notification['last_name']; ?></div>
                                                                    <small class="text-muted"><?php echo $notification['username']; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($notification['message']); ?>
                                                            <?php if (!empty($notification['link'])): ?>
                                                                <br><small><a href="<?php echo $notification['link']; ?>" target="_blank"><?php echo $notification['link']; ?></a></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?></td>
                                                        <td>
                                                            <?php if ($notification['is_read']): ?>
                                                                <span class="badge bg-success">Lue</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Non lue</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="card-footer bg-white">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center mb-0">
                                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
    $(document).ready(function() {
        // Remplir le formulaire avec le modèle sélectionné
        $('.list-group-item').click(function() {
            var title = $(this).data('title');
            var message = $(this).data('message');
            
            $('#title').val(title);
            $('#message').val(message);
        });
    });
    </script>
</body>
</html>

