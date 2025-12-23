<?php
/**
 * User Management Page
 * Allows admins to add, edit, and manage system users
 */

require_once 'auth.php';
require_once 'config.php';
require_once 'db.php';

// Require admin authentication
requireAuth();
requireRole('admin');

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add new user
    if ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'user';
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            $error = 'All fields are required.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            try {
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Username or email already exists.';
                } else {
                    // Hash password and insert user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, full_name, role, is_active) 
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    
                    $stmt->execute([$username, $email, $hashed_password, $full_name, $role]);
                    $message = "User '$username' created successfully!";
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
    
    // Toggle user active status
    elseif ($action === 'toggle_status') {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($user_id > 0 && $user_id != $_SESSION['user_id']) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'User status updated successfully!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            $error = 'Cannot modify your own account status.';
        }
    }
    
    // Delete user
    elseif ($action === 'delete_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($user_id > 0 && $user_id != $_SESSION['user_id']) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'User deleted successfully!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            $error = 'Cannot delete your own account.';
        }
    }
    
    // Reset password
    elseif ($action === 'reset_password') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';
        
        if (empty($new_password)) {
            $error = 'Password is required.';
        } elseif ($new_password !== $confirm_new_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $message = 'Password reset successfully!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
    
    // Edit user
    elseif ($action === 'edit_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        
        // Validation
        if (empty($username) || empty($email) || empty($full_name)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            try {
                // Check if username or email already exists for other users
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $user_id]);
                
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Username or email already exists.';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $full_name, $user_id]);
                    $message = 'User updated successfully!';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
    
    // Change user role
    elseif ($action === 'change_role') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $new_role = $_POST['new_role'] ?? '';
        
        if ($user_id > 0 && $user_id != $_SESSION['user_id']) {
            if (in_array($new_role, ['admin', 'user'])) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$new_role, $user_id]);
                    $message = "User role changed to '$new_role' successfully!";
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid role specified.';
            }
        } else {
            $error = 'Cannot change your own role.';
        }
    }
}

// Fetch all users
try {
    $stmt = $pdo->query("
        SELECT id, username, email, full_name, role, is_active, last_login, created_at 
        FROM users 
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching users: ' . $e->getMessage();
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - GeekMobile Invoice System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .user-management {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .add-user-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
        }
        .users-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .users-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .users-table th {
            background: #343a40;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 500;
        }
        .users-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .users-table tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-admin {
            background: #007bff;
            color: white;
        }
        .badge-user {
            background: #6c757d;
            color: white;
        }
        .badge-active {
            background: #28a745;
            color: white;
        }
        .badge-inactive {
            background: #dc3545;
            color: white;
        }
        .btn-group {
            display: flex;
            gap: 5px;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .btn-sm:hover {
            opacity: 0.8;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .modal-header {
            margin-bottom: 20px;
        }
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        .close:hover {
            color: #000;
        }
    </style>
</head>
<body>
    <div class="user-management">
        <h1>👥 User Management</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Add New User Form -->
        <div class="add-user-form">
            <h2>➕ Add New User</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </div>
        
        <!-- Users List -->
        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px;">No users found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                        <?php echo strtoupper($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <div class="btn-group" style="flex-wrap: wrap;">
                                            <button onclick="openEditUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>')" 
                                                    class="btn-sm btn-info" title="Edit User" style="background: #28a745;">
                                                ✏️ Edit
                                            </button>
                                            
                                            <button onclick="openResetPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')" 
                                                    class="btn-sm btn-info" title="Reset Password">
                                                🔑 Reset
                                            </button>
                                            
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Change user role to <?php echo $user['role'] === 'admin' ? 'user' : 'admin'; ?>?');">
                                                <input type="hidden" name="action" value="change_role">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="new_role" value="<?php echo $user['role'] === 'admin' ? 'user' : 'admin'; ?>">
                                                <button type="submit" class="btn-sm" style="background: #6f42c1; color: white;" title="Change Role">
                                                    <?php echo $user['role'] === 'admin' ? '👤 Make User' : '👑 Make Admin'; ?>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle user status?');">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn-sm btn-warning" title="Toggle Status">
                                                    <?php echo $user['is_active'] ? '🚫 Disable' : '✅ Enable'; ?>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn-sm btn-danger" title="Delete User">
                                                    🗑️ Delete
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-size: 12px;">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="index.php" class="btn">← Back to Dashboard</a>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeEditUserModal()">&times;</span>
                <h3>✏️ Edit User</h3>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label for="edit_username">Username *</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email *</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_full_name">Full Name *</label>
                    <input type="text" id="edit_full_name" name="full_name" required>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Update User</button>
                    <button type="button" class="btn" onclick="closeEditUserModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeResetPasswordModal()">&times;</span>
                <h3>🔑 Reset Password</h3>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                
                <p>Reset password for user: <strong id="reset_username"></strong></p>
                
                <div class="form-group">
                    <label for="new_password">New Password *</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_new_password">Confirm New Password *</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required minlength="6">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                    <button type="button" class="btn" onclick="closeResetPasswordModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Edit User Modal Functions
        function openEditUserModal(userId, username, email, fullName) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('editUserModal').style.display = 'block';
        }
        
        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
            document.getElementById('edit_username').value = '';
            document.getElementById('edit_email').value = '';
            document.getElementById('edit_full_name').value = '';
        }
        
        // Reset Password Modal Functions
        function openResetPasswordModal(userId, username) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_username').textContent = username;
            document.getElementById('resetPasswordModal').style.display = 'block';
        }
        
        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').style.display = 'none';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_new_password').value = '';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editUserModal');
            const resetModal = document.getElementById('resetPasswordModal');
            
            if (event.target == editModal) {
                closeEditUserModal();
            }
            if (event.target == resetModal) {
                closeResetPasswordModal();
            }
        }
    </script>
</body>
</html>
