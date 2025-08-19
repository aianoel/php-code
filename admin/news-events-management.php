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
            case 'add_news':
                $stmt = $pdo->prepare("INSERT INTO news (title, excerpt, content, date, image_url, display_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['excerpt'],
                    $_POST['content'],
                    $_POST['date'],
                    $_POST['image_url'],
                    $_POST['display_order']
                ]);
                $success_message = "News article added successfully!";
                break;
                
            case 'update_news':
                $stmt = $pdo->prepare("UPDATE news SET title = ?, excerpt = ?, content = ?, date = ?, image_url = ?, is_active = ?, display_order = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['excerpt'],
                    $_POST['content'],
                    $_POST['date'],
                    $_POST['image_url'],
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['display_order'],
                    $_POST['news_id']
                ]);
                $success_message = "News article updated successfully!";
                break;
                
            case 'delete_news':
                $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
                $stmt->execute([$_POST['news_id']]);
                $success_message = "News article deleted successfully!";
                break;
                
            case 'add_event':
                $stmt = $pdo->prepare("INSERT INTO events (title, description, date, time, location, image_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['date'],
                    $_POST['time'],
                    $_POST['location'],
                    $_POST['image_url'],
                    $_POST['display_order']
                ]);
                $success_message = "Event added successfully!";
                break;
                
            case 'update_event':
                $stmt = $pdo->prepare("UPDATE events SET title = ?, description = ?, date = ?, time = ?, location = ?, image_url = ?, is_active = ?, display_order = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['date'],
                    $_POST['time'],
                    $_POST['location'],
                    $_POST['image_url'],
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['display_order'],
                    $_POST['event_id']
                ]);
                $success_message = "Event updated successfully!";
                break;
                
            case 'delete_event':
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                $stmt->execute([$_POST['event_id']]);
                $success_message = "Event deleted successfully!";
                break;
        }
    }
}

// Fetch news
$stmt = $pdo->query("SELECT * FROM news ORDER BY display_order, date DESC");
$news = $stmt->fetchAll();

