<?php 
require_once __DIR__.'/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('/recommender.php');
}

$errors = [];

// Store redirect parameter if provided for after registration
if (isset($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $skill_level = $_POST['skill_level'] ?? 'Beginner';
    
    // Validation
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (empty($password)) $errors[] = 'Password is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
    
    // Check if email already exists
    if (empty($errors) && $db) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered. Please login instead.';
        }
    }
    
    // Register user
    if (empty($errors) && $db) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, name, skill_level) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$email, $password_hash, $name, $skill_level])) {
            // Auto-login after successful registration
            $user_id = $db->lastInsertId();
            $_SESSION["user_id"] = $user_id;
            $_SESSION["user_email"] = $email;
            $_SESSION["user_name"] = $name;
            $_SESSION["user_skill_level"] = $skill_level;
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $success = true;
            $redirect_to = $_SESSION['redirect_after_login'] ?? '/recommender.php';
            unset($_SESSION['redirect_after_login']);
            redirect($redirect_to, "Welcome to AI Course Recommender, " . $name . "!", "success");
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

include __DIR__.'/partials/header.php';
?>

<section class="auth-section">
    <div class="auth-container">
        <div class="auth-card card">
            <div class="auth-header">
                <img src="<?php echo url_path('/assets/logo.png'); ?>" alt="AI Course Recommender" class="auth-logo">
                <h1>Create Account</h1>
                <p class="auth-subtitle">Join us to save and track your learning journey</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="input" 
                           value="<?php echo e($_POST['name'] ?? ''); ?>" 
                           placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="input" 
                           value="<?php echo e($_POST['email'] ?? ''); ?>" 
                           placeholder="john@example.com" required>
                </div>

                <div class="form-group">
                    <label for="skill_level">Current Skill Level</label>
                    <select id="skill_level" name="skill_level" class="input" required>
                        <option value="Beginner" <?php echo ($_POST['skill_level'] ?? '') === 'Beginner' ? 'selected' : ''; ?>>Beginner</option>
                        <option value="Intermediate" <?php echo ($_POST['skill_level'] ?? '') === 'Intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="Advanced" <?php echo ($_POST['skill_level'] ?? '') === 'Advanced' ? 'selected' : ''; ?>>Advanced</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="input" 
                           placeholder="Min. 6 characters" required>
                    <small class="helper">At least 6 characters</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="input" 
                           placeholder="Re-enter password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Create Account
                </button>

                <div class="auth-footer">
                    <p>Already have an account? <a href="<?php echo url_path('/login.php'); ?>" class="link">Login here</a></p>
                </div>
            </form>
        </div>
    </div>
</section>

<style>
.auth-section {
    min-height: calc(100vh - 60px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    margin-top: 60px;
}

.auth-container {
    width: 100%;
    max-width: 480px;
}

.auth-card {
    padding: 40px;
}

.auth-header {
    text-align: center;
    margin-bottom: 32px;
}

.auth-logo {
    width: 80px;
    height: 80px;
    margin: 0 auto 24px;
    display: block;
    object-fit: contain;
}

.auth-header h1 {
    margin-bottom: 8px;
    background: linear-gradient(90deg, var(--brand), var(--brand2));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.auth-subtitle {
    color: var(--text-muted);
    font-size: 14px;
}

.auth-form .form-group {
    margin-bottom: 20px;
}

.auth-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-primary);
}

.auth-form .input {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg-darker);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 14px;
    transition: all 0.3s ease;
}

.auth-form .input:focus {
    outline: none;
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(124, 77, 255, 0.1);
}

.helper {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: var(--text-muted);
}

.btn-block {
    width: 100%;
    margin-top: 24px;
}

.auth-footer {
    margin-top: 24px;
    text-align: center;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.auth-footer p {
    color: var(--text-muted);
    font-size: 14px;
}

.link {
    color: var(--brand);
    text-decoration: none;
    font-weight: 500;
}

.link:hover {
    text-decoration: underline;
}

.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.alert-error {
    background: rgba(255, 68, 68, 0.1);
    border: 1px solid rgba(255, 68, 68, 0.3);
    color: #ff4444;
}

.alert ul {
    margin: 8px 0 0 20px;
    font-size: 14px;
}

.alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}
</style>

<?php include __DIR__.'/partials/footer.php'; ?>