<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un vendeur
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'seller' && $_SESSION['role'] !== 'admin')) {
    header("Location: ../login.php");
    exit();
}

$success = "";
$error = "";

// Récupérer les catégories pour le formulaire
$categories = getCategories($conn);

// Traitement du formulaire d'ajout de produit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    // Récupérer et valider les données
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $sale_type = isset($_POST['sale_type']) ? $_POST['sale_type'] : 'immediate';
    $auction_end = null;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $qualities = isset($_POST['qualities']) ? json_encode(explode("\n", trim($_POST['qualities']))) : '[]';
    $defects = isset($_POST['defects']) ? json_encode(explode("\n", trim($_POST['defects']))) : '[]';
    
    // Validation des données
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Le nom du produit est requis";
    }
    
    if (empty($description)) {
        $errors[] = "La description du produit est requise";
    }
    
    if ($price <= 0) {
        $errors[] = "Le prix doit être supérieur à 0";
    }
    
    if ($stock < 0) {
        $errors[] = "Le stock ne peut pas être négatif";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Veuillez sélectionner une catégorie";
    }
    
    // Validation spécifique pour les enchères
    if ($sale_type == 'auction') {
        if (empty($_POST['auction_end'])) {
            $errors[] = "La date de fin d'enchère est requise";
        } else {
            $auction_end = $_POST['auction_end'];
            $auction_end_timestamp = strtotime($auction_end);
            
            if ($auction_end_timestamp <= time()) {
                $errors[] = "La date de fin d'enchère doit être dans le futur";
            }
        }
    }
    
    // Traitement de l'image principale
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errors[] = "Format d'image non autorisé. Formats acceptés: " . implode(', ', $allowed);
        } else {
            // Créer le dossier d'upload s'il n'existe pas
            $upload_dir = "../images/products/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Générer un nom de fichier unique
            $new_filename = uniqid() . '.' . $ext;
            $image_path = "/images/products/" . $new_filename;
            $upload_path = $upload_dir . $new_filename;
            
            // Déplacer le fichier uploadé
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $errors[] = "Erreur lors de l'upload de l'image";
            }
        }
    } else {
        $errors[] = "L'image du produit est requise";
    }
    
    // Traitement des images additionnelles (si présentes)
    $additional_images = [];
    if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
        $file_count = count($_FILES['additional_images']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['additional_images']['error'][$i] == 0) {
                $filename = $_FILES['additional_images']['name'][$i];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed)) {
                    $errors[] = "Format d'image additionnelle non autorisé: " . $filename;
                    continue;
                }
                
                // Générer un nom de fichier unique
                $new_filename = uniqid() . '_' . $i . '.' . $ext;
                $add_image_path = "/images/products/" . $new_filename;
                $upload_path = $upload_dir . $new_filename;
                
                // Déplacer le fichier uploadé
                if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$i], $upload_path)) {
                    $additional_images[] = $add_image_path;
                } else {
                    $errors[] = "Erreur lors de l'upload de l'image additionnelle: " . $filename;
                }
            }
        }
    }
    
    // Traitement de la vidéo (si présente)
    $video_path = null;
    if (isset($_FILES['video']) && $_FILES['video']['error'] == 0) {
        $allowed_video = ['mp4', 'webm', 'ogg'];
        $filename = $_FILES['video']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed_video)) {
            $errors[] = "Format de vidéo non autorisé. Formats acceptés: " . implode(', ', $allowed_video);
        } else {
            // Créer le dossier d'upload s'il n'existe pas
            $upload_dir = "../videos/products/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Générer un nom de fichier unique
            $new_filename = uniqid() . '.' . $ext;
            $video_path = "/videos/products/" . $new_filename;
            $upload_path = $upload_dir . $new_filename;
            
            // Déplacer le fichier uploadé
            if (!move_uploaded_file($_FILES['video']['tmp_name'], $upload_path)) {
                $errors[] = "Erreur lors de l'upload de la vidéo";
            }
        }
    }
    
    // Si aucune erreur, ajouter le produit
    if (empty($errors)) {
        $seller_id = $_SESSION['user_id'];
        $additional_images_json = !empty($additional_images) ? json_encode($additional_images) : null;
        
        // Préparer la requête SQL
        $sql = "INSERT INTO products (name, description, price, stock, image, additional_images, video, category_id, seller_id, sale_type, auction_end, featured, qualities, defects, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdisssiisisis", $name, $description, $price, $stock, $image_path, $additional_images_json, $video_path, $category_id, $seller_id, $sale_type, $auction_end, $featured, $qualities, $defects);
        
        if ($stmt->execute()) {
            $product_id = $stmt->insert_id;
            $success = "Produit ajouté avec succès! <a href='../product.php?id=$product_id' class='alert-link'>Voir le produit</a>";
            
            // Réinitialiser le formulaire
            $_POST = [];
        } else {
            $error = "Erreur lors de l'ajout du produit: " . $stmt->error;
        }
    } else {
        $error = "Veuillez corriger les erreurs suivantes:<br>" . implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Espace Vendeur</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/seller.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="d-flex align-items-center px-3 mb-3 text-white">
                        <i class="fas fa-store me-2"></i>
                        <span class="fs-5">Espace Vendeur</span>
                    </div>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>" href="products.php">
                                <i class="fas fa-box me-2"></i>
                                Produits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'add_product.php' ? 'active' : ''; ?>" href="add_product.php">
                                <i class="fas fa-plus me-2"></i>
                                Ajouter un produit
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" href="orders.php">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Commandes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'negotiations.php' ? 'active' : ''; ?>" href="negotiations.php">
                                <i class="fas fa-comments-dollar me-2"></i>
                                Négociations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'auctions.php' ? 'active' : ''; ?>" href="auctions.php">
                                <i class="fas fa-gavel me-2"></i>
                                Enchères
                            </a>
                        </li>
                    </ul>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Retour au site
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Ajouter un nouveau produit</h1>
                </div>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Informations du produit</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="add_product.php" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nom du produit *</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description *</label>
                                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                        <div class="form-text">Décrivez votre produit en détail. Incluez les caractéristiques, l'état, etc.</div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="price" class="form-label">Prix (€) *</label>
                                            <input type="number" class="form-control" id="price" name="price" min="0.01" step="0.01" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="stock" class="form-label">Stock disponible *</label>
                                            <input type="number" class="form-control" id="stock" name="stock" min="0" value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : '1'; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Catégorie *</label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">Sélectionner une catégorie</option>
                                            <?php foreach ($categories as $category): ?>
                                                <?php 
                                                // Afficher différemment les catégories principales et les sous-catégories
                                                $is_parent = $category['parent_id'] === null;
                                                $selected = (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '';
                                                ?>
                                                <option value="<?php echo $category['id']; ?>" <?php echo $selected; ?> <?php echo $is_parent ? 'disabled' : ''; ?>>
                                                    <?php echo $is_parent ? '-- ' . $category['name'] . ' --' : $category['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="sale_type" class="form-label">Type de vente *</label>
                                        <select class="form-select" id="sale_type" name="sale_type" required>
                                            <option value="immediate" <?php echo (isset($_POST['sale_type']) && $_POST['sale_type'] == 'immediate') ? 'selected' : ''; ?>>Achat immédiat</option>
                                            <option value="negotiation" <?php echo (isset($_POST['sale_type']) && $_POST['sale_type'] == 'negotiation') ? 'selected' : ''; ?>>Négociable</option>
                                            <option value="auction" <?php echo (isset($_POST['sale_type']) && $_POST['sale_type'] == 'auction') ? 'selected' : ''; ?>>Enchère</option>
                                        </select>
                                    </div>
                                    
                                    <div id="auction_fields" class="mb-3" style="display: none;">
                                        <label for="auction_end" class="form-label">Date de fin d'enchère *</label>
                                        <input type="datetime-local" class="form-control" id="auction_end" name="auction_end" value="<?php echo isset($_POST['auction_end']) ? htmlspecialchars($_POST['auction_end']) : ''; ?>">
                                        <div class="form-text">La date de fin doit être dans le futur.</div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="featured" name="featured" <?php echo (isset($_POST['featured'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="featured">Mettre en avant ce produit</label>
                                        <div class="form-text">Les produits mis en avant apparaissent sur la page d'accueil.</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Image principale *</label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                                        <div class="form-text">Format recommandé: JPG, PNG ou GIF. Taille maximale: 2 Mo.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="image_preview" class="form-label">Aperçu de l'image</label>
                                        <div id="image_preview" class="border rounded p-2 text-center">
                                            <img id="preview_img" src="../images/placeholder.jpg" alt="Aperçu de l'image" class="img-fluid" style="max-height: 200px;">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="additional_images" class="form-label">Images additionnelles</label>
                                        <input type="file" class="form-control" id="additional_images" name="additional_images[]" accept="image/*" multiple>
                                        <div class="form-text">Vous pouvez sélectionner plusieurs images (max 5).</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="video" class="form-label">Vidéo du produit</label>
                                        <input type="file" class="form-control" id="video" name="video" accept="video/mp4,video/webm,video/ogg">
                                        <div class="form-text">Formats acceptés: MP4, WebM, OGG. Taille maximale: 50 Mo.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="qualities" class="form-label">Qualités du produit</label>
                                        <textarea class="form-control" id="qualities" name="qualities" rows="3" placeholder="Une qualité par ligne"><?php echo isset($_POST['qualities']) ? htmlspecialchars($_POST['qualities']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="defects" class="form-label">Défauts du produit</label>
                                        <textarea class="form-control" id="defects" name="defects" rows="3" placeholder="Un défaut par ligne"><?php echo isset($_POST['defects']) ? htmlspecialchars($_POST['defects']) : ''; ?></textarea>
                                        <div class="form-text">Soyez transparent sur les éventuels défauts pour éviter les litiges.</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="products.php" class="btn btn-secondary">Annuler</a>
                                <button type="submit" name="add_product" class="btn btn-primary">Ajouter le produit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Afficher/masquer les champs spécifiques aux enchères
        document.getElementById('sale_type').addEventListener('change', function() {
            var auctionFields = document.getElementById('auction_fields');
            if (this.value === 'auction') {
                auctionFields.style.display = 'block';
            } else {
                auctionFields.style.display = 'none';
            }
        });
        
        // Déclencher l'événement au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            var saleType = document.getElementById('sale_type');
            if (saleType.value === 'auction') {
                document.getElementById('auction_fields').style.display = 'block';
            }
        });
        
        // Aperçu de l'image principale
        document.getElementById('image').addEventListener('change', function(e) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview_img').src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
        });
        
        // Limiter le nombre d'images additionnelles à 5
        document.getElementById('additional_images').addEventListener('change', function() {
            if (this.files.length > 5) {
                alert('Vous ne pouvez pas sélectionner plus de 5 images additionnelles.');
                this.value = '';
            }
        });
    </script>
</body>
</html>

