<?php
ob_start();
require_once '../config/dbcon.php';
include '../includes/employer_navbar.php';
require_once '../auth/employer_auth.php';
require_once '../auth/auth_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if both user_id and stud_id are not set simultaneously
if (isset($_SESSION['user_id']) && isset($_SESSION['stud_id'])) {
    echo "Error: Both user and student IDs are set. Only one should be set.";
    exit;
}

// Check if neither user_id nor stud_id is set
if (!isset($_SESSION['user_id']) && !isset($_SESSION['stud_id'])) {
    header("Location: ../index.php");
    exit;
}

// Initialize current user data (unchanged from your original code)
if (isset($_SESSION['user_id'])) {
    $currentUser = [
        'entity_type' => 'user',
        'entity_id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_first_name'] ?? 'User',
        'role' => $_SESSION['user_type'] ?? 'Unknown',
        'email' => $_SESSION['user_email'] ?? '',
        'picture' => $_SESSION['picture_file'] ?? ''
    ];

    $user_id = $currentUser['entity_id'];
    $query = "SELECT * FROM user WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id]);
    $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userDetails) {
        $currentUser['full_name'] = $userDetails['user_first_name'] . ' ' . $userDetails['user_last_name'];
        $currentUser['email'] = $userDetails['user_email'];
        $currentUser['picture'] = $userDetails['picture_file'];
        $currentUser['status'] = $userDetails['status'];
    }
} else {
    $currentUser = [
        'entity_type' => 'student',
        'entity_id' => $_SESSION['stud_id'],
        'name' => $_SESSION['stud_first_name'] ?? 'Student',
        'role' => 'Student',
        'email' => $_SESSION['stud_email'] ?? '',
        'picture' => $_SESSION['profile_picture'] ?? ''
    ];

    $stud_id = $currentUser['entity_id'];
    $query = "SELECT * FROM student WHERE stud_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$stud_id]);
    $studentDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($studentDetails) {
        $currentUser['full_name'] = $studentDetails['stud_first_name'] . ' ' . $studentDetails['stud_last_name'];
        $currentUser['email'] = $studentDetails['stud_email'];
        $currentUser['picture'] = $studentDetails['profile_picture'];
        $currentUser['status'] = $studentDetails['status'];
    }
}

// Set reference type and ID
$reference_type = $currentUser['entity_type'];
$reference_id = $currentUser['entity_id'];

// Handle actions (enhanced with bulk actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // First get the actor_id for the current user
    $stmt = $conn->prepare("SELECT actor_id FROM actor WHERE entity_type = ? AND entity_id = ?");
    $stmt->execute([$currentUser['entity_type'], $currentUser['entity_id']]);
    $actor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$actor) {
        $_SESSION['error_message'] = "Could not find your user record in the notification system";
        header("Location: employer_notifications.php");
        exit;
    }
    
    $actor_id = $actor['actor_id'];

    if (isset($_POST['mark_all_read'])) {
        try {
            $stmt = $conn->prepare("UPDATE notification SET is_read = TRUE WHERE actor_id = ? AND deleted_at IS NULL");
            $stmt->execute([$actor_id]);
            $_SESSION['success_message'] = "All notifications marked as read";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error marking notifications as read: " . $e->getMessage();
        }
        header("Location: employer_notifications.php");
        exit;
    }
    
    if (isset($_POST['delete_all'])) {
        try {
            $stmt = $conn->prepare("UPDATE notification SET deleted_at = CURRENT_TIMESTAMP WHERE actor_id = ? AND deleted_at IS NULL");
            $stmt->execute([$actor_id]);
            $_SESSION['success_message'] = "All notifications deleted";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting notifications: " . $e->getMessage();
        }
        header("Location: employer_notifications.php");
        exit;
    }
}

// Handle single notification actions (unchanged)
if (isset($_GET['notification_id'])) {
    $notification_id = $_GET['notification_id'];
    $stmt = $conn->prepare("UPDATE notification SET is_read = TRUE WHERE notification_id = ?");
    $stmt->execute([$notification_id]);
    header("Location: employer_notifications.php");
    exit;
}

if (isset($_GET['delete_notification_id'])) {
    $notification_id = $_GET['delete_notification_id'];
    $stmt = $conn->prepare("UPDATE notification SET deleted_at = CURRENT_TIMESTAMP WHERE notification_id = ?");
    $stmt->execute([$notification_id]);
    header("Location: employer_notifications.php");
    exit;
}

