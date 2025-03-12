<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est un administrateur
requireAdmin();

// Récupérer les informations de l'administrateur
$admin_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Traitement du formulaire de mise à jour du profil
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error_message = "Veuillez remplir tous les champs obligatoires.";
        } else {
            // Vérifier si l'email existe déjà pour un autre utilisateur
            $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $email, $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Cet email est déjà utilisé par un autre utilisateur.";
            } else {
                // Mise à jour du profil
                $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $admin_id);
                
                if ($stmt->execute()) {
                    $success_message = "Votre profil a été mis à jour avec succès.";
                    
                    // Mettre à jour les informations de session
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['email'] = $email;
                } else {
                    $error_message = "Une erreur s'est produite lors de la mise à jour de votre profil.";
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "Veuillez remplir tous les champs.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Les nouveaux mots de passe ne correspondent pas.";
        } elseif (strlen($new_password) < 8) {
            $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        } else {
            // Vérifier le mot de passe actuel
            if (password_verify($current_password, $admin['password'])) {
                // Mettre à jour le mot de passe
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hashed_password, $admin_id);
                
                if ($stmt->execute()) {
                    $success_message = "Votre mot de passe a été modifié avec succès.";
                } else {
                    $error_message = "Une erreur s'est produite lors de la modification de votre mot de passe.";
                }
            } else {
                $error_message = "Le mot de passe actuel est incorrect.";
            }
        }
    } elseif (isset($_POST['update_profile_image'])) {
        // Traitement de l'image de profil
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['profile_image']['tmp_name'];
            $file_name = $_FILES['profile_image']['name'];
            $file_size = $_FILES['profile_image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Vérifier l'extension du fichier
            $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
            if (!in_array($file_ext, $allowed_extensions)) {
                $error_message = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
            } elseif ($file_size > 5242880) { // 5 MB
                $error_message = "La taille du fichier ne doit pas dépasser 5 MB.";
            } else {
                // Créer un nom de fichier unique
                $new_file_name = 'profile_' . $admin_id . '_' . time() . '.' . $file_ext;
                $upload_path = '../uploads/profiles/' . $new_file_name;
                
                // Créer le répertoire s'il n'existe pas
                if (!file_exists('../uploads/profiles/')) {
                    mkdir('../uploads/profiles/', 0777, true);
                }
                
                // Déplacer le fichier téléchargé
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Mettre à jour l'image de profil dans la base de données
                    $profile_image_url = 'uploads/profiles/' . $new_file_name;
                    
                    $sql = "UPDATE users SET profile_image = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $profile_image_url, $admin_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Votre image de profil a été mise à jour avec succès.";
                        
                        // Supprimer l'ancienne image si elle existe
                        if (!empty($admin['profile_image']) && file_exists('../' . $admin['profile_image'])) {
                            unlink('../' . $admin['profile_image']);
                        }
                        
                        // Mettre à jour les informations de l'administrateur
                        $admin['profile_image'] = $profile_image_url;
                    } else {
                        $error_message = "Une erreur s'est produite lors de la mise à jour de votre image de profil.";
                    }
                } else {
                    $error_message = "Une erreur s'est produite lors du téléchargement de l'image.";
                }
            }
        } else {
            $error_message = "Veuillez sélectionner une image.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - Administration</title>
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
                    <h1 class="h2">Mon profil</h1>
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
                    <!-- Informations du profil -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Informations du profil</h5>
                            </div>
                            <div class="card-body text-center">
                                <?php if (!empty($admin['profile_image'])): ?>
                                    <img src="../<?php echo $admin['profile_image']; ?>" class="rounded-circle mb-3" width="150" height="150" alt="Profile Image" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 150px; height: 150px; font-size: 3rem;">
                                        <?php echo strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <h5 class="mb-1"><?php echo $admin['first_name'] . ' ' . $admin['last_name']; ?></h5>
                                <p class="text-muted mb-3"><?php echo $admin['username']; ?></p>
                                
                                <div class="d-grid">
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changeProfileImageModal">
                                        <i class="fas fa-camera me-2"></i> Changer l'image de profil
                                    </button>
                                </div>
                                
                                <hr>
                                
                                <div class="text-start">
                                    <p class="mb-2"><i class="fas fa-envelope me-2 text-primary"></i> <?php echo $admin['email']; ?></p>
                                    <p class="mb-2"><i class="fas fa-phone me-2 text-primary"></i> <?php echo !empty($admin['phone']) ? $admin['phone'] : 'Non renseigné'; ?></p>
                                    <p class="mb-2"><i class="fas fa-user-shield me-2 text-primary"></i> Administrateur</p>
                                    <p class="mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i> Membre depuis <?php echo date('d/m/Y', strtotime($admin['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Formulaires de mise à jour -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Modifier mes informations</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="profile.php">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="first_name" class="form-label fw-medium">Prénom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $admin['first_name']; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="last_name" class="form-label fw-medium">Nom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $admin['last_name']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="email" class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $admin['email']; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label fw-medium">Téléphone</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $admin['phone']; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Enregistrer les modifications
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Changer mon mot de passe</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="profile.php">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Mot de passe actuel <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="form-text">Le mot de passe doit contenir au moins 8 caractères.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="change_password" class="btn btn-primary">
                                            <i class="fas fa-key me-2"></i> Changer mon mot de passe
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal pour changer l'image de profil -->
    <div class="modal fade" id="changeProfileImageModal" tabindex="-1" aria-labelledby="changeProfileImageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="profile.php" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="changeProfileImageModalLabel">Changer l'image de profil</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="profile_image" class="form-label">Sélectionner une nouvelle image</label>
                            <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*" required>
                            <div class="form-text">Formats acceptés : JPG, JPEG, PNG, GIF. Taille maximale : 5 MB.</div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <div id="image_preview" class="d-none">
                                <img src="/placeholder.svg" class="img-thumbnail mb-2" style="max-height: 200px;" alt="Image Preview">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="update_profile_image" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Prévisualisation de l'image
    document.getElementById('profile_image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const preview = document.getElementById('image_preview');
                preview.classList.remove('d-none');
                preview.querySelector('img').src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
    </script>
</body>
</html>

