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
            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" href="orders.php">
                <i class="fas fa-shopping-cart me-2"></i>
                Commandes
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>" href="reviews.php">
                <i class="fas fa-star me-2"></i>
                Avis
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                <i class="fas fa-chart-line me-2"></i>
                Statistiques
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                <i class="fas fa-user me-2"></i>
                Profil
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

