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

// User is logged in, continue with the rest of progress.php
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

// Get progress statistics
$overallProgress = calculateProgress($conn, $userId);

// Get task counts by priority
$priorityCountQuery = "SELECT 
                          priority,
                          COUNT(*) as total,
                          SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed
                       FROM tasks 
                       WHERE user_id = ?
                       GROUP BY priority";
$stmt = $conn->prepare($priorityCountQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$priorityResult = $stmt->get_result();
$priorityCounts = [];

while ($row = $priorityResult->fetch_assoc()) {
    $priorityCounts[$row['priority']] = [
        'total' => $row['total'],
        'completed' => $row['completed'],
        'percentage' => $row['total'] > 0 ? ($row['completed'] / $row['total']) * 100 : 0
    ];
}

// Ensure all priorities have an entry even if no tasks
foreach (['low', 'medium', 'high'] as $priority) {
    if (!isset($priorityCounts[$priority])) {
        $priorityCounts[$priority] = [
            'total' => 0,
            'completed' => 0,
            'percentage' => 0
        ];
    }
}

// Get task completion by week (last 4 weeks)
$weeklyProgressQuery = "SELECT 
                           YEAR(deadline) as year,
                           WEEK(deadline, 1) as week,
                           COUNT(*) as total,
                           SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed
                        FROM tasks 
                        WHERE user_id = ? 
                          AND deadline BETWEEN DATE_SUB(CURDATE(), INTERVAL 4 WEEK) AND CURDATE()
                        GROUP BY YEAR(deadline), WEEK(deadline, 1)
                        ORDER BY year ASC, week ASC";
$stmt = $conn->prepare($weeklyProgressQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$weeklyResult = $stmt->get_result();
$weeklyProgress = [];

while ($row = $weeklyResult->fetch_assoc()) {
    $weekNumber = $row['week'];
    $weeklyProgress[$weekNumber] = [
        'total' => $row['total'],
        'completed' => $row['completed'],
        'percentage' => $row['total'] > 0 ? ($row['completed'] / $row['total']) * 100 : 0
    ];
}

// Get recent completed tasks
$recentCompletedQuery = "SELECT * FROM tasks 
                        WHERE user_id = ? AND completed = 1 
                        ORDER BY updated_at DESC 
                        LIMIT 5";
$stmt = $conn->prepare($recentCompletedQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentResult = $stmt->get_result();
$recentCompletedTasks = [];

while ($row = $recentResult->fetch_assoc()) {
    $recentCompletedTasks[] = $row;
}

// Get overdue tasks
$overdueQuery = "SELECT * FROM tasks 
                WHERE user_id = ? AND completed = 0 AND deadline < CURDATE() 
                ORDER BY deadline ASC";
$stmt = $conn->prepare($overdueQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$overdueResult = $stmt->get_result();
$overdueTasks = [];

while ($row = $overdueResult->fetch_assoc()) {
    $overdueTasks[] = $row;
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

// Get completion rate by month (last 6 months)
$monthlyProgressQuery = "SELECT 
                           DATE_FORMAT(deadline, '%Y-%m') as month,
                           COUNT(*) as total,
                           SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed
                        FROM tasks 
                        WHERE user_id = ? 
                          AND deadline BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND CURDATE()
                        GROUP BY DATE_FORMAT(deadline, '%Y-%m')
                        ORDER BY month ASC";
$stmt = $conn->prepare($monthlyProgressQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$monthlyResult = $stmt->get_result();
$monthlyProgress = [];

while ($row = $monthlyResult->fetch_assoc()) {
    $monthName = date('M Y', strtotime($row['month'] . '-01'));
    $monthlyProgress[$monthName] = [
        'total' => $row['total'],
        'completed' => $row['completed'],
        'percentage' => $row['total'] > 0 ? ($row['completed'] / $row['total']) * 100 : 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Tracker - Interactive Study Planner</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a class="nav-link active" href="progress.php">Progress Tracker</a>
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
            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h1 class="card-title">Progress Tracker</h1>
                        <p class="card-text">Monitor your study progress, track task completion, and analyze your performance over time.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Progress Section -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Overall Progress</h5>
                        <div class="progress mb-3" style="height: 30px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $overallProgress; ?>%;" aria-valuenow="<?php echo $overallProgress; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo round($overallProgress); ?>%
                            </div>
                        </div>
                        <p class="card-text">You've completed <?php echo $taskCounts['completed']; ?> out of <?php echo $taskCounts['total']; ?> tasks.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Stats Cards -->
        <div class="row mb-4">
            <!-- Priority-based Progress -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Progress by Priority</h5>
                        <div class="my-3">
                            <p><strong>High Priority:</strong></p>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $priorityCounts['high']['percentage']; ?>%;" aria-valuenow="<?php echo $priorityCounts['high']['percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($priorityCounts['high']['percentage']); ?>%
                                </div>
                            </div>
                            <p class="small text-muted"><?php echo $priorityCounts['high']['completed']; ?> of <?php echo $priorityCounts['high']['total']; ?> completed</p>
                        </div>
                        <div class="my-3">
                            <p><strong>Medium Priority:</strong></p>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-warning text-dark" role="progressbar" style="width: <?php echo $priorityCounts['medium']['percentage']; ?>%;" aria-valuenow="<?php echo $priorityCounts['medium']['percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($priorityCounts['medium']['percentage']); ?>%
                                </div>
                            </div>
                            <p class="small text-muted"><?php echo $priorityCounts['medium']['completed']; ?> of <?php echo $priorityCounts['medium']['total']; ?> completed</p>
                        </div>
                        <div class="my-3">
                            <p><strong>Low Priority:</strong></p>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-info text-dark" role="progressbar" style="width: <?php echo $priorityCounts['low']['percentage']; ?>%;" aria-valuenow="<?php echo $priorityCounts['low']['percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($priorityCounts['low']['percentage']); ?>%
                                </div>
                            </div>
                            <p class="small text-muted"><?php echo $priorityCounts['low']['completed']; ?> of <?php echo $priorityCounts['low']['total']; ?> completed</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Progress Chart -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Monthly Progress Trend</h5>
                        <canvas id="monthlyProgressChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Charts -->
        <div class="row mb-4">
            <!-- Priority Distribution Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Task Distribution by Priority</h5>
                        <canvas id="priorityDistributionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Completion Status Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Task Completion Status</h5>
                        <canvas id="completionStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Completed Tasks and Overdue Tasks -->
        <div class="row mb-4">
            <!-- Recently Completed Tasks -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Recently Completed Tasks</h5>
                        <?php if (empty($recentCompletedTasks)): ?>
                            <p class="text-muted">No tasks completed yet.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recentCompletedTasks as $task): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                            <small>
                                                <?php if ($task['priority'] == 'high'): ?>
                                                    <span class="badge bg-danger">High</span>
                                                <?php elseif ($task['priority'] == 'medium'): ?>
                                                    <span class="badge bg-warning text-dark">Medium</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-dark">Low</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <small class="text-muted">Completed on: <?php echo date('M d, Y', strtotime($task['updated_at'])); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Overdue Tasks -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Overdue Tasks</h5>
                        <?php if (empty($overdueTasks)): ?>
                            <p class="text-success">No overdue tasks. Great job!</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($overdueTasks as $task): ?>
                                    <div class="list-group-item list-group-item-danger">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                            <small>
                                                <?php if ($task['priority'] == 'high'): ?>
                                                    <span class="badge bg-danger">High</span>
                                                <?php elseif ($task['priority'] == 'medium'): ?>
                                                    <span class="badge bg-warning text-dark">Medium</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-dark">Low</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <small>Deadline: <?php echo date('M d, Y', strtotime($task['deadline'])); ?> (<?php echo floor((strtotime('now') - strtotime($task['deadline'])) / (60 * 60 * 24)); ?> days overdue)</small>
                                        <div class="mt-2">
                                            <a href="index.php?complete_task=<?php echo $task['id']; ?>" class="btn btn-sm btn-success me-1">
                                                <i class="fas fa-check"></i> Mark Complete
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productivity Tips -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Productivity Tips</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6><i class="fas fa-clock text-primary me-2"></i> Time Management</h6>
                                        <p class="card-text small">Use the Pomodoro Technique: Study for 25 minutes, then take a 5-minute break. After four cycles, take a longer break of 15-30 minutes.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6><i class="fas fa-list-check text-success me-2"></i> Task Prioritization</h6>
                                        <p class="card-text small">Tackle high-priority tasks first. Use the Eisenhower Matrix to categorize tasks by urgency and importance.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6><i class="fas fa-brain text-danger me-2"></i> Effective Study</h6>
                                        <p class="card-text small">Practice active recall and spaced repetition. Teach concepts to others to solidify your understanding.</p>
                                    </div>
                                </div>
                            </div>
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
    
    <!-- Chart JS Initialization -->
    <script>
        // Monthly Progress Chart
        const monthlyCtx = document.getElementById('monthlyProgressChart').getContext('2d');
        const monthlyProgressChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    foreach (array_keys($monthlyProgress) as $month) {
                        echo "'" . $month . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Completion Rate (%)',
                    data: [
                        <?php 
                        foreach ($monthlyProgress as $data) {
                            echo round($data['percentage']) . ',';
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Completion Rate (%)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                }
            }
        });

        // Priority Distribution Chart
        const priorityCtx = document.getElementById('priorityDistributionChart').getContext('2d');
        const priorityDistributionChart = new Chart(priorityCtx, {
            type: 'pie',
            data: {
                labels: ['High Priority', 'Medium Priority', 'Low Priority'],
                datasets: [{
                    data: [
                        <?php echo $priorityCounts['high']['total']; ?>,
                        <?php echo $priorityCounts['medium']['total']; ?>,
                        <?php echo $priorityCounts['low']['total']; ?>
                    ],
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(23, 162, 184, 0.8)'
                    ],
                    borderColor: [
                        'rgba(220, 53, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(23, 162, 184, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Completion Status Chart
        const statusCtx = document.getElementById('completionStatusChart').getContext('2d');
        const completionStatusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $taskCounts['completed']; ?>,
                        <?php echo $taskCounts['total'] - $taskCounts['completed']; ?>
                    ],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(108, 117, 125, 0.8)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>