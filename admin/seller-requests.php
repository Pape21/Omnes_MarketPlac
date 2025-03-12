<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est un administrateur
requireAdmin();

// Traitement des actions (approbation/refus)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $comment = isset($_POST['comment']) ? $_POST['comment'] : '';
    
    if ($action === 'approve') {
        // Récupérer l'ID de l'utilisateur associé à la demande
        $sql = "SELECT user_id FROM seller_requests WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $user_id = $row['user_id'];
            
            // Mettre à jour le rôle de l'utilisateur
            $sql = "UPDATE users SET role = 'seller' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Mettre à jour le statut de la demande
            $sql = "UPDATE seller_requests SET status = 'approved', admin_comment = ?, processed_at = NOW(), processed_by = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $comment, $_SESSION['user_id'], $request_id);
            $stmt->execute();
            
            // Envoyer une notification à l'utilisateur
            $notification = "Félicitations ! Votre demande pour devenir vendeur a été approuvée.";
            addNotification($conn, $user_id, 'seller_request_approved', $notification);
            
            // Rediriger avec un message de succès
            header("Location: seller-requests.php?success=approved");
            exit();
        }
    } elseif ($action === 'reject') {
        // Récupérer l'ID de l'utilisateur associé à la demande
        $sql = "SELECT user_id FROM seller_requests WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $user_id = $row['user_id'];
            
            // Mettre à jour le statut de la demande
            $sql = "UPDATE seller_requests SET status = 'rejected', admin_comment = ?, processed_at = NOW(), processed_by = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $comment, $_SESSION['user_id'], $request_id);
            $stmt->execute();
            
            // Envoyer une notification à l'utilisateur
            $notification = "Votre demande pour devenir vendeur a été refusée. Raison : " . $comment;
            addNotification($conn, $user_id, 'seller_request_rejected', $notification);
            
            // Rediriger avec un message de succès
            header("Location: seller-requests.php?success=rejected");
            exit();
        }
    }
}

// Récupérer les demandes avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Construire la requête SQL avec les filtres
$where_clause = "";
$params = [];
$types = "";

