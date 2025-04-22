<?php
// Start the session for user data persistence
session_start();

require_once 'includes/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User is not logged in, redirect to login page
    header('Location: auth/login.php');
    exit;
}

// User is logged in, continue with the rest of index.php
$userId = $_SESSION['user_id'];


// Function to calculate overall progress
function calculateProgress($conn, $userId) {
    // Count total tasks
    $totalQuery = "SELECT COUNT(*) as total FROM tasks WHERE user_id = ?";
    $stmt = $conn->prepare($totalQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $totalRow = $totalResult->fetch_assoc();
    $totalTasks = $totalRow['total'];
    
    if ($totalTasks == 0) {
        return 0;
    }
    
    // Count completed tasks
    $completedQuery = "SELECT COUNT(*) as completed FROM tasks WHERE user_id = ? AND completed = 1";
    $stmt = $conn->prepare($completedQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $completedResult = $stmt->get_result();
    $completedRow = $completedResult->fetch_assoc();
    $completedTasks = $completedRow['completed'];
    
    return ($completedTasks / $totalTasks) * 100;
}

// Process task form submission
if (isset($_POST['action']) && $_POST['action'] == 'add_task') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $deadline = $_POST['deadline'];
    $priority = $_POST['priority'];
    
    $query = "INSERT INTO tasks (user_id, title, description, deadline, priority, completed, created_at) 
              VALUES (?, ?, ?, ?, ?, 0, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issss", $userId, $title, $description, $deadline, $priority);
    $stmt->execute();
    
    // Redirect to prevent form resubmission
    header('Location: index.php');
    exit;
}

// Process task completion toggle
if (isset($_GET['complete_task'])) {
    $taskId = $_GET['complete_task'];
    
    // First check the current status
    $statusQuery = "SELECT completed FROM tasks WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($statusQuery);
    $stmt->bind_param("ii", $taskId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $newStatus = $row['completed'] ? 0 : 1;
        
        // Update the task
        $updateQuery = "UPDATE tasks SET completed = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("iii", $newStatus, $taskId, $userId);
        $stmt->execute();
    }
    
    // Redirect to refresh page
    header('Location: index.php');
    exit;
}

// Process task deletion
if (isset($_GET['delete_task'])) {
    $taskId = $_GET['delete_task'];
    
    $query = "DELETE FROM tasks WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $taskId, $userId);
    $stmt->execute();
    
    // Redirect to refresh page
    header('Location: index.php');
    exit;
}

// Calculate overall progress
$overallProgress = calculateProgress($conn, $userId);

// Get upcoming tasks (next 7 days)
$upcomingQuery = "SELECT * FROM tasks 
                 WHERE user_id = ? 
                 AND deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                 AND completed = 0 
                 ORDER BY deadline ASC 
                 LIMIT 5";

$stmt = $conn->prepare($upcomingQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$upcomingResult = $stmt->get_result();
$upcomingTasks = [];

while ($row = $upcomingResult->fetch_assoc()) {
    $upcomingTasks[] = $row;
}

// Get total task count and completed task count
$taskCountQuery = "SELECT 
                      COUNT(*) as total,
                      SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed
                  FROM tasks 
                  WHERE user_id = ?";
$stmt = $conn->prepare($taskCountQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$taskCountResult = $stmt->get_result();
$taskCounts = $taskCountResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Study Planner</title>
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
                    <a class="nav-link active" href="index.php">Dashboard</a>
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
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
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
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h1 class="card-title">Welcome to Your Study Planner</h1>
                        <p class="card-text">Organize your academic tasks, track your progress, and manage your study notes in one place.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Overview -->
        <div class="row mb-4">
            <!-- Progress Overview -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Overall Progress</h5>
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $overallProgress; ?>%;" aria-valuenow="<?php echo $overallProgress; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo round($overallProgress); ?>%</div>
                        </div>
                        <p class="card-text">You've completed <?php echo $taskCounts['completed']; ?> out of <?php echo $taskCounts['total']; ?> tasks.</p>
                        <a href="progress.php" class="btn btn-outline-primary">View Detailed Progress</a>
                    </div>
                </div>
            </div>

            <!-- Quick Task Add -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Quick Add Task</h5>
                        <form action="index.php" method="post">
                            <input type="hidden" name="action" value="add_task">
                            <div class="mb-3">
                                <input type="text" class="form-control" name="title" placeholder="Task Title" required>
                            </div>
                            <div class="mb-3">
                                <textarea class="form-control" name="description" rows="2" placeholder="Description"></textarea>
                            </div>
                            <div class="mb-3">
                                <input type="date" class="form-control" name="deadline" required>
                            </div>
                            <div class="mb-3">
                                <select class="form-control" name="priority">
                                    <option value="low">Low Priority</option>
                                    <option value="medium" selected>Medium Priority</option>
                                    <option value="high">High Priority</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Task</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Quick Links</h5>
                        <div class="list-group">
                            <a href="tasks.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-tasks me-2"></i> Task Manager
                            </a>
                            <a href="progress.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-chart-line me-2"></i> Progress Tracker
                            </a>
                            <a href="notes.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-sticky-note me-2"></i> Study Notes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Tasks -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Upcoming Tasks</h5>
                        <?php if (empty($upcomingTasks)): ?>
                            <p class="text-muted">No upcoming tasks for the next 7 days.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Deadline</th>
                                            <th>Priority</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcomingTasks as $task): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($task['deadline'])); ?></td>
                                                <td>
                                                    <?php if ($task['priority'] == 'high'): ?>
                                                        <span class="badge bg-danger">High</span>
                                                    <?php elseif ($task['priority'] == 'medium'): ?>
                                                        <span class="badge bg-warning text-dark">Medium</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info text-dark">Low</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="index.php?complete_task=<?php echo $task['id']; ?>" class="btn btn-sm btn-success me-1">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="index.php?delete_task=<?php echo $task['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this task?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="tasks.php" class="btn btn-outline-primary">View All Tasks</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Overview -->
        <div class="row mb-4">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-tasks fa-3x mb-3 text-primary"></i>
                        <h5 class="card-title">Task Manager</h5>
                        <p class="card-text">Add, edit, and delete your study tasks. Set deadlines and priorities to stay organized.</p>
                        <a href="tasks.php" class="btn btn-primary">Manage Tasks</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-3x mb-3 text-success"></i>
                        <h5 class="card-title">Progress Tracker</h5>
                        <p class="card-text">Monitor your study progress with visual charts and statistics. Stay motivated with achievement tracking.</p>
                        <a href="progress.php" class="btn btn-success">Track Progress</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-sticky-note fa-3x mb-3 text-warning"></i>
                        <h5 class="card-title">Study Notes</h5>
                        <p class="card-text">Create, organize, and access your study notes in one place. Categorize them for easy retrieval.</p>
                        <a href="notes.php" class="btn btn-warning text-dark">Manage Notes</a>
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
    <!-- Custom JavaScript -->
    <script src="js/index.js"></script>
</body>
</html>