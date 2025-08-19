<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_content':
                $stmt = $pdo->prepare("UPDATE landing_page_content SET title = ?, subtitle = ?, content = ?, image_url = ?, button_text = ?, button_url = ?, is_active = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['subtitle'],
                    $_POST['content'],
                    $_POST['image_url'],
                    $_POST['button_text'],
                    $_POST['button_url'],
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['content_id']
                ]);
                $success_message = "Content updated successfully!";
                break;
                
            case 'add_announcement':
                $stmt = $pdo->prepare("INSERT INTO landing_announcements (message, display_order) VALUES (?, ?)");
                $stmt->execute([$_POST['message'], $_POST['display_order']]);
                $success_message = "Announcement added successfully!";
                break;
                
            case 'update_announcement':
                $stmt = $pdo->prepare("UPDATE landing_announcements SET message = ?, is_active = ?, display_order = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['message'],
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['display_order'],
                    $_POST['announcement_id']
                ]);
                $success_message = "Announcement updated successfully!";
                break;
                
            case 'delete_announcement':
                $stmt = $pdo->prepare("DELETE FROM landing_announcements WHERE id = ?");
                $stmt->execute([$_POST['announcement_id']]);
                $success_message = "Announcement deleted successfully!";
                break;
        }
    }
}

// Fetch landing page content
$stmt = $pdo->query("SELECT * FROM landing_page_content ORDER BY display_order");
$landingContent = $stmt->fetchAll();