// Fetch notifications (unchanged query)
// For employer notifications (showing who applied)
$query = "
    SELECT n.*, 
        ref_a.entity_type AS applicant_entity_type,
        ref_a.entity_id AS applicant_entity_id,
        COALESCE(s.stud_first_name, u.user_first_name) AS applicant_first_name,
        COALESCE(s.stud_last_name, u.user_last_name) AS applicant_last_name,
        COALESCE(s.profile_picture, u.picture_file) AS applicant_picture,
        j.title AS job_title
    FROM notification n
    JOIN actor a ON n.actor_id = a.actor_id  -- Employer receiving notification
    LEFT JOIN actor ref_a ON n.reference_id = ref_a.actor_id  -- Student who applied
    LEFT JOIN student s ON ref_a.entity_type = 'student' AND ref_a.entity_id = s.stud_id
    LEFT JOIN user u ON ref_a.entity_type = 'user' AND ref_a.entity_id = u.user_id
    LEFT JOIN job_posting j ON n.action_url LIKE CONCAT('%job_id=', j.job_id, '%')
    WHERE a.entity_type = ? 
    AND a.entity_id = ?
    AND n.deleted_at IS NULL
    ORDER BY n.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute([$currentUser['entity_type'], $currentUser['entity_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process notifications for employer view
foreach ($notifications as &$notification) {
    if (!empty($notification['applicant_entity_id'])) {
        $firstName = $notification['applicant_first_name'] ?? '';
        $lastName = $notification['applicant_last_name'] ?? '';
        $applicantName = trim("$firstName $lastName");
        $jobTitle = $notification['job_title'] ?? 'the job';
        
        // Customize message for employer view
        $notification['message'] = str_replace(
            ['{actor}', 'applied for:'], 
            [$applicantName, 'applied to your job:'], 
            $notification['message']
        );
    }
}
unset($notification);

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread_count++;
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1A4D8F;
            --secondary-color: #3A7BD5;
            --primary-light: #e0e7ff;
            --accent-color: #4cc9f0;
            --success-color: #38b000;
            --warning-color: #ffaa00;
            --danger-color: #ef233c;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --card-hover-shadow: 0 8px 16px rgba(67, 97, 238, 0.15);
            --border-radius: 12px;
        }
        
        body {
            background-color: #f8f9fa;
            /* font-family: 'Poppins', sans-serif; */
        }
        
        .dashboard-container {
            min-height: 70vh;
            padding: 2rem;
            max-width: 1350px;
            margin: 0 auto;
        }
        
        /* Notification Card */
        .notification-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
            position: relative;
            margin-bottom: 1rem;
        }
        
        .notification-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-3px);
        }
        
        .card-unread {
            border-left: 4px solid var(--primary-color);
            background-color: rgba(26, 77, 143, 0.05);
        }
        
        .card-read {
            opacity: 0.9;
        }
        
        .actor-image {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .notification-time {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .notification-message {
            font-size: 0.95rem;
            color: #2b2d42;
            margin-bottom: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #adb5bd;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #e9ecef;
        }
        
        .empty-state p {
            margin-bottom: 0;
            font-weight: 500;
        }
        
        .action-buttons a {
            white-space: nowrap;
            font-size: 0.85rem;
            padding: 0.35rem 0.75rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 300;
            padding: 0.75rem 1.25rem;
            position: relative;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: transparent;
            border-bottom: 3px solid var(--primary-color);
        }
        
        .nav-tabs .nav-link .badge {
            font-size: 0.65rem;
            margin-left: 0.25rem;
        }
        
        .date-divider {
            position: relative;
            text-align: center;
            margin: 1.5rem 0;
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .date-divider::before,
        .date-divider::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background-color: #dee2e6;
        }
        
        .date-divider::before {
            left: 0;
        }
        
        .date-divider::after {
            right: 0;
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem !important;
            }
            
            .action-buttons a {
                width: 100%;
            }
            
            .nav-tabs .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }
    </style>
    <style>
        #notificationTabs .nav-link {
            font-size: 0.75rem;  /* Smaller font size */
            padding: 0.375rem 0.75rem;  /* Smaller padding for compact buttons */
            height: 36px;  /* Reduced height */
            line-height: 1.5;  /* Ensure text is vertically centered */
            display: flex;  /* Use flexbox for centering */
            align-items: center;  /* Vertically center content */
            justify-content: center;  /* Horizontally center content */
        }

        #notificationTabs .nav-link i {
            font-size: 1rem;  /* Slightly smaller icon */
            margin-right: 0.25rem;  /* Space between icon and text */
        }

        #notificationTabs .badge {
            font-size: 0.7rem; /* Smaller badge text */
            padding: 0.25rem 0.5rem;  /* Adjust badge padding */
        }

        .btn-mark-read {
            background-color: #1A4D8F;
            color: white;
            font-weight: 300;
        }

        .btn-mark-read:hover {
            background-color:rgb(120, 172, 245); 
            color: #fff;
        }

        .applicant-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="container dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <h2 class="fw-normal mb-0" style="color: #1A4D8F;">
            <i class="bi bi-bell-fill me-2"></i>Notifications
            <?php if ($unread_count > 0): ?>
                <span class="badge bg-danger ms-2 align-middle"><?= $unread_count ?> unread</span>
            <?php endif; ?>
        </h2>
        
        <div class="d-flex gap-2">
            <form method="post" class="d-inline">
                <button type="submit" name="mark_all_read" class="btn btn-sm btn-mark-read">
                    <i class="bi bi-check-all me-1"></i> Mark all as read
                </button>
            </form>
            <form method="post" class="d-inline">
                <button type="submit" name="delete_all" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i> Delete all
                </button>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4 fade-in" id="notificationTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                <i class="bi bi-list-check me-1"></i> All Notifications
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="unread-tab" data-bs-toggle="tab" data-bs-target="#unread" type="button" role="tab">
                <i class="bi bi-envelope me-1"></i> Unread 
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger"><?= $unread_count ?></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="tab-content fade-in" id="notificationTabContent">
        <div class="tab-pane fade show active" id="all" role="tabpanel">
            <?php if (empty($notifications)) : ?>
                <div class="empty-state">
                    <i class="bi bi-bell-slash mb-3"></i>
                    <h4>No notifications found</h4>
                    <p>You're all caught up!</p>
                </div>
            <?php else : ?>
                <?php 
                $current_date = null;
                foreach ($notifications as $notification) : 
                    $notification_date = date("F j, Y", strtotime($notification['created_at']));
                    if ($notification_date !== $current_date) {
                        $current_date = $notification_date;
                        echo '<div class="date-divider">' . $current_date . '</div>';
                    }
                ?>
                    <div class="card notification-card <?= $notification['is_read'] ? 'card-read' : 'card-unread' ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-start">
                               
                                <?php if ($notification['applicant_entity_id']) : ?>
                                    <?php 
                                        $imagePath = !empty($notification['applicant_picture']) ? "../uploads/" . $notification['applicant_picture'] : null;
                                        if ($imagePath && file_exists($imagePath)) : ?>
                                            <img src="<?= htmlspecialchars($imagePath) ?>"
                                                alt="Applicant"
                                                class="applicant-image me-3"
                                                style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">
                                        <?php else : ?>
                                        <div class="actor-image me-3 bg-primary-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-user text-primary"></i>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-semibold">
                                        <span class="badge bg-primary">Application</span>
                                        <span class="mx-2">|</span>
                                        <span><?= htmlspecialchars($notification['applicant_first_name'] . ' ' . $notification['applicant_last_name']) ?></span>
                                    </h6>

                                    <?php if (!empty($notification['job_title'])) : ?>
                                        <p class="text-muted mb-2">
                                            <i class="bi bi-briefcase me-1"></i>
                                            <?= htmlspecialchars($notification['job_title']) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <p class="notification-message mb-2">
                                        <?= htmlspecialchars($notification['message']) ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="notification-time">
                                            <i class="bi bi-clock me-1"></i>
                                            <?= date("g:i a", strtotime($notification['created_at'])) ?>
                                            • <?= ucfirst($notification['notification_type']) ?>
                                        </span>
                                        
                                        <div class="action-buttons d-flex gap-2">
                                            <?php if (!$notification['is_read']) : ?>
                                                <a href="employer_notifications.php?notification_id=<?= $notification['notification_id'] ?>"
                                                   class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-circle me-1"></i> Read
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="employer_notifications.php?delete_notification_id=<?= $notification['notification_id'] ?>"
                                               class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash me-1"></i> Delete
                                            </a>
                                            
                                            <?php if ($notification['action_url']) : ?>
                                                <a href="<?= htmlspecialchars($notification['action_url']) ?>"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-arrow-right-circle me-1"></i> View
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="unread" role="tabpanel">
            <?php 
            $unread_notifications = array_filter($notifications, function($n) {
                return !$n['is_read'];
            });
            
            if (empty($unread_notifications)) : ?>
                <div class="empty-state">
                    <i class="bi bi-check-circle mb-3"></i>
                    <h4>No unread notifications</h4>
                    <p>You've read all your notifications!</p>
                </div>
            <?php else : ?>
                <?php 
                $current_date = null;
                foreach ($unread_notifications as $notification) : 
                    $notification_date = date("F j, Y", strtotime($notification['created_at']));
                    if ($notification_date !== $current_date) {
                        $current_date = $notification_date;
                        echo '<div class="date-divider">' . $current_date . '</div>';
                    }
                ?>
                <div class="card notification-card card-unread">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <?php if ($notification['applicant_entity_id']) : ?>
                                <?php 
                                    $imagePath = !empty($notification['applicant_picture']) ? "../uploads/" . $notification['applicant_picture'] : null;
                                    if ($imagePath && file_exists($imagePath)) : ?>
                                        <img src="<?= htmlspecialchars($imagePath) ?>"
                                            alt="Applicant"
                                            class="applicant-image me-3"
                                            style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">
                                    <?php else : ?>
                                    <div class="applicant-image me-3 bg-primary-light d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user text-primary"></i>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-semibold">
                                    <span class="badge bg-primary">Application</span>
                                    <span class="mx-2">|</span>
                                    <span><?= htmlspecialchars($notification['applicant_first_name'] . ' ' . $notification['applicant_last_name']) ?></span>
                                </h6>

                                <p class="notification-message mb-2">
                                    <?= htmlspecialchars($notification['message']) ?>
                                </p>

                                <?php if (!empty($notification['job_title'])) : ?>
                                    <p class="text-muted mb-2">
                                        <i class="bi bi-briefcase me-1"></i>
                                        <?= htmlspecialchars($notification['job_title']) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="notification-time">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= date("g:i a", strtotime($notification['created_at'])) ?>
                                    </span>
                                    
                                    <div class="action-buttons d-flex gap-2">
                                        <a href="employer_notifications.php?notification_id=<?= $notification['notification_id'] ?>"
                                        class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle me-1"></i> Read
                                        </a>
                                        
                                        <a href="employer_notifications.php?delete_notification_id=<?= $notification['notification_id'] ?>"
                                        class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash me-1"></i> Delete
                                        </a>
                                        
                                        <?php if ($notification['action_url']) : ?>
                                            <a href="<?= htmlspecialchars($notification['action_url']) ?>"
                                            class="btn btn-sm btn-primary">
                                                <i class="bi bi-arrow-right-circle me-1"></i> View
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/stud_footer.php'; ?>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>

