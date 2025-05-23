<?php
include "../includes/sidebar.php";
require "../controllers/chart_query.php";
require "../controllers/admin_dashboard.php";
require '../auth/auth_check.php'; 
require '../config/dbcon.php';

$sql = "SELECT 
            (SELECT COUNT(*) FROM job_posting) AS total_jobs,
            (SELECT COUNT(*) FROM employer) AS total_employers,
            (SELECT COUNT(*) FROM job_posting WHERE flagged = TRUE) AS total_flagged_jobs,
            (SELECT COUNT(*) FROM skill_matching) AS total_skill_matches,
            (SELECT COUNT(*) FROM job_posting WHERE expires_at IS NOT NULL AND expires_at < NOW() + INTERVAL 7 DAY) AS total_expiring_jobs";

$stmt = $conn->query($sql);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$totalJobs = $stats['total_jobs'];
$totalEmployers = $stats['total_employers'];
$totalFlaggedJobs = $stats['total_flagged_jobs'];
$totalSkillMatches = $stats['total_skill_matches'];
$totalExpiringJobs = $stats['total_expiring_jobs'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Management</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap JS (Bundle includes Popper.js) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: #0A2647; /* Navy Blue */
            --secondary-color: #2C7865; /* Teal */
            --accent-color: #FFD700; /* Gold */
            --background-light: #F5F5F5; /* Light Gray */
            --text-dark: #333333; /* Dark Gray */
            --shadow-color: rgba(0, 0, 0, 0.1);
            --font-family: 'Poppins', sans-serif;


            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --sidebar-bg: #2c3e50;
            --sidebar-active-bg: #34495e;
            --sidebar-text: #ecf0f1;
            --sidebar-active-text: #3498db;
            --sidebar-hover-bg: #34495e;
            --sidebar-transition: all 0.3s ease;
            --main-content-padding: 20px;
        }

        body {
            background-color: var(--background-light);
            color: var(--text-dark);
            /* font-family: var(--font-family);*/
            transition: background-color 0.3s ease, color 0.3s ease;

            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            overflow-x: hidden;
            transition: var(--sidebar-transition);
        }

        .main-content {
            margin-left: 350px;
            margin-right: 150px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .sidebar.collapsed + .main-content {
            margin-left: 200px;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            transition: var(--sidebar-transition);
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-toggle {
            padding: 15px;
            text-align: right;
            cursor: pointer;
            color: var(--sidebar-text);
            transition: var(--sidebar-transition);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-toggle:hover {
            color: var(--sidebar-active-text);
        }

        .sidebar.collapsed .sidebar-toggle {
            text-align: center;
            padding: 15px 0;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 10px 0;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
        }

        .nav-item {
            position: relative;
            margin: 5px 10px;
            border-radius: 5px;
            overflow: hidden;
            transition: var(--sidebar-transition);
        }

        .nav-links {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: var(--sidebar-transition);
            white-space: nowrap;
        }

        .nav-links:hover {
            background: var(--sidebar-hover-bg);
            color: var(--sidebar-active-text);
        }

        .nav-links.active {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
            font-weight: 500;
        }

        .nav-links i {
            margin-right: 10px;
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
            transition: var(--sidebar-transition);
        }

        .sidebar.collapsed .nav-links i {
            margin-right: 0;
            font-size: 1.3rem;
        }

        .nav-links span {
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .sidebar.collapsed .nav-links span {
            opacity: 0;
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--sidebar-bg);
            padding: 5px 15px;
            border-radius: 4px;
            white-space: nowrap;
            pointer-events: none;
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 1001;
        }

        .sidebar.collapsed .nav-item:hover span {
            opacity: 1;
            transform: translate(10px, -50%);
        }

        /* Dark Mode Toggle */
        .dark-mode-toggle {
            padding: 15px;
            display: flex;
            justify-content: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .toggle-btn {
            background: none;
            border: none;
            color: var(--sidebar-text);
            cursor: pointer;
            font-size: 1.2rem;
            transition: var(--sidebar-transition);
        }

        .toggle-btn:hover {
            color: var(--sidebar-active-text);
        }

        /* Dark Mode Styles */
        body.dark-mode {
            background-color: #121212;
            color: #ffffff;
        }

        body.dark-mode .sidebar {
            background-color: #1a1a1a;
        }

        body.dark-mode .nav-links.active {
            background-color: #2a2a2a;
        }

        body.dark-mode .nav-links:hover {
            background-color: #2a2a2a;
        }

        /* Logout button styling */
        .logout-link {
            color: #e74c3c;
            transition: color 0.2s;
        }

        .logout-link:hover {
            color: #c0392b;
            text-decoration: none;
        }

        body.dark-mode .logout-link {
            color: #ff6b6b;
        }

        body.dark-mode .logout-link:hover {
            color: #ff5252;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width);
            }

            .sidebar.collapsed {
                transform: translateX(0);
                width: var(--sidebar-collapsed-width);
            }

            .main-content {
                margin-left: 0;
            }
        }
        .card {
            border: none;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            background: linear-gradient(145deg, var(--background-light), #ffffff);
            box-shadow: 0 4px 6px var(--shadow-color);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px var(--shadow-color);
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px;
            border-bottom: none;
        }

        .card-title {
            margin-bottom: 0;
            font-size: 1.25rem;
        }

        .card-body {
            padding: 20px;
        }

         /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--background-light);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }

        .dark-mode ::-webkit-scrollbar-track {
            background: #2d2d2d;
        }

        .dark-mode ::-webkit-scrollbar-thumb {
            background: var(--secondary-color);
        }
          /* Loading Spinner */
          .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
        }

        .loading-spinner.active {
            display: block;
        }

        /* SweetAlert2 Customization */
        .swal2-popup {
            font-family: var(--font-family);
            border-radius: 10px;
        }

        .swal2-confirm {
            background-color: var(--primary-color) !important;
        }

        .swal2-cancel {
            background-color: var(--secondary-color) !important;
        }

        /* Enhanced Table Styling */
        .table {
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .table thead th {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .table tbody tr {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .table tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .table tbody td {
            vertical-align: middle;
        }
    </style>
    <style>
    .stat-card {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 25px;
        text-align: center;
        transition: all 0.5s ease;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0));
        clip-path: circle(10% at 90% 10%);
        transition: all 0.5s ease;
    }

    .stat-card:hover::before {
        clip-path: circle(100%);
    }

    .stat-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
    }

    .stat-icon i {
        font-size: 2.5rem;
        color: #fff;
        transition: all 0.5s ease;
        position: relative;
        z-index: 2;
    }

    .stat-card:hover .stat-icon i {
        transform: scale(1.2) translateY(-5px);
    }

    .stat-title {
        font-size: 1.2rem;
        color: #fff;
        margin-top: 15px;
        transition: all 0.5s ease;
        position: relative;
        z-index: 2;
    }

    .stat-card:hover .stat-title {
        transform: translateY(-5px);
    }

    .stat-value {
        font-size: 2rem;
        color: #fff;
        font-weight: bold;
        margin-top: 10px;
        transition: all 0.5s ease;
        position: relative;
        z-index: 2;
    }

    .stat-card:hover .stat-value {
        transform: translateY(-5px);
    }

    .particles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.3), transparent);
        opacity: 0;
        transition: opacity 0.5s ease;
        pointer-events: none;
    }

    .stat-card:hover .particles {
        opacity: 1;
    }

    .blue {
        background: linear-gradient(135deg, rgba(52, 152, 219, 0.8), rgba(41, 128, 185, 0.8)); /* Muted blue */
    }

    .green {
        background: linear-gradient(135deg, rgba(39, 174, 96, 0.8), rgba(29, 131, 72, 0.8)); /* Rich green */
    }

    .red {
        background: linear-gradient(135deg, rgba(192, 57, 43, 0.8), rgba(150, 40, 27, 0.8)); /* Deep red */
    }

    .purple {
        background: linear-gradient(135deg, rgba(108, 92, 231, 0.8), rgba(72, 52, 212, 0.8)); /* Royal purple */
    }

    .orange {
        background: linear-gradient(135deg, rgba(230, 126, 34, 0.8), rgba(211, 84, 0, 0.8)); /* Warm orange */
    }

    .teal {
        background: linear-gradient(135deg, rgba(26, 188, 156, 0.8), rgba(22, 160, 133, 0.8)); /* Modern teal */
    }

    .gray {
        background: linear-gradient(135deg, rgba(149, 165, 166, 0.8), rgba(127, 140, 141, 0.8)); /* Professional gray */
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .fade-in .stat-card {
        animation: fadeIn 0.5s ease forwards;
        opacity: 0;
    }

    .fade-in .stat-card:nth-child(1) { animation-delay: 0.1s; }
    .fade-in .stat-card:nth-child(2) { animation-delay: 0.2s; }
    .fade-in .stat-card:nth-child(3) { animation-delay: 0.3s; }
    .fade-in .stat-card:nth-child(4) { animation-delay: 0.4s; }
</style>

</head>
<body class="fade-in">
    <!-- Sidebar -->
    <nav class="sidebar collapsed" id="sidebar">
        <div class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-chevron-right"></i>
        </div>
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <?php foreach ($sidebar_menu as $item): ?>
                    <?php 
                        $is_active = ($current_page == basename($item[2])) ? 'active' : '';
                        $icon = $item[1];
                        $title = $item[0];
                        $link = $item[2];
                        $logout_attr = ($title === "Logout") ? 'onclick="confirmLogout(event)"' : '';
                    ?>
                    <li class="nav-item">
                        <a class="nav-links <?= $is_active ?>" href="<?= $link ?>" <?= $logout_attr ?>>
                            <i class="<?= $icon ?>"></i>
                            <span><?= $title ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="dark-mode-toggle">
            <button class="toggle-btn" id="darkModeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </div>
    </nav>
    

   <!-- Main Content -->
   <main class="main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom ">
                <h1 class="h2">Job Management Panel</h1>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addJobModal">
                        <i class="fas fa-plus me-2"></i> Add Job
                    </button> 
                    <a href="#" class="btn btn-outline-primary d-flex align-items-center" onclick="confirmExport(event)">
                        <i class="fas fa-file-export me-2"></i> Export Job 
                    </a>
                </div>
        </div>


        <div class="row mb-4 fade-in d-flex justify-content-between">
            <?php
                $adminStats = [
                    ["Job Listings", $totalJobs, "fas fa-briefcase", "blue"],
                    ["Employers", $totalEmployers, "fas fa-building", "green"],
                    ["Flagged Jobs", $totalFlaggedJobs, "fas fa-flag", "red"],
                    ["Skill Matches", $totalSkillMatches, "fas fa-chart-line", "purple"],
                    ["Expiring Jobs", $totalExpiringJobs, "fas fa-clock", "orange"]
                ];
                foreach ($adminStats as $index => $stat): 
            ?>
            <div class="col-lg flex-grow-1 mx-2 mb-4">
                <div class="stat-card <?php echo $stat[3]; ?>" data-index="<?php echo $index; ?>">
                    <div class="stat-icon"><i class="<?php echo $stat[2]; ?>"></i></div>
                    <div class="stat-title"><?php echo $stat[0]; ?></div>
                    <div class="stat-value"><?php echo $stat[1]; ?></div>
                    <div class="particles"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>


        <div>
            <ul class="nav nav-tabs" id="adminTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="jobs-tab" data-bs-toggle="tab" data-bs-target="#jobs" type="button" role="tab">Job Listings</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="employers-tab" data-bs-toggle="tab" data-bs-target="#employers" type="button" role="tab">Employers</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="moderation-tab" data-bs-toggle="tab" data-bs-target="#moderation" type="button" role="tab">Moderation</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="matching-tab" data-bs-toggle="tab" data-bs-target="#matching" type="button" role="tab">Skill Matching</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="expiry-tab" data-bs-toggle="tab" data-bs-target="#expiry" type="button" role="tab">Job Expiry</button>
                </li>
            </ul>
            
            <div class="tab-content mt-3" id="adminTabsContent">
                <!-- Job Listings Management -->
                <div class="tab-pane fade show active" id="jobs" role="tabpanel">
                    <!-- Moderation Status Filter -->
                    <div>
                        <div class="btn-group" role="group" style="margin-bottom:15px;">
                            <button class="btn btn-md btn-outline-primary filter-btn active" onclick="filterJobs('all', this)">All</button>
                            <button class="btn btn-md btn-outline-primary filter-btn" onclick="filterJobs('pending', this)">Pending</button>
                            <button class="btn btn-md btn-outline-primary filter-btn" onclick="filterJobs('approved', this)">Approved</button>
                            <button class="btn btn-md btn-outline-primary filter-btn" onclick="filterJobs('flagged', this)">Flagged</button>
                            <button class="btn btn-md btn-outline-primary filter-btn" onclick="filterJobs('rejected', this)">Rejected</button>
                        </div>
                    </div>

                    <!-- Job Listings -->
                    <div class="row row-cols-1 row-cols-md-3 g-4" id="jobsContainer"></div>
                </div>


                <!-- Employer Management -->
                <div class="tab-pane fade" id="employers" role="tabpanel">
                <div>
                    <div class="btn-group" role="group" style="margin-bottom:15px;">
                        <button class="btn btn-md btn-outline-primary filter-btn active" onclick="filterEmployers('all', this)">All</button>
                        <button class="btn btn-md btn-outline-primary filter-btn" onclick="filterEmployers('Active', this)">Active</button>
                        <button class="btn btn-md btn-outline-primary filter-btn" onclick="filterEmployers('Suspended', this)">Suspended</button>
                        <button class="btn btn-md btn-outline-primary filter-btn" onclick="filterEmployers('Banned', this)">Banned</button>
                    </div>
                </div>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Employer Name</th>
                                <th>Company Name</th> <!-- Added this column -->
                                <th>Job Title</th> <!-- Added this column -->
                                <th>Jobs Posted</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="employerTableBody">
                            <tr id="loadingRow">
                                <td colspan="6" class="text-center">Loading employers...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                
                <!-- Moderation & Flagging -->
                <div class="tab-pane fade" id="moderation" role="tabpanel">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center animate__animated animate__fadeIn">
                            Flagged Job: Data Analyst <span class="badge bg-danger">Flagged</span>
                            <button class="btn btn-primary">Review</button>
                        </li>
                    </ul>
                </div>
                
                <!-- Skill & Matching Analysis -->
                <div class="tab-pane fade" id="matching" role="tabpanel">
                    <div class="alert alert-info animate__animated animate__fadeIn">Skill match for 'Software Engineer': 85%</div>
                </div>
                
                <!-- Job Expiry & Renewal -->
                <div class="tab-pane fade" id="expiry" role="tabpanel">
                    <ul class="list-group" id="expiring-jobs-list">
                        <!-- Jobs will be appended here dynamically -->
                    </ul>
                </div>
            </div>
        </div>
    </main>

<!-- Add Job Modal -->
<div class="modal fade" id="addJobModal" tabindex="-1" aria-labelledby="addJobModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addJobModalLabel"><i class="fas fa-briefcase me-2"></i>Add New Job</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addJobForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row g-3">
                        
                        <!-- Employer -->
                        <div class="col-md-6">
                            <label for="employer_id" class="form-label"><i class="fas fa-building me-1"></i>Employer</label>
                            <select id="employer_id" name="employer_id" class="form-select" required>
                                <option value="">Select Employer</option>
                            </select>
                        </div>

                        <!-- Job Type -->
                        <div class="col-md-6">
                            <label for="job_type" class="form-label"><i class="fas fa-tasks me-1"></i>Job Type</label>
                            <select id="job_type" name="job_type_id" class="form-select" required>
                                <option value="">Select Job Type</option>
                            </select>
                        </div>

                        <!-- Location -->
                        <div class="col-md-6">
                            <label for="location" class="form-label"><i class="fas fa-map-marker-alt me-1"></i>Location</label>
                            <input type="text" id="location" name="location" class="form-control" required>
                        </div>

                        <!-- Salary -->
                        <div class="col-md-6">
                            <label for="salary" class="form-label"><i class="fas fa-dollar-sign me-1"></i>Salary ($)</label>
                            <input type="number" id="salary" name="salary" class="form-control" step="0.01" min="0" required>
                        </div>

                        <!-- Job Title -->
                        <div class="col-md-6">
                            <label for="job_title" class="form-label"><i class="fas fa-heading me-1"></i>Job Title</label>
                            <input type="text" id="job_title" name="job_title" class="form-control" required>
                        </div>

                        <!-- Job Description -->
                        <div class="col-12">
                            <label for="description" class="form-label"><i class="fas fa-align-left me-1"></i>Job Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4" required></textarea>
                        </div>

                        <!-- Job Image -->
                        <div class="col-md-6">
                            <label for="img_url" class="form-label"><i class="fas fa-image me-1"></i>Job Image</label>
                            <input type="file" id="img_url" name="img_url" class="form-control" accept="image/*">
                        </div>

                        <!-- Expiration Date -->
                        <div class="col-md-6">
                            <label for="expires_at" class="form-label"><i class="fas fa-calendar-alt me-1"></i>Expiration Date</label>
                            <input type="date" id="expires_at" name="expires_at" class="form-control" min="<?= date('Y-m-d'); ?>">
                        </div>

                        <!-- Skill Selection Table -->
                        <div class="col-12">
                            <label for="skills" class="form-label"><i class="fas fa-tools me-1"></i>Required Skills</label>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Skill</th>
                                        <th>Importance</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="skills-table-body">
                                    <!-- Skill rows will be dynamically added here -->
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add-skill">
                                <i class="fas fa-plus me-1"></i>Add Skill
                            </button>
                        </div>
                    </div>

                    <!-- Hidden Input -->
                    <input type="hidden" name="moderation_status" value="Pending">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Job</button>
                </div>
            </form>
        </div>
    </div>
</div>





    <!-- Job Details Modal -->
    <div class="modal fade" id="jobDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Job Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Description:</strong> <span id="modalDescription"></span></p>
                    <p><strong>Location:</strong> <span id="modalLocation"></span></p>
                    <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                    <p><strong>Posted Date:</strong> <span id="modalDate"></span></p>
                    <p><strong>Required Skills:</strong></p>
                    <ul id="modalSkills"></ul> <!-- ✅ This will now show job skills -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Loading Spinner -->
    <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>  
            <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <!-- SweetAlert -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- DataTables JS -->
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
        <!-- Custom Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="../assests/logout.js"></script>
        <script src="../assests/sidebar_toggle.js" defer></script>
        <script src="../assests/addjob.js" defer></script>
        <script src="../assests/jobmanagement.js" defer></script>
        <script src="../assests/employermanagement.js" defer></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                fetchExpiringJobs();

                function fetchExpiringJobs() {
                    fetch('../controllers/fetch_expiredJobs.php')
                        .then(response => response.json())
                        .then(data => { 
                            const list = document.getElementById('expiring-jobs-list');
                            list.innerHTML = '';

                            if (data.jobs && data.jobs.length > 0) {
                                data.jobs.forEach(job => {
                                    const li = document.createElement('li');
                                    li.className = 'list-group-item d-flex justify-content-between align-items-center animate__animated animate__fadeIn';
                                    li.innerHTML = `
                                        Job: ${job.title} - Expires soon
                                        <input type="date" class="form-control" id="expiry-date-${job.job_id}" value="${getFormattedDate(job.expires_at)}">
                                        <button class="btn btn-success btn-extend" data-id="${job.job_id}">Extend</button>
                                    `;
                                    list.appendChild(li);
                                });

                                // Add click event for each extend button
                                document.querySelectorAll('.btn-extend').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const jobId = this.getAttribute('data-id');
                                        const newExpiryDate = document.getElementById(`expiry-date-${jobId}`).value;
                                        if (newExpiryDate) {
                                            // Append default time '00:00:00' to the selected date
                                            const formattedExpiryDate = newExpiryDate + " 00:00:00";
                                            extendJob(jobId, formattedExpiryDate, this);
                                        } else {
                                            alert('Please select a new expiry date.');
                                        }
                                    });
                                });
                            } else {
                                list.innerHTML = '<li class="list-group-item">No expiring jobs found.</li>';
                            }
                        });
                }

                function getFormattedDate(dateString) {
                    const date = new Date(dateString);
                    return date.toISOString().split('T')[0]; // Returns date in YYYY-MM-DD format
                }

                function extendJob(jobId, newExpiryDate, button) {
                    const formData = new FormData();
                    formData.append('action', 'extend_job');
                    formData.append('job_id', jobId);
                    formData.append('new_expiry', newExpiryDate);

                    fetch('../controllers/job_extend.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(response => {
                        if (response.success) {
                            button.classList.remove('btn-success');
                            button.classList.add('btn-secondary');
                            button.disabled = true;
                            button.textContent = 'Extended to ' + newExpiryDate;
                        } else {
                            alert(response.error || 'Error extending job.');
                        }
                    })
                    .catch(err => {
                        alert('Request failed.');
                        console.error(err);
                    });
                }
            });

        </script>
    </body>
</html>