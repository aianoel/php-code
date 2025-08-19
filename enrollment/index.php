<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

$error = '';
$success = '';
$currentStep = 1;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'next':
                // Store current step data in session
                foreach ($_POST as $key => $value) {
                    if ($key !== 'action') {
                        $_SESSION['enrollment_data'][$key] = sanitize_input($value);
                    }
                }
                $currentStep = min(5, intval($_POST['current_step']) + 1);
                $_SESSION['enrollment_step'] = $currentStep;
                break;
                
            case 'previous':
                $currentStep = max(1, intval($_POST['current_step']) - 1);
                $_SESSION['enrollment_step'] = $currentStep;
                break;
                
            case 'submit':
                // Final submission
                foreach ($_POST as $key => $value) {
                    if ($key !== 'action') {
                        $_SESSION['enrollment_data'][$key] = sanitize_input($value);
                    }
                }
                
                // Create enrollment application
                $applicationId = $dataManager->createEnrollmentApplication($_SESSION['enrollment_data']);
                
                if ($applicationId) {
                    // Generate application number
                    $applicationNumber = 'APP-' . date('Y') . '-' . str_pad($applicationId, 6, '0', STR_PAD_LEFT);
                    
                    // Update application with application number
                    $stmt = $pdo->prepare("UPDATE enrollment_applications SET application_number = ? WHERE id = ?");
                    $stmt->execute([$applicationNumber, $applicationId]);
                    
                    // Generate student credentials
                    $credentials = $dataManager->generateStudentCredentials(
                        $_SESSION['enrollment_data']['firstName'],
                        $_SESSION['enrollment_data']['lastName'],
                        $applicationId
                    );
                    
                    // Create user account for student portal access
                    $hashedPassword = password_hash($credentials['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, password, email, first_name, last_name, role, status, created_at)
                        VALUES (?, ?, ?, ?, ?, 'student', 'pending', NOW())
                    ");
                    $stmt->execute([
                        $credentials['username'],
                        $hashedPassword,
                        $_SESSION['enrollment_data']['email'],
                        $_SESSION['enrollment_data']['firstName'],
                        $_SESSION['enrollment_data']['lastName']
                    ]);
                    
                    // Send SMS notification with credentials and application ID
                    $phoneNumber = $_SESSION['enrollment_data']['phoneNumber'];
                    $smsMessage = "Welcome to EduManage! Your enrollment application has been submitted successfully.\n\n";
                    $smsMessage .= "Application ID: {$applicationNumber}\n";
                    $smsMessage .= "Student Portal Login:\n";
                    $smsMessage .= "Username: {$credentials['username']}\n";
                    $smsMessage .= "Password: {$credentials['password']}\n\n";
                    $smsMessage .= "Please keep these credentials safe. You can access the student portal at our website.";
                    
                    $smsResult = $dataManager->sendSMS($phoneNumber, $smsMessage);
                    
                    $success = "Your application has been submitted successfully! Your Application Number is: $applicationNumber. ";
                    $success .= "Login credentials have been sent to your mobile number ({$phoneNumber}). ";
                    $success .= "Please check your SMS for your username and password to access the student portal.";
                    
                    // Clear session data
                    unset($_SESSION['enrollment_data']);
                    unset($_SESSION['enrollment_step']);
                    
                    // Log activity
                    log_activity(null, 'enrollment_submitted', "New enrollment application submitted: $applicationId, SMS sent to: $phoneNumber");
                } else {
                    $error = 'Failed to submit application. Please try again.';
                }
                break;
        }
    }
}

// Get current step from session
if (isset($_SESSION['enrollment_step'])) {
    $currentStep = $_SESSION['enrollment_step'];
}

