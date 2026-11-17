<?php 
require_once __DIR__.'/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('/recommender.php');
}

$errors = [];

// Store redirect parameter if provided for after login
if (isset($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validation
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($password)) $errors[] = 'Password is required';
    
    // Authenticate user
    if (empty($errors) && $db) {
        $stmt = $db->prepare("SELECT id, email, password_hash, name, skill_level FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_skill_level'] = $user['skill_level'];
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Set remember me cookie (optional)
            if ($remember) {
                setcookie('remember_token', bin2hex(random_bytes(32)), time() + (86400 * 30), '/');
            }
            
            // Redirect to intended page or default
            $redirect_to = $_SESSION['redirect_after_login'] ?? '/my-plans.php';
            unset($_SESSION['redirect_after_login']);
            redirect($redirect_to, 'Welcome back, ' . $user['name'] . '!', 'success');
        } else {
            $errors[] = 'Invalid email or password';
        }
    }
}

// Check for registration success message
$flash = get_flash();

include __DIR__.'/partials/header.php';
?>

<section class="auth-section">
    <div class="auth-container">
        <div class="auth-card card">
            <div class="auth-header">
                <img src="<?php echo url_path('/assets/logo.png'); ?>" alt="AI Course Recommender" class="auth-logo">
                <h1>Welcome Back</h1>
                <p class="auth-subtitle">Login to continue your learning journey</p>
            </div>

            <?php if ($flash && $flash['type'] === 'success'): ?>
                <div class="alert alert-success">
                    ✓ <?php echo e($flash['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Login Failed</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="input" 
                           value="<?php echo e($_POST['email'] ?? ''); ?>" 
                           placeholder="john@example.com" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="input" 
                           placeholder="Enter your password" required>
                </div>

                <div class="form-group form-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="<?php echo url_path('/forgot-password.php'); ?>" class="link">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Login
                </button>

                <div class="auth-footer">
                    <p>Don't have an account? <a href="<?php echo url_path('/register.php'); ?>" class="link">Register here</a></p>
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

.form-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: var(--text-secondary);
}

.checkbox-label input[type="checkbox"] {
    width: auto;
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
    font-size: 14px;
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

.demo-info {
    margin-top: 24px;
    padding: 16px;
    background: rgba(0, 229, 255, 0.05);
    border: 1px solid rgba(0, 229, 255, 0.2);
    border-radius: 8px;
    font-size: 13px;
    text-align: center;
}

.demo-info code {
    background: var(--bg-darker);
    padding: 2px 8px;
    border-radius: 4px;
    color: var(--brand2);
    font-family: 'Courier New', monospace;
}
</style>

<?php include __DIR__.'/partials/footer.php'; ?>