// Fetch announcements
$stmt = $pdo->query("SELECT * FROM landing_announcements ORDER BY display_order");
$announcements = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Page Management - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #7c3aed 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
        }
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .nav-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        .btn-secondary:hover {
            background: #475569;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        .card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #1e293b;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .success-message {
            background: #10b981;
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
        .content-section {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: #f9fafb;
        }
        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .section-title {
            font-weight: 600;
            color: #1f2937;
            text-transform: capitalize;
        }
        .announcement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            background: white;
        }
        .announcement-text {
            flex: 1;
            margin-right: 1rem;
        }
        .announcement-actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
        }
        .image-upload-container {
            border: 2px dashed #d1d5db;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            background: #f9fafb;
        }
        .image-preview {
            margin-bottom: 1rem;
        }
        .no-image {
            color: #6b7280;
            font-style: italic;
            padding: 2rem;
        }
        .upload-controls {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .hero-images-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }
        .hero-image-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            background: white;
        }
        .hero-image-preview {
            width: 100px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.25rem;
        }
        .hero-image-info {
            flex: 1;
        }
        .hero-image-actions {
            display: flex;
            gap: 0.5rem;
        }
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .nav-buttons {
                flex-direction: column;
            }

            .upload-controls {
                flex-direction: column;
            }

            .hero-image-item {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h1>Landing Page Management</h1>
                            <p>Customize your school's landing page content</p>
                        </div>
                        <div>
                            <a href="../index.php" class="btn btn-success" target="_blank"><i class="fas fa-external-link-alt"></i> Preview Landing Page</a>
                        </div>
                    </div>
                </div>
                <?php if (isset($success_message)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <div class="grid">
                    <!-- Landing Page Content Management -->
                    <div class="card">
                        <h2><i class="fas fa-file-alt"></i> Page Content Sections</h2>
                        
                        <?php foreach ($landingContent as $content): ?>
                            <div class="content-section">
                                <div class="section-header">
                                    <h3 class="section-title"><?= htmlspecialchars($content['section_name']) ?></h3>
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_content">
                                    <input type="hidden" name="content_id" value="<?= $content['id'] ?>">
                                    
                                    <div class="form-group">
                                        <label>Title</label>
                                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($content['title']) ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Subtitle</label>
                                        <input type="text" name="subtitle" class="form-control" value="<?= htmlspecialchars($content['subtitle']) ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Content</label>
                                        <textarea name="content" class="form-control"><?= htmlspecialchars($content['content']) ?></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Image</label>
                                        <div class="image-upload-container">
                                            <input type="hidden" name="image_url" id="image_url_<?= $content['id'] ?>" value="<?= htmlspecialchars($content['image_url']) ?>">
                                            <div class="image-preview" id="preview_<?= $content['id'] ?>">
                                                <?php if ($content['image_url']): ?>
                                                    <img src="../<?= htmlspecialchars($content['image_url']) ?>" alt="Current image" style="max-width: 200px; max-height: 150px; border-radius: 0.5rem;">
                                                <?php else: ?>
                                                    <div class="no-image">No image selected</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="upload-controls">
                                                <input type="file" id="file_<?= $content['id'] ?>" accept="image/*" style="display: none;">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('file_<?= $content['id'] ?>').click()">
                                                    <i class="fas fa-upload"></i> Upload Image
                                                </button>
                                                <?php if ($content['image_url']): ?>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeImage(<?= $content['id'] ?>)">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Button Text</label>
                                        <input type="text" name="button_text" class="form-control" value="<?= htmlspecialchars($content['button_text']) ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Button URL</label>
                                        <input type="text" name="button_url" class="form-control" value="<?= htmlspecialchars($content['button_url']) ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="checkbox-group">
                                            <input type="checkbox" name="is_active" id="active_<?= $content['id'] ?>" <?= $content['is_active'] ? 'checked' : '' ?>>
                                            <label for="active_<?= $content['id'] ?>">Active</label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Section
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Announcements Management -->
                    <div class="card">
                        <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
                        
                        <!-- Add New Announcement -->
                        <div class="content-section">
                            <h3>Add New Announcement</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_announcement">
                                
                                <div class="form-group">
                                    <label>Message</label>
                                    <textarea name="message" class="form-control" required placeholder="Enter announcement message..."></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Display Order</label>
                                    <input type="number" name="display_order" class="form-control" value="<?= count($announcements) + 1 ?>" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Announcement
                                </button>
                            </form>
                        </div>

                        <!-- Existing Announcements -->
                        <div class="content-section">
                            <h3>Existing Announcements</h3>
                            
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="announcement-item">
                                    <div class="announcement-text">
                                        <strong><?= htmlspecialchars($announcement['message']) ?></strong>
                                        <br>
                                        <small>Order: <?= $announcement['display_order'] ?> | Status: <?= $announcement['is_active'] ? 'Active' : 'Inactive' ?></small>
                                    </div>
                                    <div class="announcement-actions">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="editAnnouncement(<?= $announcement['id'] ?>, '<?= htmlspecialchars($announcement['message'], ENT_QUOTES) ?>', <?= $announcement['is_active'] ?>, <?= $announcement['display_order'] ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_announcement">
                                            <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Announcement Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 1rem; width: 90%; max-width: 500px;">
            <h3>Edit Announcement</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_announcement">
                <input type="hidden" name="announcement_id" id="editAnnouncementId">
                
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" id="editMessage" class="form-control" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" id="editOrder" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="editActive">
                        <label for="editActive">Active</label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editAnnouncement(id, message, isActive, displayOrder) {
            document.getElementById('editAnnouncementId').value = id;
            document.getElementById('editMessage').value = message;
            document.getElementById('editOrder').value = displayOrder;
            document.getElementById('editActive').checked = isActive;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Image upload functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners for file inputs
            document.querySelectorAll('input[type="file"]').forEach(function(input) {
                input.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        uploadImage(file, this.id);
                    }
                });
            });
        });

        function uploadImage(file, inputId) {
            const contentId = inputId.replace('file_', '');
            const formData = new FormData();
            formData.append('image', file);

            // Show loading state
            const preview = document.getElementById('preview_' + contentId);
            preview.innerHTML = '<div class="no-image">Uploading...</div>';

            fetch('upload-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update hidden input
                    document.getElementById('image_url_' + contentId).value = data.url;
                    
                    // Update preview
                    preview.innerHTML = '<img src="../' + data.url + '" alt="Uploaded image" style="max-width: 200px; max-height: 150px; border-radius: 0.5rem;">';
                    
                    // Update upload controls
                    const controls = preview.parentNode.querySelector('.upload-controls');
                    if (!controls.querySelector('.btn-danger')) {
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'btn btn-danger btn-sm';
                        removeBtn.onclick = function() { removeImage(contentId); };
                        removeBtn.innerHTML = '<i class="fas fa-trash"></i> Remove';
                        controls.appendChild(removeBtn);
                    }
                    
                    alert('Image uploaded successfully!');
                } else {
                    preview.innerHTML = '<div class="no-image">Upload failed: ' + data.error + '</div>';
                    alert('Upload failed: ' + data.error);
                }
            })
            .catch(error => {
                preview.innerHTML = '<div class="no-image">Upload error</div>';
                alert('Upload error: ' + error.message);
            });
        }

        function removeImage(contentId) {
            if (confirm('Are you sure you want to remove this image?')) {
                // Clear hidden input
                document.getElementById('image_url_' + contentId).value = '';
                
                // Update preview
                document.getElementById('preview_' + contentId).innerHTML = '<div class="no-image">No image selected</div>';
                
                // Remove the remove button
                const controls = document.getElementById('preview_' + contentId).parentNode.querySelector('.upload-controls');
                const removeBtn = controls.querySelector('.btn-danger');
                if (removeBtn) {
                    removeBtn.remove();
                }
            }
        }
    </script>
</body>
</html>
