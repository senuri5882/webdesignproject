<?php
// Start the session
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../includes/db_connection.php';

$registerError = '';
$registerSuccess = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $fullName = trim($_POST['full_name']);
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        $registerError = 'All fields are required';
    } elseif ($password !== $confirmPassword) {
        $registerError = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $registerError = 'Password must be at least 8 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Please enter a valid email address';
    } else {
        // Check if username already exists
        $checkUsername = "SELECT id FROM users WHERE username = ?";
        $stmt = $conn->prepare($checkUsername);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $registerError = 'Username already exists. Please choose another one.';
        } else {
            // Check if email already exists
            $checkEmail = "SELECT id FROM users WHERE email = ?";
            $stmt = $conn->prepare($checkEmail);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $registerError = 'Email already in use. Please use another email or login to your existing account.';
            } else {
                // All validations passed, create new user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $insertQuery = "INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("ssss", $username, $email, $hashedPassword, $fullName);
                
                if ($stmt->execute()) {
                    $registerSuccess = 'Registration successful! You can now login.';
                    
                
                } else {
                    $registerError = 'Registration failed. Please try again later.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Interactive Study Planner</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <style>
        .register-container {
            max-width: 600px;
            margin: 60px auto;
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .password-requirements {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="register-container">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="register-header">
                        <i class="fas fa-book-open fa-3x text-primary mb-3"></i>
                        <h2>Interactive Study Planner</h2>
                        <p class="text-muted">Create your account to get started</p>
                    </div>
                    
                    <?php if (!empty($registerError)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($registerError); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($registerSuccess)): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($registerSuccess); ?>
                            <div class="mt-2">
                                <a href="login.php" class="btn btn-success btn-sm">Login Now</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="register.php" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Choose a username" required 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="Enter your email" required
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       placeholder="Enter your full name"
                                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Create a password" required>
                            </div>
                            <div class="password-requirements mt-1">
                                Password must be at least 8 characters long
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm your password" required>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-0">Already have an account? <a href="login.php">Login</a></p>
                        <p class="mt-2"><small class="text-muted">© <?php echo date('Y'); ?> Kushan Kumarasiri</small></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Optional JavaScript for password validation feedback -->
    <script>
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const form = document.querySelector('form');
        
        // Check passwords match on form submission
        form.addEventListener('submit', function(e) {
            if (passwordInput.value !== confirmInput.value) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
            
            if (passwordInput.value.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
            }
        });
    </script>
</body>
</html>