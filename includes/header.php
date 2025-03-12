<header>
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
      <div class="container">
          <a class="navbar-brand" href="/omnes-marketplace/index.php">Omnes MarketPlace</a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse" id="navbarNav">
              <ul class="navbar-nav me-auto">
                  <li class="nav-item">
                      <a class="nav-link" href="/omnes-marketplace/index.php">Accueil</a>
                  </li>
                  <li class="nav-item dropdown">
                      <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                          Tout Parcourir
                      </a>
                      <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                          <li><h6 class="dropdown-header">Par catégorie</h6></li>
                          <?php
                          $categories = getCategories($conn);
                          foreach ($categories as $category) {
                              echo '<li><a class="dropdown-item" href="/omnes-marketplace/category.php?id=' . $category['id'] . '">' . $category['name'] . '</a></li>';
                          }
                          ?>
                          <li><hr class="dropdown-divider"></li>
                          <li><h6 class="dropdown-header">Par type de vente</h6></li>
                          <li><a class="dropdown-item" href="/omnes-marketplace/sale_type.php?type=immediate">Achat immédiat</a></li>
                          <li><a class="dropdown-item" href="/omnes-marketplace/sale_type.php?type=negotiation">Transaction vendeur-client</a></li>
                          <li><a class="dropdown-item" href="/omnes-marketplace/sale_type.php?type=auction">Meilleure offre</a></li>
                          <li><hr class="dropdown-divider"></li>
                          <li><h6 class="dropdown-header">Par gamme</h6></li>
                          <li><a class="dropdown-item" href="/omnes-marketplace/product_range.php?range=rare">Articles rares</a></li>
                          <li><a class="dropdown-item" href="/omnes-marketplace/product_range.php?range=premium">Articles hauts de gamme</a></li>
                          <li><a class="dropdown-item" href="/omnes-marketplace/product_range.php?range=regular">Articles réguliers</a></li>
                      </ul>
                  </li>
                  <li class="nav-item">
                      <a class="nav-link" href="/omnes-marketplace/notifications.php">
                          Notifications
                          <?php
                          if (isLoggedIn()) {
                              $unreadCount = getUnreadNotificationsCount($conn, $_SESSION['user_id']);
                              if ($unreadCount > 0) {
                                  echo '<span class="badge bg-danger">' . $unreadCount . '</span>';
                              }
                          }
                          ?>
                      </a>
                  </li>
              </ul>
              
              <form class="d-flex me-3" action="/omnes-marketplace/search.php" method="GET">
                  <input class="form-control me-2" type="search" name="keyword" placeholder="Rechercher" aria-label="Search">
                  <button class="btn btn-outline-light" type="submit">Rechercher</button>
              </form>
              
              <ul class="navbar-nav ms-auto d-flex align-items-center">
                  <li class="nav-item me-3">
                      <a class="nav-link" href="/omnes-marketplace/cart.php">
                          <i class="fas fa-shopping-cart"></i> Panier
                          <?php
                          if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                              $cartCount = count($_SESSION['cart']);
                              echo '<span class="badge bg-secondary">' . $cartCount . '</span>';
                          }
                          ?>
                      </a>
                  </li>
                  <?php if (isLoggedIn()): ?>
                      <li class="nav-item dropdown">
                          <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                              <i class="fas fa-user"></i> <?php echo $_SESSION['username']; ?>
                          </a>
                          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                              <li><a class="dropdown-item" href="/omnes-marketplace/account.php">Mon compte</a></li>
                              <?php if (isAdmin()): ?>
                                  <li><a class="dropdown-item" href="/omnes-marketplace/admin/index.php">Administration</a></li>
                              <?php endif; ?>
                              <?php if (isSeller()): ?>
                                  <li><a class="dropdown-item" href="/omnes-marketplace/seller/index.php">Espace vendeur</a></li>
                              <?php endif; ?>
                              <li><hr class="dropdown-divider"></li>
                              <li><a class="dropdown-item" href="/omnes-marketplace/logout.php">Déconnexion</a></li>
                          </ul>
                      </li>
                  <?php else: ?>
                      <li class="nav-item me-2">
                          <a class="nav-link btn btn-outline-light px-3" href="/omnes-marketplace/login.php">Connexion</a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link btn btn-secondary px-3" href="/omnes-marketplace/register.php">Inscription</a>
                      </li>
                  <?php endif; ?>
              </ul>
          </div>
      </div>
  </nav>
</header>