if ($status_filter !== 'all') {
    $where_clause .= "WHERE sr.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $where_clause .= (empty($where_clause) ? "WHERE " : " AND ") . "(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Requête pour compter le nombre total de demandes
$count_sql = "SELECT COUNT(*) as total FROM seller_requests sr 
              JOIN users u ON sr.user_id = u.id 
              $where_clause";

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_requests = $total_row['total'];
$total_pages = ceil($total_requests / $limit);

// Requête pour récupérer les demandes avec pagination
$sql = "SELECT sr.*, u.username, u.email, u.first_name, u.last_name, u.profile_image 
        FROM seller_requests sr 
        JOIN users u ON sr.user_id = u.id 
        $where_clause 
        ORDER BY sr.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des demandes vendeur - Administration</title>
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
                    <h1 class="h2">Gestion des demandes vendeur</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php if ($_GET['success'] === 'approved'): ?>
                            <strong>Succès!</strong> La demande a été approuvée et l'utilisateur est maintenant un vendeur.
                        <?php elseif ($_GET['success'] === 'rejected'): ?>
                            <strong>Succès!</strong> La demande a été refusée.
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistiques -->
                <div class="row mb-4">
                    <?php
                    // Compter les demandes par statut
                    $stats_sql = "SELECT status, COUNT(*) as count FROM seller_requests GROUP BY status";
                    $stats_result = $conn->query($stats_sql);
                    $stats = [
                        'pending' => 0,
                        'approved' => 0,
                        'rejected' => 0
                    ];
                    
                    while ($row = $stats_result->fetch_assoc()) {
                        $stats[$row['status']] = $row['count'];
                    }
                    ?>
                    
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">En attente</h6>
                                        <h2 class="mb-0"><?php echo $stats['pending']; ?></h2>
                                    </div>
                                    <i class="fas fa-clock fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Approuvées</h6>
                                        <h2 class="mb-0"><?php echo $stats['approved']; ?></h2>
                                    </div>
                                    <i class="fas fa-check-circle fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card text-white bg-danger mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Refusées</h6>
                                        <h2 class="mb-0"><?php echo $stats['rejected']; ?></h2>
                                    </div>
                                    <i class="fas fa-times-circle fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtres et recherche -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="seller-requests.php" method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Statut</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approuvées</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Refusées</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="search" class="form-label">Recherche</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Nom, email, username..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Liste des demandes -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Liste des demandes (<?php echo $total_requests; ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" width="60">ID</th>
                                        <th scope="col">Utilisateur</th>
                                        <th scope="col">Date de demande</th>
                                        <th scope="col">Statut</th>
                                        <th scope="col" width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($requests)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">Aucune demande trouvée</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($requests as $request): ?>
                                            <tr>
                                                <td><?php echo $request['id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($request['profile_image'])): ?>
                                                            <img src="<?php echo $request['profile_image']; ?>" class="rounded-circle me-2" width="40" height="40" alt="Profile">
                                                        <?php else: ?>
                                                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                                <?php echo strtoupper(substr($request['first_name'], 0, 1) . substr($request['last_name'], 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="fw-bold"><?php echo $request['first_name'] . ' ' . $request['last_name']; ?></div>
                                                            <small class="text-muted"><?php echo $request['email']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($request['status'] === 'pending'): ?>
                                                        <span class="badge bg-warning text-dark">En attente</span>
                                                    <?php elseif ($request['status'] === 'approved'): ?>
                                                        <span class="badge bg-success">Approuvée</span>
                                                    <?php elseif ($request['status'] === 'rejected'): ?>
                                                        <span class="badge bg-danger">Refusée</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="seller-request-details.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <?php if ($request['status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $request['id']; ?>">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $request['id']; ?>">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Modal d'approbation -->
                                                    <div class="modal fade" id="approveModal<?php echo $request['id']; ?>" tabindex="-1" aria-labelledby="approveModalLabel<?php echo $request['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="POST" action="seller-requests.php">
                                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                    <input type="hidden" name="action" value="approve">
                                                                    
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="approveModalLabel<?php echo $request['id']; ?>">Approuver la demande</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p>Êtes-vous sûr de vouloir approuver la demande de <strong><?php echo $request['first_name'] . ' ' . $request['last_name']; ?></strong> ?</p>
                                                                        <p>L'utilisateur deviendra un vendeur et pourra publier des produits sur la plateforme.</p>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label for="approveComment<?php echo $request['id']; ?>" class="form-label">Commentaire (optionnel)</label>
                                                                            <textarea class="form-control" id="approveComment<?php echo $request['id']; ?>" name="comment" rows="3" placeholder="Ajouter un commentaire ou des instructions pour le vendeur..."></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                        <button type="submit" class="btn btn-success">Approuver</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Modal de refus -->
                                                    <div class="modal fade" id="rejectModal<?php echo $request['id']; ?>" tabindex="-1" aria-labelledby="rejectModalLabel<?php echo $request['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="POST" action="seller-requests.php">
                                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                    <input type="hidden" name="action" value="reject">
                                                                    
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="rejectModalLabel<?php echo $request['id']; ?>">Refuser la demande</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p>Êtes-vous sûr de vouloir refuser la demande de <strong><?php echo $request['first_name'] . ' ' . $request['last_name']; ?></strong> ?</p>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label for="rejectComment<?php echo $request['id']; ?>" class="form-label">Motif du refus <span class="text-danger">*</span></label>
                                                                            <textarea class="form-control" id="rejectComment<?php echo $request['id']; ?>" name="comment" rows="3" placeholder="Expliquez pourquoi la demande est refusée..." required></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                        <button type="submit" class="btn btn-danger">Refuser</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
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
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script>
    $(document).ready(function() {
        // Auto-submit form when status changes
        $('#status').change(function() {
            $(this).closest('form').submit();
        });
    });
    </script>
</body>
</html>

