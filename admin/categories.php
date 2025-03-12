<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Récupérer la liste des catégories
$categories = [];
$query = "SELECT * FROM categories ORDER BY name ASC";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Gérer l'ajout d'une catégorie
if (isset($_POST['add_category'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    
    $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $description);
    $stmt->execute();
    
    header("Location: categories.php");
    exit();
}

// Gérer la suppression d'une catégorie
if (isset($_POST['delete_category'])) {
    $category_id = $_POST['category_id'];
    $conn->query("DELETE FROM categories WHERE id = " . intval($category_id));
    header("Location: categories.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories - Administration</title>
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1 class="h2">Gestion des Catégories</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus"></i> Ajouter une catégorie
                        </button>
                    </div>
                </div>

                <!-- Tableau -->
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-sm">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Nombre de produits</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): 
                                // Compter le nombre de produits dans cette catégorie
                                $count_query = "SELECT COUNT(*) as count FROM products WHERE category_id = " . $category['id'];
                                $count_result = $conn->query($count_query);
                                $count = 0;
                                if ($count_result && $count_row = $count_result->fetch_assoc()) {
                                    $count = $count_row['count'];
                                }
                            ?>
                            <tr>
                                <td><?php echo $category['id']; ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td><?php echo htmlspecialchars($category['description']); ?></td>
                                <td><?php echo $count; ?></td>
                                <td>
                                    <a href="javascript:void(0)" onclick="editCategory(<?php echo $category['id']; ?>)" class="text-decoration-none me-2">Modifier</a>
                                    <form action="" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?');">
                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                        <button type="submit" name="delete_category" class="btn btn-link text-decoration-none p-0">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Statistiques -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Catégories</h5>
                                <p class="card-text fs-2"><?php echo count($categories); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Catégories Populaires</h5>
                                <?php
                                $popular_category = "N/A";
                                $max_products = 0;
                                
                                foreach ($categories as $category) {
                                    $count_query = "SELECT COUNT(*) as count FROM products WHERE category_id = " . $category['id'];
                                    $count_result = $conn->query($count_query);
                                    if ($count_result && $count_row = $count_result->fetch_assoc()) {
                                        if ($count_row['count'] > $max_products) {
                                            $max_products = $count_row['count'];
                                            $popular_category = $category['name'];
                                        }
                                    }
                                }
                                ?>
                                <p class="card-text fs-2"><?php echo htmlspecialchars($popular_category); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Produits</h5>
                                <?php
                                $total_products = 0;
                                $products_query = "SELECT COUNT(*) as count FROM products";
                                $products_result = $conn->query($products_query);
                                if ($products_result && $products_row = $products_result->fetch_assoc()) {
                                    $total_products = $products_row['count'];
                                }
                                ?>
                                <p class="card-text fs-2"><?php echo $total_products; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal d'ajout de catégorie -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Ajouter une catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addCategoryForm" action="" method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom de la catégorie</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" form="addCategoryForm" name="add_category" class="btn btn-primary">Ajouter</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCategory(categoryId) {
            window.location.href = 'edit_category.php?id=' + categoryId;
        }
    </script>
</body>
</html>

