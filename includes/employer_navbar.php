<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in as employer
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Include your external PDO database connection
require '../config/dbcon.php';

// Function to fetch employer details from the database
function getEmployerDetails($conn, $userId) {
    $stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id AND deleted_at IS NULL");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get unread notification count using PDO
function getUnreadNotificationCount($conn, $userId) {
    $stmt = $conn->prepare("SELECT actor_id FROM actor WHERE entity_type = 'user' AND entity_id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 1) {
        $actor = $stmt->fetch(PDO::FETCH_ASSOC);
        $actorId = $actor['actor_id'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notification 
                              WHERE actor_id = :actor_id AND is_read = 0 AND deleted_at IS NULL");
        $stmt->bindParam(':actor_id', $actorId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    return 0;
}

function getUnreadMessageCount($conn, $userId) {
    // Get the actor_id of the user
    $stmt = $conn->prepare("SELECT actor_id FROM actor WHERE entity_type = 'user' AND entity_id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 1) {
        $actor = $stmt->fetch(PDO::FETCH_ASSOC);
        $actorId = $actor['actor_id'];
        
        // Count unread messages for this user (as receiver)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM message 
                                WHERE receiver_id = :actor_id AND is_read = 0 AND deleted_at IS NULL");
        $stmt->bindParam(':actor_id', $actorId, PDO::PARAM_INT);
        $stmt->execute();
       return (int) $stmt->fetchColumn();
    }
    return 0;
}

// Get employer user ID from session
$userId = $_SESSION['user_id'];

// Fetch employer data
$employerData = getEmployerDetails($conn, $userId);

// Set session variables if data is found
if ($employerData) {
    $_SESSION['user_first_name'] = $employerData['user_first_name'];
    $_SESSION['user_last_name'] = $employerData['user_last_name'];
    $_SESSION['picture_file'] = $employerData['picture_file'];
}

// Profile picture handling
$profilePicture = $employerData['picture_file'] ?? '';
if (!empty($profilePicture) && file_exists('../uploads/' . $profilePicture)) {
    $profile_pic = '../uploads/' . $profilePicture;
} else {
    $name = trim(($employerData['user_first_name'] ?? '') . ' ' . ($employerData['user_last_name'] ?? ''));
    $profile_pic = 'https://ui-avatars.com/api/?name=' . urlencode($name ?: 'Employer') . '&background=457B9D&color=fff&rounded=true&size=128';
}

// Get unread notification count
$notification_count = getUnreadNotificationCount($conn, $userId);
$message_count = getUnreadMessageCount($conn, $userId);

// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);

// Navigation links for employer
$nav_links = [
    "Dashboard" => "../dashboard/employer.php",
    "Jobs" => "../dashboard/employer_jobs.php",
    "Applications" => "employer_applications.php",
    "Notifications" => "../dashboard/employer_notifications.php",
    "Forum" => "forums.php",
    "Messages" => "messages.php"
];

// Get employer name safely
$firstName = htmlspecialchars($employerData['user_first_name'] ?? 'Employer');
$lastName = htmlspecialchars($employerData['user_last_name'] ?? '');

// Job-related details
$companyName = $employerData['company_name'] ?? '';
$jobTitle = $employerData['job_title'] ?? '';

// Resume file handling (if any)
$resumeFile = $employerData['resume_file'] ?? '';

// Define the base directory for uploads
$uploadsDir = '../uploads/';

// Check if the resume file exists
if (!empty($resumeFile) && file_exists($uploadsDir . $resumeFile)) {
    $resumeLink = $uploadsDir . $resumeFile;
} else {
    $resumeLink = '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1A4D8F;
            --secondary-color: #3A7BD5;
            --accent-color: #4ECDC4;
            --text-dark: #2D3748;
            --text-light: #F8F9FA;
            --light-bg: #F7FAFC;
            --danger-color: #E53E3E;
            --success-color: #38A169;
            --warning-color: #DD6B20;
            --hover-color: rgba(58, 123, 213, 0.1);
            --transition-speed: 0.3s;
            --nav-height: 70px;
            --border-radius: 8px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        body {
            font-family: 'Poppins', sans-serif !important;
            background-color: var(--light-bg);
        }
        
        .navbar {
            background-color: white !important;
            box-shadow: var(--box-shadow);
            padding: 0.5rem 2rem;
            min-height: var(--nav-height);
            transition: all var(--transition-speed) ease;
            z-index: 1030;
        }

        .navbar.scrolled {
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.12);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            transition: all var(--transition-speed) ease;
            letter-spacing: -0.5px;
        }

        .navbar-brand i {
            margin-right: 10px;
            color: var(--accent-color);
            font-size: 1.5em;
        }

        .navbar-brand:hover {
            color: var(--secondary-color) !important;
        }

        .nav-link {
            color: var(--text-dark) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            padding: 0.5rem 1rem !important;
            border-radius: var(--border-radius);
            transition: all var(--transition-speed) ease;
            position: relative;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .nav-link i {
            margin-right: 8px;
            font-size: 1.1em;
        }

        .nav-link:not(.active):hover {
            background-color: var(--hover-color);
            color: var(--secondary-color) !important;
        }

        .nav-link.active {
            font-weight: 600;
            color: white !important;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 4px 12px rgba(26, 77, 143, 0.25);
        }

        .nav-link.active i {
            color: white !important;
        }

        .nav-link.active:after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-top: 8px solid var(--primary-color);
        }

        /* Custom dropdown styles */
        .custom-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .custom-dropdown-toggle {
            cursor: pointer;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: inherit;
        }
        
        .custom-dropdown-menu {
            position: absolute;
            right: 0;
            top: 100%;
            z-index: 1000;
            display: none;
            min-width: 220px;
            padding: 0.5rem;
            margin: 0.125rem 0 0;
            font-size: 0.9rem;
            color: #212529;
            text-align: left;
            list-style: none;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0, 0, 0, 0.15);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.2s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .custom-dropdown-menu.show {
            display: block;
        }
        
        .custom-dropdown-item {
            display: block;
            width: 100%;
            padding: 0.65rem 1rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            border-radius: 6px;
            transition: all var(--transition-speed) ease;
            display: flex;
            align-items: center;
        }
        
        .custom-dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 12px;
            color: var(--secondary-color);
        }
        
        .custom-dropdown-item:hover {
            background-color: var(--hover-color);
            color: var(--secondary-color);
            transform: translateX(3px);
        }
        
        .custom-dropdown-divider {
            height: 0;
            margin: 0.5rem 0;
            overflow: hidden;
            border-top: 1px solid rgba(0, 0, 0, 0.15);
        }
        
        .custom-dropdown-header {
            display: block;
            padding: 0.5rem 1rem;
            margin-bottom: 0;
            font-size: 0.85rem;
            color: var(--primary-color);
            white-space: nowrap;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Notification badge */
        .notification-badge {
            font-size: 0.7rem;
            padding: 0.25em 0.6em;
            margin-left: 6px;
            vertical-align: middle;
            font-weight: 600;
        }

        /* Profile picture */
        .profile-container {
            position: relative;
            margin-right: 10px;
        }

        .profile-pic {
            width: 38px;
            height: 38px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .profile-pic:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .online-status {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 10px;
            height: 10px;
            background-color: var(--success-color);
            border-radius: 50%;
            border: 2px solid white;
        }

        .user-name {
            font-weight: 500;
            color: var(--text-dark);
            margin-left: 8px;
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .navbar {
                padding: 0.5rem 1rem;
            }
            
            .navbar-collapse {
                padding: 1rem 0;
                background-color: white;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                margin-top: 1rem;
                z-index: 1040;
            }
            
            .nav-link {
                margin: 0.3rem 0;
                padding: 0.8rem 1.5rem !important;
            }
            
            .nav-link.active:after {
                display: none;
            }
            
            .custom-dropdown-menu {
                position: static;
                float: none;
                width: auto;
                margin-top: 0;
                background-color: var(--light-bg);
                border: none;
                box-shadow: none;
                animation: none;
            }
        }

        /* Animation for notification bell */
        @keyframes ring {
            0% { transform: rotate(0deg); }
            25% { transform: rotate(15deg); }
            50% { transform: rotate(-15deg); }
            75% { transform: rotate(10deg); }
            100% { transform: rotate(0deg); }
        }

        .has-notifications {
            position: relative;
        }

        .has-notifications i {
            animation: ring 0.5s ease-in-out;
        }


            .custom-dropdown-toggle::after {
                content: '';
                display: inline-block;
                margin-left: 8px;
                vertical-align: middle;
                width: 0;
                height: 0;
                border-left: 5px solid transparent;
                border-right: 5px solid transparent;
                border-top: 5px solid var(--text-dark);
                transition: all var(--transition-speed) ease;
            }
            
            .custom-dropdown-toggle:hover::after {
                border-top-color: var(--secondary-color);
            }
            
            .custom-dropdown-menu.show + .custom-dropdown-toggle::after {
                transform: rotate(180deg);
            }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../dashboard/employer.php">
            CareerQuest
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <?php foreach ($nav_links as $name => $url): ?>
                    <li class="nav-item mx-1">
                        <a class="nav-link position-relative px-3 py-2 <?= ($currentPage == basename($url)) ? 'active' : '' ?> <?= ($name === 'Notifications' && $notification_count > 0) ? 'has-notifications' : '' ?>" href="<?= $url; ?>">
                            <span><?= $name; ?></span>
                            <?php if ($name === "Notifications" && $notification_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $notification_count ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($name === "Messages" && $message_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $message_count ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Custom dropdown implementation -->
            <div class="d-flex align-items-center">
                <div class="custom-dropdown">
                    <a class="custom-dropdown-toggle nav-link d-flex align-items-center" id="userDropdown" href="#">
                        <div class="position-relative me-2">
                            <img src="<?= $profile_pic ?>" class="rounded-circle" width="36" height="36" alt="Profile">
                            <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-light rounded-circle"></span>
                        </div>
                        <span class="d-none d-lg-inline"><?= $_SESSION['user_first_name'] ?? 'Employer' ?></span>
                        <!-- The ::after pseudo-element will automatically add the caret here -->
                    </a>
                    <ul class="custom-dropdown-menu" id="userDropdownMenu">
                        <li><h6 class="custom-dropdown-header"><?= htmlspecialchars($firstName . ' ' . $lastName) ?></h6></li>
                        <li><hr class="custom-dropdown-divider"></li>
                        <li><a class="custom-dropdown-item" href="../dashboard/employer_profile.php"><i class="bi bi-person"></i> My Profile</a></li>
                        <?php if ($resumeLink): ?>
                            <li><a class="custom-dropdown-item" href="<?= $resumeLink ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> My Resume</a></li>
                        <?php else: ?>
                            <li><a class="custom-dropdown-item" href="javascript:void(0);"><i class="bi bi-file-earmark-text"></i> No Resume Uploaded</a></li>
                        <?php endif; ?>
                        <li><a class="custom-dropdown-item" href="../dashboard/employer_account_settings.php"><i class="bi bi-gear"></i> Account Settings</a></li>
                        <li><hr class="custom-dropdown-divider"></li>
                        <li><a class="custom-dropdown-item text-danger" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Custom dropdown functionality
        const dropdownToggle = document.querySelector('.custom-dropdown-toggle');
        const dropdownMenu = document.querySelector('.custom-dropdown-menu');
        
        if (dropdownToggle && dropdownMenu) {
            dropdownToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Close all other open dropdowns first
                document.querySelectorAll('.custom-dropdown-menu.show').forEach(menu => {
                    if (menu !== dropdownMenu) {
                        menu.classList.remove('show');
                    }
                });
                
                // Toggle current dropdown
                dropdownMenu.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                }
            });
        }
        
        // Close dropdown when clicking on a dropdown item
        document.querySelectorAll('.custom-dropdown-item').forEach(item => {
            item.addEventListener('click', function() {
                dropdownMenu.classList.remove('show');
            });
        });

        // Scroll effect for navbar
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            navbar.classList.toggle('scrolled', window.scrollY > 10);
        });

        // Animation for notification bell if there are unread notifications
        <?php if ($notification_count > 0): ?>
        const notificationBell = document.querySelector('.has-notifications i');
        if (notificationBell) {
            setInterval(() => {
                notificationBell.style.animation = 'none';
                setTimeout(() => {
                    notificationBell.style.animation = 'ring 0.5s ease-in-out';
                }, 50);
            }, 8000);
        }
        <?php endif; ?>
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Custom logout confirmation
document.addEventListener('DOMContentLoaded', function() {
    // Find all logout links/buttons
    const logoutLinks = document.querySelectorAll('[href="../auth/logout.php"], .logout-btn');
    
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Logout Confirmation',
                text: 'Are you sure you want to log out?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#1A4D8F', 
                cancelButtonColor: '#6c757d', 
                confirmButtonText: 'Yes, Logout',
                cancelButtonText: 'Cancel',
                background: 'white',
                customClass: {
                    title: 'text-dark',
                    confirmButton: 'btn btn-primary px-4 py-2 me-3', 
                    cancelButton: 'btn btn-outline-secondary px-4 py-2', 
                    actions: 'gap-3'
                },
                buttonsStyling: false,
                reverseButtons: true,
                showClass: {
                    popup: 'animate__animated animate__fadeInDown animate__faster'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp animate__faster'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "../auth/logout.php";
                }
            });
        });
    });
});
</script>
</body>
</html>