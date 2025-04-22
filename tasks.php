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

// User is logged in, continue with the rest of tasks.php
$userId = $_SESSION['user_id'];

// Process task form submission
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'add_task') {
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
        header('Location: tasks.php?status=added');
        exit;
    } 
    elseif ($_POST['action'] == 'edit_task') {
        $taskId = $_POST['task_id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $deadline = $_POST['deadline'];
        $priority = $_POST['priority'];
        $completed = isset($_POST['completed']) ? 1 : 0;
        
        $query = "UPDATE tasks 
                SET title = ?, description = ?, deadline = ?, priority = ?, completed = ? 
                WHERE id = ? AND user_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssiis", $title, $description, $deadline, $priority, $completed, $taskId, $userId);
        $stmt->execute();
        
        // Redirect to prevent form resubmission
        header('Location: tasks.php?status=updated');
        exit;
    }
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
    header('Location: tasks.php');
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
    header('Location: tasks.php?status=deleted');
    exit;
}

// Get task data for edit
$editTaskData = null;
if (isset($_GET['edit_task'])) {
    $taskId = $_GET['edit_task'];
    
    $query = "SELECT * FROM tasks WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $taskId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $editTaskData = $result->fetch_assoc();
    } else {
        // Task doesn't exist or doesn't belong to user
        header('Location: tasks.php');
        exit;
    }
}

// Set up filter and sort options
$filterCompleted = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'deadline';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Build query based on filters
$query = "SELECT * FROM tasks WHERE user_id = ?";

if ($filterCompleted == 'completed') {
    $query .= " AND completed = 1";
} elseif ($filterCompleted == 'pending') {
    $query .= " AND completed = 0";
}

// Add sorting
$query .= " ORDER BY ";
switch ($sortBy) {
    case 'priority':
        $query .= "FIELD(priority, 'high', 'medium', 'low')";
        break;
    case 'title':
        $query .= "title";
        break;
    case 'created':
        $query .= "created_at";
        break;
    default:
        $query .= "deadline";
}

$query .= " " . $sortOrder;

// Prepare and execute the query
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}

// Count tasks by status
$countQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) as pending
              FROM tasks 
              WHERE user_id = ?";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$countResult = $stmt->get_result();
