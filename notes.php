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

// User is logged in, continue with the rest of notes.php
$userId = $_SESSION['user_id'];

// Process note form submission for adding/editing
if (isset($_POST['action'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    
    if ($_POST['action'] == 'add_note') {
        // Insert new note
        $query = "INSERT INTO notes (user_id, title, content, category, created_at) 
                  VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isss", $userId, $title, $content, $category);
        $stmt->execute();
        
    } elseif ($_POST['action'] == 'edit_note' && isset($_POST['note_id'])) {
        // Update existing note
        $noteId = $_POST['note_id'];
        
        // Verify the note belongs to this user
        $verifyQuery = "SELECT id FROM notes WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($verifyQuery);
        $stmt->bind_param("ii", $noteId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update the note
            $updateQuery = "UPDATE notes SET title = ?, content = ?, category = ?, updated_at = NOW() 
                           WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("sssii", $title, $content, $category, $noteId, $userId);
            $stmt->execute();
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: notes.php');
    exit;
}

// Process note deletion
if (isset($_GET['delete_note'])) {
    $noteId = $_GET['delete_note'];
    
    $query = "DELETE FROM notes WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $noteId, $userId);
    $stmt->execute();
    
    // Redirect to refresh page
    header('Location: notes.php');
    exit;
}

// Prepare for note editing
$editingNote = null;
if (isset($_GET['edit_note'])) {
    $noteId = $_GET['edit_note'];
    
    $query = "SELECT * FROM notes WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $noteId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $editingNote = $result->fetch_assoc();
    }
}

// Get filter parameters
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Get user's categories for the filter dropdown
$categoriesQuery = "SELECT DISTINCT category FROM notes WHERE user_id = ? AND category != '' ORDER BY category";
$stmt = $conn->prepare($categoriesQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$categoriesResult = $stmt->get_result();
$categories = [];

while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Build the query to get notes with optional filters
$notesQuery = "SELECT * FROM notes WHERE user_id = ?";
$queryParams = [$userId];
$paramTypes = "i";

if (!empty($categoryFilter)) {
    $notesQuery .= " AND category = ?";
    $queryParams[] = $categoryFilter;
    $paramTypes .= "s";
}

if (!empty($searchQuery)) {
    $notesQuery .= " AND (title LIKE ? OR content LIKE ?)";
    $searchParam = "%$searchQuery%";
    $queryParams[] = $searchParam;
    $queryParams[] = $searchParam;
    $paramTypes .= "ss";
}

$notesQuery .= " ORDER BY updated_at DESC";

$stmt = $conn->prepare($notesQuery);
$stmt->bind_param($paramTypes, ...$queryParams);
$stmt->execute();
$notesResult = $stmt->get_result();
$notes = [];

while ($row = $notesResult->fetch_assoc()) {
    $notes[] = $row;
}

// Count total notes
$totalNotesQuery = "SELECT COUNT(*) as total FROM notes WHERE user_id = ?";
$stmt = $conn->prepare($totalNotesQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalNotesResult = $stmt->get_result();
$totalNotes = $totalNotesResult->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Notes - Interactive Study Planner</title>
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
                        <a class="nav-link active" href="notes.php">Notes</a>
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
        <!-- Notes Header -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h1 class="card-title">
                            <i class="fas fa-sticky-note me-2 text-warning"></i>
                            Study Notes
                        </h1>
                        <p class="card-text">Create, organize, and manage your study notes all in one place.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Notes Form -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo isset($editingNote) ? 'Edit Note' : 'Add New Note'; ?>
                        </h5>
                        <form action="notes.php" method="post">
                            <input type="hidden" name="action" value="<?php echo isset($editingNote) ? 'edit_note' : 'add_note'; ?>">
                            <?php if (isset($editingNote)): ?>
                                <input type="hidden" name="note_id" value="<?php echo $editingNote['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo isset($editingNote) ? htmlspecialchars($editingNote['title']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <input type="text" class="form-control" id="category" name="category" list="categoryOptions"
                                       value="<?php echo isset($editingNote) ? htmlspecialchars($editingNote['category']) : ''; ?>">
                                <datalist id="categoryOptions">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div class="mb-3">
                                <label for="content" class="form-label">Content</label>
                                <textarea class="form-control" id="content" name="content" rows="10" required><?php echo isset($editingNote) ? htmlspecialchars($editingNote['content']) : ''; ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo isset($editingNote) ? 'Update Note' : 'Add Note'; ?>
                                </button>
                                <?php if (isset($editingNote)): ?>
                                    <a href="notes.php" class="btn btn-outline-secondary">Cancel Editing</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Notes Statistics -->
                <div class="card shadow-sm mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Notes Statistics</h5>
                        <p class="mb-2">
                            <i class="fas fa-sticky-note me-2 text-warning"></i>
                            Total Notes: <strong><?php echo $totalNotes; ?></strong>
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-folder me-2 text-info"></i>
                            Categories: <strong><?php echo count($categories); ?></strong>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-clock me-2 text-success"></i>
                            Last Updated: 
                            <strong>
                                <?php 
                                    echo !empty($notes) ? date('M d, Y H:i', strtotime($notes[0]['updated_at'])) : 'No notes yet'; 
                                ?>
                            </strong>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Notes Display -->
            <div class="col-lg-8">
                <!-- Search and Filter -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="get" action="notes.php" class="row g-3">
                            <div class="col-md-5">
                                <label for="searchQuery" class="form-label">Search Notes</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="searchQuery" name="search" 
                                           placeholder="Search by title or content" value="<?php echo htmlspecialchars($searchQuery); ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label for="categoryFilter" class="form-label">Filter by Category</label>
                                <select class="form-select" id="categoryFilter" name="category" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>" 
                                                <?php echo $categoryFilter === $category ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <?php if (!empty($searchQuery) || !empty($categoryFilter)): ?>
                                    <a href="notes.php" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Notes List -->
                <?php if (empty($notes)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php 
                            if (!empty($searchQuery) || !empty($categoryFilter)) {
                                echo "No notes found matching your search criteria.";
                            } else {
                                echo "You haven't created any notes yet. Add your first note to get started!";
                            }
                        ?>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <?php foreach ($notes as $note): ?>
                            <div class="col">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($note['title']); ?></h5>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="notes.php?edit_note=<?php echo $note['id']; ?>">
                                                        <i class="fas fa-edit me-2 text-primary"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="notes.php?delete_note=<?php echo $note['id']; ?>" 
                                                       onclick="return confirm('Are you sure you want to delete this note?')">
                                                        <i class="fas fa-trash me-2 text-danger"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($note['category'])): ?>
                                            <span class="badge bg-info text-dark mb-2">
                                                <?php echo htmlspecialchars($note['category']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <p class="card-text note-content">
                                            <?php 
                                                // Show a preview of the content (first 150 characters)
                                                echo nl2br(htmlspecialchars(
                                                    strlen($note['content']) > 150 ? 
                                                    substr($note['content'], 0, 150) . '...' : 
                                                    $note['content']
                                                )); 
                                            ?>
                                        </p>
                                    </div>
                                    <div class="card-footer text-muted">
                                        <small>
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M d, Y H:i', strtotime($note['updated_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
    <script src="js/notes.js"></script>
</body>
</html>