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

if (empty($password)) {
    $errors[] = 'Password is required';
} else {
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }

    if (!preg_match('/[A-Za-z]/', $password)) {
        $errors[] = 'Password must contain at least one letter';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
}

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match';
}

    
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
    <div class="password-field">
        <input type="password" id="password" name="password"
               class="input" placeholder="Create a strong password" required>

        <button type="button" class="password-toggle"
                aria-label="Show password" aria-pressed="false"
                onclick="togglePasswordField('password', this)">
            <svg class="icon-eye" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7C20.268 16.057 16.478 19 12 19S3.732 16.057 2.458 12z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 3l18 18"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M10.584 10.587a2 2 0 002.829 2.828"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9.88 5.09A9.77 9.77 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.721 9.721 0 01-4.16 5.146M6.228 6.228A9.72 9.72 0 002.458 12c1.274 4.057 5.064 7 9.542 7a9.77 9.77 0 004.09-.89"/>
            </svg>
        </button>
    </div>
    
<small class="helper" id="password-requirements">
    Password must contain:
    <ul style="margin: 6px 0 0 20px; padding: 0;">
        <li>At least 8 characters</li>
        <li>At least one letter (A-Z)</li>
        <li>At least one number (0-9)</li>
    </ul>
</small>

</div>

<div class="form-group">
    <label for="confirm_password">Confirm Password</label>
    <div class="password-field">
        <input type="password" id="confirm_password"
               name="confirm_password" class="input"
               placeholder="Re-enter password" required>

        <button type="button" class="password-toggle"
                aria-label="Show confirm password" aria-pressed="false"
                onclick="togglePasswordField('confirm_password', this)">
            <svg class="icon-eye" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7C20.268 16.057 16.478 19 12 19S3.732 16.057 2.458 12z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 3l18 18"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M10.584 10.587a2 2 0 002.829 2.828"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9.88 5.09A9.77 9.77 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.721 9.721 0 01-4.16 5.146M6.228 6.228A9.72 9.72 0 002.458 12c1.274 4.057 5.064 7 9.542 7a9.77 9.77 0 004.09-.89"/>
            </svg>
        </button>
    </div>
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


/* Password visibility toggle */
.password-field {
    position: relative;
}

.password-field .input {
    padding-right: 52px;
}

.password-toggle {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    width: 34px;
    height: 34px;
    border: none;
    background: transparent;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s ease;
}

.password-toggle:hover {
    background: rgba(124, 77, 255, 0.12);
    color: var(--brand);
}

.password-toggle:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 2px;
}

.password-toggle svg {
    width: 20px;
    height: 20px;
}

.password-toggle .icon-eye-off {
    display: none;
}

.password-toggle.active .icon-eye {
    display: none;
}

.password-toggle.active .icon-eye-off {
    display: block;
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


<script>
function togglePasswordField(fieldId, button) {
    const passwordInput = document.getElementById(fieldId);

    // Check whether the password is currently hidden
    const isHidden = passwordInput.type === 'password';

    // Switch between hidden and visible
    passwordInput.type = isHidden ? 'text' : 'password';

    // Switch the eye icon
    button.classList.toggle('active', isHidden);

    // Update accessibility labels
    button.setAttribute(
        'aria-label',
        isHidden ? 'Hide password' : 'Show password'
    );

    button.setAttribute(
        'aria-pressed',
        isHidden ? 'true' : 'false'
    );
}
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('password');
    const requirements = document.querySelectorAll('#password-requirements li');

    // Store the original requirement descriptions
    const labels = Array.from(requirements, function (item) {
        return item.textContent.trim();
    });

    function validatePasswordLive() {
        const password = passwordInput.value;

        // Check all three password requirements
        const checks = [
            password.length >= 8,
            /[A-Za-z]/.test(password),
            /[0-9]/.test(password)
        ];

        requirements.forEach(function (item, index) {
            const valid = checks[index];

            item.style.color = valid ? '#22c55e' : '#ef4444';
            item.style.listStyleType = 'none';
            item.textContent = (valid ? '✓ ' : '✗ ') + labels[index];
        });
    }

    // Update requirements whenever the user types
    passwordInput.addEventListener('input', validatePasswordLive);

    // Check the initial password state
    validatePasswordLive();
});
</script>


<?php include __DIR__.'/partials/footer.php'; ?>