// Real-time notification updates
function checkNewNotifications() {
    $.ajax({
        url: '../controllers/notifications_controller.php',
        type: 'GET',
        data: {
            action: 'get_notifications',
            reference_type: '<?= $reference_type ?>',
            reference_id: '<?= $reference_id ?>',
            last_check: new Date().toISOString()
        },
        success: function(response) {
            if (response.new_count > 0) {
                // Update badge
                $('.nav-link#unread-tab .badge').text(response.new_count);
                
                // Show toast notification
                if (response.latest_notification) {
                    showToastNotification(response.latest_notification);
                }
            }
        }
    });
}

function showToastNotification(notification) {
    // Create toast element
    const toast = $(`
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-primary text-white">
                    <strong class="me-auto"><i class="bi bi-bell-fill me-2"></i>New Notification</strong>
                    <small class="text-white">Just now</small>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body bg-white">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <i class="bi bi-info-circle-fill text-primary fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <p class="mb-1">${notification.message}</p>
                            <a href="${notification.action_url || '#'}" class="btn btn-sm btn-primary mt-2">
                                <i class="bi bi-arrow-right-circle me-1"></i> View
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `);
    
    // Add to body and auto-remove after 5 seconds
    $('body').append(toast);
    setTimeout(() => toast.remove(), 5000);
}

// Check every 30 seconds
setInterval(checkNewNotifications, 30000);

// Initial check when page loads
$(document).ready(function() {
    checkNewNotifications();
    
    // Add hover effects dynamically
    $('.notification-card').hover(
        function() {
            $(this).css('transform', 'translateY(-3px)');
        },
        function() {
            $(this).css('transform', 'translateY(0)');
        }
    );
});
</script>

</body>
</html>