$taskCounts = $countResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Interactive Study Planner</title>
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
                        <a class="nav-link active" href="tasks.php">Task Manager</a>
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
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="mb-0">Task Manager</h1>
                <p class="text-muted">Organize and manage your study tasks</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                    <i class="fas fa-plus me-1"></i> Add New Task
                </button>
            </div>
        </div>
        
        <!-- Status Notification -->
        <?php if (isset($_GET['status'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php if ($_GET['status'] == 'added'): ?>
                    Task added successfully!
                <?php elseif ($_GET['status'] == 'updated'): ?>
                    Task updated successfully!
                <?php elseif ($_GET['status'] == 'deleted'): ?>
                    Task deleted successfully!
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Task Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card bg-light shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Tasks</h6>
                                <h3 class="mb-0"><?php echo $taskCounts['total']; ?></h3>
                            </div>
                            <div class="icon-bg bg-primary rounded-circle p-3">
                                <i class="fas fa-tasks text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-light shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Pending Tasks</h6>
                                <h3 class="mb-0"><?php echo $taskCounts['pending']; ?></h3>
                            </div>
                            <div class="icon-bg bg-warning rounded-circle p-3">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-light shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Completed Tasks</h6>
                                <h3 class="mb-0"><?php echo $taskCounts['completed']; ?></h3>
                            </div>
                            <div class="icon-bg bg-success rounded-circle p-3">
                                <i class="fas fa-check text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Filter and Sort Options -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form action="tasks.php" method="get" class="row g-3">
                            <div class="col-md-4">
                                <label for="filterSelect" class="form-label">Filter Tasks</label>
                                <select id="filterSelect" name="filter" class="form-select">
                                    <option value="all" <?php echo $filterCompleted == 'all' ? 'selected' : ''; ?>>All Tasks</option>
                                    <option value="pending" <?php echo $filterCompleted == 'pending' ? 'selected' : ''; ?>>Pending Tasks</option>
                                    <option value="completed" <?php echo $filterCompleted == 'completed' ? 'selected' : ''; ?>>Completed Tasks</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="sortSelect" class="form-label">Sort By</label>
                                <select id="sortSelect" name="sort" class="form-select">
                                    <option value="deadline" <?php echo $sortBy == 'deadline' ? 'selected' : ''; ?>>Deadline</option>
                                    <option value="priority" <?php echo $sortBy == 'priority' ? 'selected' : ''; ?>>Priority</option>
                                    <option value="title" <?php echo $sortBy == 'title' ? 'selected' : ''; ?>>Title</option>
                                    <option value="created" <?php echo $sortBy == 'created' ? 'selected' : ''; ?>>Creation Date</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="orderSelect" class="form-label">Order</label>
                                <select id="orderSelect" name="order" class="form-select">
                                    <option value="ASC" <?php echo $sortOrder == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                    <option value="DESC" <?php echo $sortOrder == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task List -->
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Your Tasks</h5>
                        <?php if (empty($tasks)): ?>
                            <div class="alert alert-info">
                                No tasks found. Start by adding a new task!
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="5%">Status</th>
                                            <th width="25%">Title</th>
                                            <th width="30%">Description</th>
                                            <th width="15%">Deadline</th>
                                            <th width="10%">Priority</th>
                                            <th width="15%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tasks as $task): ?>
                                            <tr class="<?php echo $task['completed'] ? 'table-success' : ''; ?>">
                                                <td>
                                                    <a href="tasks.php?complete_task=<?php echo $task['id']; ?>" class="btn btn-sm <?php echo $task['completed'] ? 'btn-success' : 'btn-outline-success'; ?>">
                                                        <i class="fas fa-<?php echo $task['completed'] ? 'check' : 'circle'; ?>"></i>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                <td>
                                                    <?php 
                                                        $desc = htmlspecialchars($task['description']);
                                                        echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc; 
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $deadline = new DateTime($task['deadline']);
                                                        $today = new DateTime();
                                                        $interval = $today->diff($deadline);
                                                        $isPast = $today > $deadline;
                                                        
                                                        echo date('M d, Y', strtotime($task['deadline']));
                                                        
                                                        if (!$task['completed']) {
                                                            if ($isPast) {
                                                                echo '<br><span class="badge bg-danger">Overdue</span>';
                                                            } elseif ($interval->days <= 3) {
                                                                echo '<br><span class="badge bg-warning text-dark">Due soon</span>';
                                                            }
                                                        }
                                                    ?>
                                                </td>
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
                                                    <a href="tasks.php?edit_task=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary me-1">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="tasks.php?delete_task=<?php echo $task['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this task?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="tasks.php" method="post">
                        <input type="hidden" name="action" value="add_task">
                        <div class="mb-3">
                            <label for="title" class="form-label">Task Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="deadline" class="form-label">Deadline</label>
                            <input type="date" class="form-control" id="deadline" name="deadline" required>
                        </div>
                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Save Task</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <?php if ($editTaskData): ?>
    <div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editTaskModalLabel">Edit Task</h5>
                    <a href="tasks.php" class="btn-close btn-close-white" aria-label="Close"></a>
                </div>
                <div class="modal-body">
                    <form action="tasks.php" method="post">
                        <input type="hidden" name="action" value="edit_task">
                        <input type="hidden" name="task_id" value="<?php echo $editTaskData['id']; ?>">
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Task Title</label>
                            <input type="text" class="form-control" id="edit_title" name="title" value="<?php echo htmlspecialchars($editTaskData['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"><?php echo htmlspecialchars($editTaskData['description']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_deadline" class="form-label">Deadline</label>
                            <input type="date" class="form-control" id="edit_deadline" name="deadline" value="<?php echo $editTaskData['deadline']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_priority" class="form-label">Priority</label>
                            <select class="form-select" id="edit_priority" name="priority">
                                <option value="low" <?php echo $editTaskData['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $editTaskData['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $editTaskData['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_completed" name="completed" <?php echo $editTaskData['completed'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="edit_completed">Mark as completed</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Task</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Automatically open the edit modal
        document.addEventListener('DOMContentLoaded', function() {
            var editModal = new bootstrap.Modal(document.getElementById('editTaskModal'));
            editModal.show();
        });
    </script>
    <?php endif; ?>

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
</body>
</html>