// Fetch events
$stmt = $pdo->query("SELECT * FROM events ORDER BY display_order, date ASC");
$events = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Events Management - Admin Dashboard</title>
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
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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
        .tabs {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 2rem;
        }
        .tab {
            padding: 1rem 2rem;
            background: none;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .item-list {
            display: grid;
            gap: 1rem;
        }
        .item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            background: #f9fafb;
        }
        .item-content {
            flex: 1;
            margin-right: 1rem;
        }
        .item-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        .item-meta {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        .item-excerpt {
            color: #4b5563;
        }
        .item-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .nav-buttons {
                flex-direction: column;
            }

            .item {
                flex-direction: column;
            }

            .item-actions {
                margin-top: 1rem;
                justify-content: flex-start;
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
                            <h1>News & Events Management</h1>
                            <p>Manage news articles and events</p>
                        </div>
                        <div>
                            <button onclick="openModal('createNewsModal')" class="btn btn-success"><i class="fas fa-plus"></i> Add News</button>
                            <button onclick="openModal('createEventModal')" class="btn btn-warning"><i class="fas fa-calendar-plus"></i> Add Event</button>
                        </div>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="tabs">
                        <button class="tab active" onclick="switchTab('news')">
                            <i class="fas fa-newspaper"></i> News Articles
                        </button>
                        <button class="tab" onclick="switchTab('events')">
                            <i class="fas fa-calendar-alt"></i> Events
                        </button>
                    </div>

                    <!-- News Tab -->
                    <div id="news-tab" class="tab-content active">
                        <div class="grid">
                            <!-- Add News Form -->
                            <div>
                                <h3>Add News Article</h3>
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_news">
                                    
                                    <div class="form-group">
                                        <label>Title</label>
                                        <input type="text" name="title" class="form-control" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Excerpt</label>
                                        <textarea name="excerpt" class="form-control" placeholder="Brief summary..."></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Content</label>
                                        <textarea name="content" class="form-control" rows="4" placeholder="Full article content..."></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Date</label>
                                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Image URL</label>
                                        <input type="url" name="image_url" class="form-control" placeholder="https://...">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Display Order</label>
                                        <input type="number" name="display_order" class="form-control" value="<?= count($news) + 1 ?>" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add News Article
                                    </button>
                                </form>
                            </div>

                            <!-- News List -->
                            <div>
                                <h3>Existing News Articles</h3>
                                <div class="item-list">
                                    <?php foreach ($news as $article): ?>
                                        <div class="item">
                                            <div class="item-content">
                                                <div class="item-title"><?= htmlspecialchars($article['title']) ?></div>
                                                <div class="item-meta">
                                                    <?= date('F j, Y', strtotime($article['date'])) ?> | 
                                                    Order: <?= $article['display_order'] ?> | 
                                                    Status: <?= $article['is_active'] ? 'Active' : 'Inactive' ?>
                                                </div>
                                                <div class="item-excerpt"><?= htmlspecialchars($article['excerpt']) ?></div>
                                            </div>
                                            <div class="item-actions">
                                                <button type="button" class="btn btn-sm btn-primary" onclick="editNews(<?= htmlspecialchars(json_encode($article)) ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_news">
                                                    <input type="hidden" name="news_id" value="<?= $article['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
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

                    <!-- Events Tab -->
                    <div id="events-tab" class="tab-content">
                        <div class="grid">
                            <!-- Add Event Form -->
                            <div>
                                <h3>Add Event</h3>
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_event">
                                    
                                    <div class="form-group">
                                        <label>Title</label>
                                        <input type="text" name="title" class="form-control" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="description" class="form-control" rows="3" placeholder="Event description..."></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Date</label>
                                        <input type="date" name="date" class="form-control" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Time</label>
                                        <input type="time" name="time" class="form-control">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Location</label>
                                        <input type="text" name="location" class="form-control" placeholder="Event location...">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Image URL</label>
                                        <input type="url" name="image_url" class="form-control" placeholder="https://...">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Display Order</label>
                                        <input type="number" name="display_order" class="form-control" value="<?= count($events) + 1 ?>" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add Event
                                    </button>
                                </form>
                            </div>

                            <!-- Events List -->
                            <div>
                                <h3>Existing Events</h3>
                                <div class="item-list">
                                    <?php foreach ($events as $event): ?>
                                        <div class="item">
                                            <div class="item-content">
                                                <div class="item-title"><?= htmlspecialchars($event['title']) ?></div>
                                                <div class="item-meta">
                                                    <?= date('F j, Y', strtotime($event['date'])) ?>
                                                    <?php if ($event['time']): ?>
                                                        at <?= date('g:i A', strtotime($event['time'])) ?>
                                                    <?php endif; ?>
                                                    <?php if ($event['location']): ?>
                                                        | <?= htmlspecialchars($event['location']) ?>
                                                    <?php endif; ?>
                                                    <br>Order: <?= $event['display_order'] ?> | 
                                                    Status: <?= $event['is_active'] ? 'Active' : 'Inactive' ?>
                                                </div>
                                                <div class="item-excerpt"><?= htmlspecialchars($event['description']) ?></div>
                                            </div>
                                            <div class="item-actions">
                                                <button type="button" class="btn btn-sm btn-primary" onclick="editEvent(<?= htmlspecialchars(json_encode($event)) ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_event">
                                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
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
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modals -->
    <div id="editNewsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 1rem; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <h3>Edit News Article</h3>
            <form method="POST" id="editNewsForm">
                <input type="hidden" name="action" value="update_news">
                <input type="hidden" name="news_id" id="editNewsId">
                
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" id="editNewsTitle" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Excerpt</label>
                    <textarea name="excerpt" id="editNewsExcerpt" class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" id="editNewsContent" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" id="editNewsDate" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Image URL</label>
                    <input type="url" name="image_url" id="editNewsImage" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" id="editNewsOrder" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="editNewsActive">
                        <label for="editNewsActive">Active</label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editNewsModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editEventModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 1rem; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <h3>Edit Event</h3>
            <form method="POST" id="editEventForm">
                <input type="hidden" name="action" value="update_event">
                <input type="hidden" name="event_id" id="editEventId">
                
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" id="editEventTitle" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="editEventDescription" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" id="editEventDate" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Time</label>
                    <input type="time" name="time" id="editEventTime" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" id="editEventLocation" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Image URL</label>
                    <input type="url" name="image_url" id="editEventImage" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" id="editEventOrder" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="editEventActive">
                        <label for="editEventActive">Active</label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editEventModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tabBtn => {
                tabBtn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tab + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function editNews(article) {
            document.getElementById('editNewsId').value = article.id;
            document.getElementById('editNewsTitle').value = article.title;
            document.getElementById('editNewsExcerpt').value = article.excerpt || '';
            document.getElementById('editNewsContent').value = article.content || '';
            document.getElementById('editNewsDate').value = article.date;
            document.getElementById('editNewsImage').value = article.image_url || '';
            document.getElementById('editNewsOrder').value = article.display_order;
            document.getElementById('editNewsActive').checked = article.is_active;
            document.getElementById('editNewsModal').style.display = 'block';
        }

        function editEvent(event) {
            document.getElementById('editEventId').value = event.id;
            document.getElementById('editEventTitle').value = event.title;
            document.getElementById('editEventDescription').value = event.description || '';
            document.getElementById('editEventDate').value = event.date;
            document.getElementById('editEventTime').value = event.time || '';
            document.getElementById('editEventLocation').value = event.location || '';
            document.getElementById('editEventImage').value = event.image_url || '';
            document.getElementById('editEventOrder').value = event.display_order;
            document.getElementById('editEventActive').checked = event.is_active;
            document.getElementById('editEventModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modals when clicking outside
        document.querySelectorAll('[id$="Modal"]').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    </script>
</body>
</html>
