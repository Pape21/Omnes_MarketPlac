<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
  header("Location: ../login.php");
  exit();
}

// Get admin information
$admin_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ? AND role = 'admin'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Get unread admin notifications count
$query = "SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0";
$result = $conn->query($query);
$row = $result->fetch_assoc();
$unread_notifications = $row['count'];

// Get pending seller requests count
$query = "SELECT COUNT(*) as count FROM seller_requests WHERE status = 'pending'";
$result = $conn->query($query);
$row = $result->fetch_assoc();
$pending_requests = $row['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - Omnes Marketplace</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
  <!-- Admin Header -->
  <header class="admin-header">
      <div class="container-fluid">
          <div class="row align-items-center">
              <div class="col-md-3">
                  <a class="navbar-brand" href="index.php">Omnes<span>Admin</span></a>
              </div>
              <div class="col-md-6">
                  <form class="d-flex">
                      <div class="input-group">
                          <input class="form-control" type="search" placeholder="Search..." aria-label="Search">
                          <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                      </div>
                  </form>
              </div>
              <div class="col-md-3">
                  <ul class="nav justify-content-end">
                      <li class="nav-item">
                          <a class="nav-link position-relative" href="notifications.php">
                              <i class="fas fa-bell"></i>
                              <?php if ($unread_notifications > 0): ?>
                                  <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                      <?php echo $unread_notifications; ?>
                                  </span>
                              <?php endif; ?>
                          </a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link position-relative" href="seller_requests.php">
                              <i class="fas fa-user-plus"></i>
                              <?php if ($pending_requests > 0): ?>
                                  <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning">
                                      <?php echo $pending_requests; ?>
                                  </span>
                              <?php endif; ?>
                          </a>
                      </li>
                      <li class="nav-item dropdown">
                          <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                              <i class="fas fa-user-circle"></i> <?php echo $admin['username']; ?>
                          </a>
                          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                              <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                              <li><a class="dropdown-item" href="../index.php"><i class="fas fa-home me-2"></i> Visit Site</a></li>
                              <li><hr class="dropdown-divider"></li>
                              <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                          </ul>
                      </li>
                  </ul>
              </div>
          </div>
      </div>
  </header>

  <!-- Sidebar Toggle Button (for mobile) -->
  <div class="sidebar-toggle" id="sidebarToggle">
      <i class="fas fa-bars"></i>
  </div>

  <!-- Main Container -->
  <div class="container-fluid">
      <div class="row">

