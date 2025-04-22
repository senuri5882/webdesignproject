<?php
// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User is not logged in, redirect to login page
    header('Location: auth/login.php');
    exit;
}

require_once 'includes/db_connection.php';

$userId = $_SESSION['user_id'];
$updateMessage = '';
$updateError = '';

// Get user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Process profile update
if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    // Basic validation
    if (empty($email)) {
        $updateError = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $updateError = 'Please enter a valid email address';
    } else {
        // Check if email already exists for another user
        $checkEmail = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($checkEmail);
        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $updateError = 'Email already in use by another account';
        } else {
            // Update user info
            $updateQuery = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssi", $fullName, $email, $userId);
            
            if ($stmt->execute()) {
                $updateMessage = 'Profile updated successfully';
                
                // Refresh user data
                $query = "SELECT * FROM users WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $updateError = 'Update failed. Please try again';
            }
        }
    }
}

// Process password change
if (isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $updateError = 'All password fields are required';
    } elseif ($newPassword !== $confirmPassword) {
        $updateError = 'New passwords do not match';
    } elseif (strlen($newPassword) < 8) {
        $updateError = 'New password must be at least 8 characters long';
    } else {
        // Verify current password
        if (password_verify($currentPassword, $user['password'])) {
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("si", $hashedPassword, $userId);
            
            if ($stmt->execute()) {
                $updateMessage = 'Password changed successfully';
            } else {
                $updateError = 'Password update failed. Please try again';
            }
        } else {
            $updateError = 'Current password is incorrect';
        }
    }
}

// Get user statistics
// Total tasks
$taskCountQuery = "SELECT 
                      COUNT(*) as total_tasks,
                      SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_tasks
                  FROM tasks 
                  WHERE user_id = ?";
$stmt = $conn->prepare($taskCountQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$taskStats = $stmt->get_result()->fetch_assoc();

// Total notes
$noteCountQuery = "SELECT COUNT(*) as total_notes FROM notes WHERE user_id = ?";
$stmt = $conn->prepare($noteCountQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$noteStats = $stmt->get_result()->fetch_assoc();

// Account creation date
$accountCreated = new DateTime($user['created_at']);
$now = new DateTime();
$accountAge = $now->diff($accountCreated)->days;

// Calculate completion rate
$completionRate = $taskStats['total_tasks'] > 0 ? 
    ($taskStats['completed_tasks'] / $taskStats['total_tasks']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Interactive Study Planner</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-book-open me-2"></i>
                Interactive Study Planner
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tasks.php">Task Manager</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="progress.php">Progress Tracker</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="notes.php">Notes</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-12 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h1 class="card-title">Your Profile</h1>
                        <p class="card-text text-muted">Manage your account settings and information</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($updateMessage)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($updateMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($updateError)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($updateError); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Information -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form action="profile.php" method="post">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                                <div class="form-text">Username cannot be changed</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="account_created" class="form-label">Account Created</label>
                                <input type="text" class="form-control" id="account_created" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" readonly disabled>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form action="profile.php" method="post">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">Password must be at least 8 characters long</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Account Statistics -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Account Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="display-1 text-primary">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <h4 class="mt-3"><?php echo htmlspecialchars($user['username']); ?></h4>
                            <p class="text-muted"><?php echo !empty($user['full_name']) ? htmlspecialchars($user['full_name']) : 'No name provided'; ?></p>
                        </div>
                        
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Tasks
                                <span class="badge bg-primary rounded-pill"><?php echo $taskStats['total_tasks']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Completed Tasks
                                <span class="badge bg-success rounded-pill"><?php echo $taskStats['completed_tasks']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Completion Rate
                                <span class="badge bg-info rounded-pill"><?php echo round($completionRate); ?>%</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Notes
                                <span class="badge bg-warning text-dark rounded-pill"><?php echo $noteStats['total_notes']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Account Age
                                <span class="badge bg-secondary rounded-pill"><?php echo $accountAge; ?> days</span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Account Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-outline-primary">
                                <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                            </a>
                            <a href="tasks.php" class="btn btn-outline-success">
                                <i class="fas fa-tasks me-2"></i>Manage Tasks
                            </a>
                            <a href="logout.php" class="btn btn-outline-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-primary text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Interactive Study Planner</h5>
                    <p>A web application to help students plan their study schedules effectively.</p>
                </div>
                <div class="col-md-6 text-md-end">
                <p>&copy; <?php echo date('Y'); ?> Kushan Kumarasiri</p>
                <p>kushanlaksitha32@gmail.com</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Password validation script -->
    <script src="js/profile.js"></script>
</body>
</html>