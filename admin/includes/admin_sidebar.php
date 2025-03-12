<div class="position-sticky">
    <div class="text-center mb-4">
        <h5 class="text-white">Omnes MarketPlace</h5>
        <p class="text-white-50">Administration</p>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                Tableau de bord
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                Utilisateurs
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sellers.php' ? 'active' : ''; ?>" href="sellers.php">
                Vendeurs
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>" href="products.php">
                Produits
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" href="categories.php">
                Catégories
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" href="orders.php">
                Commandes
            </a>
        </li>
    </ul>
    
    <hr class="text-white-50">
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="../index.php">
                Retour au site
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../logout.php">
                Déconnexion
            </a>
        </li>
    </ul>
</div>

