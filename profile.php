<?php 
require_once __DIR__.'/config.php';
require_auth();

$user = get_user_data();
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $skill_level = $_POST['skill_level'] ?? 'Beginner';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    
    // Check if email is already taken by another user
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already taken by another user';
        }
    }
    
    // Password change validation
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = 'Current password required to change password';
        } else {
            // Verify current password
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user_data = $stmt->fetch();
            
            if (!password_verify($current_password, $user_data['password_hash'])) {
                $errors[] = 'Current password is incorrect';
            }
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match';
        }
    }
    
    // Update user
    if (empty($errors)) {
        if (!empty($new_password)) {
            // Update with new password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, skill_level = ?, password_hash = ? WHERE id = ?");
            $stmt->execute([$name, $email, $skill_level, $password_hash, $_SESSION['user_id']]);
        } else {
            // Update without password change
            $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, skill_level = ? WHERE id = ?");
            $stmt->execute([$name, $email, $skill_level, $_SESSION['user_id']]);
        }
        
        // Update session
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_skill_level'] = $skill_level;
        
        $success = true;
        
        // Refresh user data
        $user = get_user_data();
    }
}

include __DIR__.'/partials/header.php';
?>

<section style="padding: 100px 20px 40px; min-height: calc(100vh - 60px);">
    <div style="max-width: 800px; margin: 0 auto;">
        
        <div style="margin-bottom: 40px;">
            <h1 style="margin-bottom: 8px;">⚙️ Account Settings</h1>
            <p style="color: var(--text-muted); font-size: 14px;">Manage your profile and preferences</p>
        </div>

        <?php if ($success): ?>
            <div style="padding: 16px; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 8px; color: #22c55e; margin-bottom: 24px;">
                ✓ Settings updated successfully!
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div style="padding: 16px; background: rgba(255, 68, 68, 0.1); border: 1px solid rgba(255, 68, 68, 0.3); border-radius: 8px; color: #ff4444; margin-bottom: 24px;">
                <strong>⚠️ Please fix the following errors:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Profile Settings -->
        <div class="card" style="padding: 32px; margin-bottom: 24px;">
            <h2 style="margin-bottom: 24px; font-size: 20px;">Profile Information</h2>
            
            <form method="POST" action="">
                <div style="display: grid; gap: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">
                            Full Name
                        </label>
                        <input 
                            type="text" 
                            name="name" 
                            value="<?php echo e($user['name']); ?>" 
                            required
                            style="width: 100%; padding: 12px 16px; background: var(--bg-darker); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 14px;"
                        />
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">
                            Email Address
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            value="<?php echo e($user['email']); ?>" 
                            required
                            style="width: 100%; padding: 12px 16px; background: var(--bg-darker); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 14px;"
                        />
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">
                            Skill Level
                        </label>
                        <select 
                            name="skill_level" 
                            required
                            style="width: 100%; padding: 12px 16px; background: var(--bg-darker); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 14px;"
                        >
                            <option value="Beginner" <?php echo $user['skill_level'] === 'Beginner' ? 'selected' : ''; ?>>Beginner</option>
                            <option value="Intermediate" <?php echo $user['skill_level'] === 'Intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="Advanced" <?php echo $user['skill_level'] === 'Advanced' ? 'selected' : ''; ?>>Advanced</option>
                        </select>
                    </div>

                    <div style="padding-top: 16px; border-top: 1px solid var(--border-color);">
                        <h3 style="margin-bottom: 16px; font-size: 16px;">Change Password (Optional)</h3>
                        
                        <div style="display: grid; gap: 16px;">
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">
                                    Current Password
                                </label>
                                <input 
                                    type="password" 
                                    name="current_password" 
                                    style="width: 100%; padding: 12px 16px; background: var(--bg-darker); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 14px;"
                                />
                            </div>

                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">
                                    New Password
                                </label>
                                <input 
                                    type="password" 
                                    name="new_password" 
                                    style="width: 100%; padding: 12px 16px; background: var(--bg-darker); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 14px;"
                                />
                                <small style="display: block; margin-top: 4px; font-size: 12px; color: var(--text-muted);">
                                    Min. 6 characters (leave blank to keep current password)
                                </small>
                            </div>

                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">
                                    Confirm New Password
                                </label>
                                <input 
                                    type="password" 
                                    name="confirm_password" 
                                    style="width: 100%; padding: 12px 16px; background: var(--bg-darker); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 14px;"
                                />
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; padding-top: 16px;">
                        <button type="submit" class="btn btn-primary" style="padding: 12px 32px;">
                            Save Changes
                        </button>
                        <a href="<?php echo url_path('/my-plans.php'); ?>" class="btn" style="padding: 12px 32px;">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Account Stats -->
        <div class="card" style="padding: 32px;">
            <h2 style="margin-bottom: 24px; font-size: 20px;">Account Statistics</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                        <?php 
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM learning_plans WHERE user_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        echo $stmt->fetch()['count'];
                        ?>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted);">Total Plans</div>
                </div>
                
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                        <?php 
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM progress WHERE user_id = ? AND completed = 1");
                        $stmt->execute([$_SESSION['user_id']]);
                        echo $stmt->fetch()['count'];
                        ?>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted);">Topics Completed</div>
                </div>
                
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted);">Member Since</div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__.'/partials/footer.php'; ?>