// Get form data from session
$formData = $_SESSION['enrollment_data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Enrollment Portal - EduManage</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo i {
            width: 40px;
            height: 40px;
            background: #3b82f6;
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .logo-text h1 {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .logo-text p {
            font-size: 0.9rem;
            color: #64748b;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }

        .close-btn:hover {
            background: #f1f5f9;
            color: #3b82f6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .progress-bar {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .step-number {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .step-number.active {
            background: #3b82f6;
            color: white;
        }

        .step-number.completed {
            background: #10b981;
            color: white;
        }

        .step-number.inactive {
            background: #e2e8f0;
            color: #64748b;
        }

        .step-title {
            font-weight: 500;
            color: #374151;
        }

        .step-title.active {
            color: #3b82f6;
        }

        .step-connector {
            flex: 1;
            height: 2px;
            background: #e2e8f0;
            margin: 0 1rem;
        }

        .step-connector.completed {
            background: #10b981;
        }

        .progress-fill {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill-bar {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6 0%, #1e40af 100%);
            transition: width 0.3s ease;
        }

        .form-card {
            background: white;
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s;
            background: #f9fafb;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-control.textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #6b7280;
            color: #6b7280;
        }

        .btn-outline:hover {
            background: #6b7280;
            color: white;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
            margin-top: 2rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }

        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            margin: 2rem 0;
        }

        .info-card i {
            font-size: 3rem;
            color: #64748b;
            margin-bottom: 1rem;
        }

        .info-card h3 {
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .info-card p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .review-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 0.75rem;
        }

        .review-item {
            display: flex;
            flex-direction: column;
        }

        .review-label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .review-value {
            color: #1e293b;
            font-weight: 500;
        }

        .tracking-card {
            background: white;
            border-left: 4px solid #3b82f6;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .tracking-card h3 {
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .tracking-card p {
            color: #64748b;
            margin-bottom: 1rem;
        }

        .tracking-form {
            display: flex;
            gap: 0.75rem;
        }

        .tracking-form input {
            flex: 1;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header-content {
                padding: 0 1rem;
            }

            .steps {
                flex-direction: column;
                gap: 1rem;
            }

            .step-connector {
                display: none;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
                gap: 1rem;
            }

            .tracking-form {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <div class="logo-text">
                    <h1>Student Enrollment Portal</h1>
                    <p>Apply for admission to our school</p>
                </div>
            </div>
            <button class="close-btn" onclick="window.location.href='../auth/login.php'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </header>

    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="progress-bar">
            <div class="steps">
                <?php 
                $steps = [
                    1 => 'Personal Info',
                    2 => 'Academic Info', 
                    3 => 'Documents',
                    4 => 'Payment',
                    5 => 'Review'
                ];
                
                foreach ($steps as $stepNum => $stepTitle): 
                    $stepClass = $stepNum < $currentStep ? 'completed' : ($stepNum == $currentStep ? 'active' : 'inactive');
                ?>
                    <div class="step">
                        <div class="step-number <?= $stepClass ?>">
                            <?= $stepNum < $currentStep ? '<i class="fas fa-check"></i>' : $stepNum ?>
                        </div>
                        <span class="step-title <?= $stepNum == $currentStep ? 'active' : '' ?>">
                            <?= $stepTitle ?>
                        </span>
                    </div>
                    <?php if ($stepNum < 5): ?>
                        <div class="step-connector <?= $stepNum < $currentStep ? 'completed' : '' ?>"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="progress-fill">
                <div class="progress-fill-bar" style="width: <?= ($currentStep / 5) * 100 ?>%"></div>
            </div>
        </div>

        <div class="form-card">
            <form method="POST" id="enrollmentForm">
                <input type="hidden" name="current_step" value="<?= $currentStep ?>">
                
                <?php if ($currentStep == 1): ?>
                    <h2 class="form-title">Personal Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstName">First Name *</label>
                            <input type="text" id="firstName" name="firstName" class="form-control" 
                                   value="<?= htmlspecialchars($formData['firstName'] ?? '') ?>" 
                                   placeholder="Enter first name" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name *</label>
                            <input type="text" id="lastName" name="lastName" class="form-control" 
                                   value="<?= htmlspecialchars($formData['lastName'] ?? '') ?>" 
                                   placeholder="Enter last name" required>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="dateOfBirth">Date of Birth *</label>
                            <input type="date" id="dateOfBirth" name="dateOfBirth" class="form-control" 
                                   value="<?= htmlspecialchars($formData['dateOfBirth'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender *</label>
                            <select id="gender" name="gender" class="form-control" required>
                                <option value="">Select gender</option>
                                <option value="male" <?= ($formData['gender'] ?? '') == 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= ($formData['gender'] ?? '') == 'female' ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= ($formData['gender'] ?? '') == 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($formData['email'] ?? '') ?>" 
                               placeholder="Enter email address" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phoneNumber">Phone Number *</label>
                        <input type="tel" id="phoneNumber" name="phoneNumber" class="form-control" 
                               value="<?= htmlspecialchars($formData['phoneNumber'] ?? '') ?>" 
                               placeholder="Enter phone number" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Complete Address *</label>
                        <textarea id="address" name="address" class="form-control textarea" 
                                  placeholder="Enter complete address" required><?= htmlspecialchars($formData['address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="parentName">Parent/Guardian Name *</label>
                            <input type="text" id="parentName" name="parentName" class="form-control" 
                                   value="<?= htmlspecialchars($formData['parentName'] ?? '') ?>" 
                                   placeholder="Enter parent/guardian name" required>
                        </div>
                        <div class="form-group">
                            <label for="parentPhone">Parent/Guardian Phone *</label>
                            <input type="tel" id="parentPhone" name="parentPhone" class="form-control" 
                                   value="<?= htmlspecialchars($formData['parentPhone'] ?? '') ?>" 
                                   placeholder="Enter parent/guardian phone" required>
                        </div>
                    </div>

                <?php elseif ($currentStep == 2): ?>
                    <h2 class="form-title">Academic Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="desiredGradeLevel">Desired Grade Level *</label>
                            <select id="desiredGradeLevel" name="desiredGradeLevel" class="form-control" required>
                                <option value="">Select grade level</option>
                                <option value="grade7" <?= ($formData['desiredGradeLevel'] ?? '') == 'grade7' ? 'selected' : '' ?>>Grade 7</option>
                                <option value="grade8" <?= ($formData['desiredGradeLevel'] ?? '') == 'grade8' ? 'selected' : '' ?>>Grade 8</option>
                                <option value="grade9" <?= ($formData['desiredGradeLevel'] ?? '') == 'grade9' ? 'selected' : '' ?>>Grade 9</option>
                                <option value="grade10" <?= ($formData['desiredGradeLevel'] ?? '') == 'grade10' ? 'selected' : '' ?>>Grade 10</option>
                                <option value="grade11" <?= ($formData['desiredGradeLevel'] ?? '') == 'grade11' ? 'selected' : '' ?>>Grade 11</option>
                                <option value="grade12" <?= ($formData['desiredGradeLevel'] ?? '') == 'grade12' ? 'selected' : '' ?>>Grade 12</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="desiredStrand">Desired Strand (for SHS)</label>
                            <select id="desiredStrand" name="desiredStrand" class="form-control">
                                <option value="">Select strand (if applicable)</option>
                                <option value="stem" <?= ($formData['desiredStrand'] ?? '') == 'stem' ? 'selected' : '' ?>>STEM</option>
                                <option value="abm" <?= ($formData['desiredStrand'] ?? '') == 'abm' ? 'selected' : '' ?>>ABM</option>
                                <option value="humss" <?= ($formData['desiredStrand'] ?? '') == 'humss' ? 'selected' : '' ?>>HUMSS</option>
                                <option value="gas" <?= ($formData['desiredStrand'] ?? '') == 'gas' ? 'selected' : '' ?>>GAS</option>
                                <option value="tvl" <?= ($formData['desiredStrand'] ?? '') == 'tvl' ? 'selected' : '' ?>>TVL</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="previousSchool">Previous School</label>
                        <input type="text" id="previousSchool" name="previousSchool" class="form-control" 
                               value="<?= htmlspecialchars($formData['previousSchool'] ?? '') ?>" 
                               placeholder="Enter previous school name">
                    </div>
                    
                    <div class="form-group">
                        <label for="previousGPA">Previous GPA (if applicable)</label>
                        <input type="number" id="previousGPA" name="previousGPA" class="form-control" 
                               value="<?= htmlspecialchars($formData['previousGPA'] ?? '') ?>" 
                               placeholder="Enter previous GPA" step="0.01" min="0" max="4.0">
                    </div>

                <?php elseif ($currentStep == 3): ?>
                    <h2 class="form-title">Required Documents</h2>
                    <div class="info-card">
                        <i class="fas fa-upload"></i>
                        <h3>Document Upload</h3>
                        <p>Document upload feature coming soon</p>
                        <p style="margin-top: 1rem; font-size: 0.85rem;">Please prepare: Birth Certificate, Report Card, Valid ID</p>
                    </div>

                <?php elseif ($currentStep == 4): ?>
                    <h2 class="form-title">Payment Information</h2>
                    <div class="info-card">
                        <i class="fas fa-credit-card"></i>
                        <h3>Payment Processing</h3>
                        <p>Payment processing will be handled by the accounting department</p>
                        <p style="margin-top: 1rem; font-size: 0.85rem;">You will receive payment instructions via email</p>
                    </div>

                <?php elseif ($currentStep == 5): ?>
                    <h2 class="form-title">Review Application</h2>
                    <div class="review-grid">
                        <div class="review-item">
                            <span class="review-label">Name:</span>
                            <span class="review-value"><?= htmlspecialchars(($formData['firstName'] ?? '') . ' ' . ($formData['lastName'] ?? '')) ?></span>
                        </div>
                        <div class="review-item">
                            <span class="review-label">Email:</span>
                            <span class="review-value"><?= htmlspecialchars($formData['email'] ?? '') ?></span>
                        </div>
                        <div class="review-item">
                            <span class="review-label">Phone:</span>
                            <span class="review-value"><?= htmlspecialchars($formData['phoneNumber'] ?? '') ?></span>
                        </div>
                        <div class="review-item">
                            <span class="review-label">Grade Level:</span>
                            <span class="review-value"><?= htmlspecialchars($formData['desiredGradeLevel'] ?? '') ?></span>
                        </div>
                        <div class="review-item">
                            <span class="review-label">Strand:</span>
                            <span class="review-value"><?= htmlspecialchars($formData['desiredStrand'] ?? 'N/A') ?></span>
                        </div>
                        <div class="review-item">
                            <span class="review-label">Parent/Guardian:</span>
                            <span class="review-value"><?= htmlspecialchars($formData['parentName'] ?? '') ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <?php if ($currentStep > 1): ?>
                        <button type="submit" name="action" value="previous" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                    <?php else: ?>
                        <a href="../auth/login.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($currentStep < 5): ?>
                        <button type="submit" name="action" value="next" class="btn btn-primary">
                            Continue <i class="fas fa-arrow-right"></i>
                        </button>
                    <?php else: ?>
                        <button type="submit" name="action" value="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Submit Application
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="tracking-card">
            <h3><i class="fas fa-search"></i> Already Applied?</h3>
            <p>Track your application status using your application ID.</p>
            <div class="tracking-form">
                <input type="text" class="form-control" placeholder="Enter Application ID">
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-search"></i> Track Status
                </button>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('enrollmentForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#ef4444';
                    isValid = false;
                } else {
                    field.style.borderColor = '#e5e7eb';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>
