<?php
require_once 'db.php';
require_once 'config.php';

// Initialize database connection
$pdo = getDBConnection();

// Handle form submissions
$message = '';
$message_type = '';

// Add new service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $unit_price = floatval($_POST['unit_price']);
    $category = trim($_POST['category']);
    
    if (!empty($name) && $unit_price > 0) {
        try {
            $stmt = $pdo->prepare('INSERT INTO services (name, description, unit_price, category) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $description, $unit_price, $category]);
            $message = 'Service added successfully!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Error adding service: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = 'Please provide service name and valid price.';
        $message_type = 'error';
    }
}

// Update service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service'])) {
    $id = intval($_POST['service_id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $unit_price = floatval($_POST['unit_price']);
    $category = trim($_POST['category']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (!empty($name) && $unit_price > 0) {
        try {
            $stmt = $pdo->prepare('UPDATE services SET name = ?, description = ?, unit_price = ?, category = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$name, $description, $unit_price, $category, $is_active, $id]);
            $message = 'Service updated successfully!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Error updating service: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Delete service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service'])) {
    $id = intval($_POST['service_id']);
    try {
        $stmt = $pdo->prepare('DELETE FROM services WHERE id = ?');
        $stmt->execute([$id]);
        $message = 'Service deleted successfully!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Error deleting service: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Fetch all services
$stmt = $pdo->query('SELECT * FROM services ORDER BY category, name');
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories = array_unique(array_column($services, 'category'));
sort($categories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            color: #555;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .service-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #667eea;
            transition: all 0.2s;
        }
        
        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        
        .service-card.inactive {
            opacity: 0.6;
            border-left-color: #9ca3af;
        }
        
        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        
        .service-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .service-category {
            display: inline-block;
            padding: 4px 12px;
            background: #667eea;
            color: white;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .service-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        
        .service-price {
            font-size: 24px;
            font-weight: bold;
            color: #10b981;
            margin-bottom: 12px;
        }
        
        .service-actions {
            display: flex;
            gap: 8px;
        }
        
        .service-actions .btn {
            padding: 8px 16px;
            font-size: 12px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            color: #333;
            font-size: 24px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🛠️ Manage Services</h1>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
                <button onclick="openAddModal()" class="btn btn-primary">+ Add New Service</button>
            </div>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php if ($message_type === 'success'): ?>✓<?php else: ?>✗<?php endif; ?>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Services List -->
        <div class="card">
            <h2>All Services (<?php echo count($services); ?>)</h2>
            
            <?php if (empty($services)): ?>
                <p style="text-align: center; color: #666; padding: 40px;">No services yet. Add your first service to get started.</p>
            <?php else: ?>
                <div class="services-grid">
                    <?php foreach ($services as $service): ?>
                        <div class="service-card <?php echo $service['is_active'] ? '' : 'inactive'; ?>">
                            <div class="service-header">
                                <div>
                                    <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                                    <?php if ($service['category']): ?>
                                        <span class="service-category"><?php echo htmlspecialchars($service['category']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($service['description']): ?>
                                <div class="service-description"><?php echo htmlspecialchars($service['description']); ?></div>
                            <?php endif; ?>
                            
                            <div class="service-price">$<?php echo number_format($service['unit_price'], 2); ?></div>
                            
                            <div class="service-actions">
                                <button onclick='openEditModal(<?php echo json_encode($service); ?>)' class="btn btn-warning">✏️ Edit</button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this service?');">
                                    <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                    <button type="submit" name="delete_service" class="btn btn-danger">🗑️ Delete</button>
                                </form>
                            </div>
                            
                            <?php if (!$service['is_active']): ?>
                                <div style="margin-top: 10px; color: #ef4444; font-size: 12px; font-weight: 500;">⚠️ Inactive</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Service Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Service</h3>
                <button class="close-modal" onclick="closeAddModal()">×</button>
            </div>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Service Name *</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" list="categories" placeholder="e.g., Web Development">
                        <datalist id="categories">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Unit Price ($) *</label>
                    <input type="number" name="unit_price" step="0.01" min="0" required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="add_service" class="btn btn-success">Add Service</button>
                    <button type="button" onclick="closeAddModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Service Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Service</h3>
                <button class="close-modal" onclick="closeEditModal()">×</button>
            </div>
            <form method="post" id="editForm">
                <input type="hidden" name="service_id" id="edit_service_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Service Name *</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" id="edit_category" list="categories">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Unit Price ($) *</label>
                    <input type="number" name="unit_price" id="edit_unit_price" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <label for="edit_is_active">Active</label>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="update_service" class="btn btn-success">Update Service</button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }
        
        function openEditModal(service) {
            document.getElementById('edit_service_id').value = service.id;
            document.getElementById('edit_name').value = service.name;
            document.getElementById('edit_category').value = service.category || '';
            document.getElementById('edit_description').value = service.description || '';
            document.getElementById('edit_unit_price').value = service.unit_price;
            document.getElementById('edit_is_active').checked = service.is_active == 1;
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
