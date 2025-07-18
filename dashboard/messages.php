<?php
require_once '../config/dbcon.php';

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

// Initialize current user data
if (isset($_SESSION['user_id'])) {
    $currentUser = [
        'entity_type' => 'user',
        'entity_id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_first_name'] ?? 'User',
        'role' => 'Unknown',
        'email' => $_SESSION['user_email'] ?? '',
        'picture' => $_SESSION['picture_file'] ?? ''
    ];

    $user_id = $currentUser['entity_id'];
    $query = "
        SELECT u.*, r.role_title 
        FROM user u
        LEFT JOIN role r ON u.role_id = r.role_id
        WHERE u.user_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id]);
    $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userDetails) {
        $currentUser['full_name'] = $userDetails['user_first_name'] . ' ' . $userDetails['user_last_name'];
        $currentUser['email'] = $userDetails['user_email'];
        $currentUser['picture'] = $userDetails['picture_file'];
        $currentUser['status'] = $userDetails['status'];
        $currentUser['role'] = $userDetails['role_title'];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messaging - <?php echo htmlspecialchars($currentUser['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>

    <style>
        :root {
            --primary-color: #4361ee;
            --primary-light: #e0e7ff;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --light-gray: #e9ecef;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --border-radius: 12px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fb;
            color: #333;
            height: 100vh;
            margin: 0;
        }

        .container-fluid {
            padding: 0;
            width: 100%;
            height: calc(100vh - 0px);
        }

        .chat-container {
            width: calc(100% - 70px);
            margin-left: 70px;
            height: 100%;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            background-color: white;
            display: flex;
        }

        .sidebar {
            width: 300px;
            background-color: white;
            border-right: 1px solid var(--light-gray);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 10px;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-role {
            font-size: 12px;
            color: var(--gray-color);
        }

        .new-message-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .new-message-btn:hover {
            background-color: var(--secondary-color);
            transform: scale(1.05);
        }

        .threads-container {
            flex: 1;
            overflow-y: auto;
        }

        .thread {
            padding: 14px 16px;
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .thread:hover {
            background-color: #f8f9fa;
        }

        .thread.active {
            background-color: var(--primary-light);
            border-left: 3px solid var(--primary-color);
        }

        .thread-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .thread-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .thread-content {
            flex: 1;
            min-width: 0;
        }

        .thread-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .thread-name {
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .thread-time {
            font-size: 0.7rem;
            color: var(--gray-color);
            white-space: nowrap;
            margin-left: 8px;
        }

        .thread-preview {
            display: flex;
            justify-content: space-between;
        }

        .thread-message {
            font-size: 0.85rem;
            color: var(--gray-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .thread-unread {
            background-color: #f0f4ff;
        }

        .unread-badge {
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #f8fafc;
            position: relative;
            z-index: 10;
        }

        .chat-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--light-gray);
            background-color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-to-inbox {
            display: none;
            background: none;
            border: none;
            color: var(--gray-color);
            font-size: 1.2rem;
        }

        .chat-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-info {
            flex: 1;
        }

        .chat-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 2px;
        }

        .chat-status {
            font-size: 0.75rem;
            color: var(--gray-color);
        }

        .message-list {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .message {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
            line-height: 1.4;
            font-size: 0.9rem;
            animation: fadeIn 0.3s ease-out;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-sent {
            background-color: var(--primary-color);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }

        .message-received {
            background-color: white;
            margin-right: auto;
            border-bottom-left-radius: 4px;
        }

        .message-time {
            display: none;
            position: absolute;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            white-space: nowrap;
            z-index: 10;
        }

        .message:hover .message-time {
            display: block;
        }

        .message-sent .message-time {
            left: -10px;
            top: -25px;
        }

        .message-received .message-time {
            right: -10px;
            top: -25px;
        }

        .message-input-container textarea {
            resize: none;
        }

        .message-input-container {
            padding: 16px 20px;
            border-top: 1px solid var(--light-gray);
            background-color: white;
        }

        .message-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-input {
            flex: 1;
            border-radius: 24px;
            padding: 12px 18px;
            border: 1px solid var(--light-gray);
            resize: none;
            font-family: inherit;
            transition: all 0.2s;
        }

        .message-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .send-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .send-button:hover {
            background-color: var(--secondary-color);
            transform: scale(1.05);
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            padding: 40px;
            color: var(--gray-color);
        }

        .empty-state-icon {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 12px;
        }

        .empty-state-text {
            font-size: 0.95rem;
            margin-bottom: 24px;
            max-width: 400px;
            line-height: 1.5;
        }

        .empty-state-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 24px;
            padding: 10px 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .empty-state-btn:hover {
            background-color: var(--secondary-color);
            color: white;
            transform: translateY(-2px);
        }

        .empty-state-tip {
            font-size: 0.8rem;
            color: var(--gray-color);
            margin-top: 24px;
        }

        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--card-shadow);
        }

        .modal-header {
            border-bottom: 1px solid var(--light-gray);
            padding: 16px 20px;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 20px;
        }

        .search-input {
            border-radius: 8px;
            padding: 10px 16px;
            border: 1px solid var(--light-gray);
            width: 100%;
            margin-bottom: 16px;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .search-results {
            max-height: 300px;
            overflow-y: auto;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .search-result-item:hover {
            background-color: #f8f9fa;
        }

        .search-result-item.active {
            background-color: var(--primary-light);
        }

        .search-result-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-result-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .search-result-info {
            flex: 1;
        }

        .search-result-name {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }

        .search-result-role {
            font-size: 0.75rem;
            color: var(--gray-color);
        }

        .modal-footer {
            border-top: 1px solid var(--light-gray);
            padding: 16px 20px;
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding-bottom: 60px; /* Space for sidebar-nav bottom bar */
            }

            .chat-container {
                width: 100%;
                margin-left: 0;
            }

            .sidebar {
                width: 100%;
                display: block;
            }

            .chat-area {
                display: none;
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: white;
                z-index: 1000; /* Increased to ensure visibility, but below sidebar-nav */
            }

            .chat-area.active {
                display: flex;
            }

            .back-to-inbox {
                display: block;
            }
        }

        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            padding: 40px 0;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--primary-light);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .navbar {
            background-color: var(--primary-color);
            padding: 10px 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand img {
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .navbar .navbar-brand {
            font-family: 'Arial', sans-serif;
            font-weight: bold;
            color: var(--primary-color) !important;
        }

        .navbar .navbar-brand:hover {
            color: #FFD700;
        }

        .navbar-light .navbar-nav .nav-link {
            color: #ffffff !important;
        }

        .navbar-light .navbar-nav .nav-link:hover {
            color: #FFD700 !important;
        }

        .navbar a {
            padding-left: 10%;
        }

        .navbar-nav .nav-item {
            margin-right: 15px;
        }

        @media (max-width: 767px) {
            .navbar-nav {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="chat-container">
            <!-- Sidebar Navigation -->
            <?php require '../includes/forum_sidebar.php'; ?>
            
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php if (!empty($currentUser['picture'])): ?>
                                <img src="../Uploads/<?php echo htmlspecialchars($currentUser['picture']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <i class="bi bi-person-fill text-muted"></i>
                            <?php endif; ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                            <div class="user-role"><?php echo htmlspecialchars($currentUser['role']); ?></div>
                        </div>
                    </div>
                    <button id="new-message-btn" class="new-message-btn" title="New Message">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                
                <div class="threads-container" id="threads-list">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-area" id="chat-area">
                <div class="chat-header">
                    <button class="back-to-inbox" id="back-to-inbox">
                        <i class="bi bi-arrow-left"></i>
                    </button>
                    <div class="chat-avatar" id="chat-avatar">
                        <i class="bi bi-person-fill text-muted"></i>
                    </div>
                    <div class="chat-info">
                        <div class="chat-name" id="chat-with-name">Select a conversation</div>
                        <div class="chat-status" id="chat-status"></div>
                    </div>
                </div>
                
                <!-- Empty State -->
                <div class="empty-state" id="empty-chat-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-chat-square-text"></i>
                    </div>
                    <h4 class="empty-state-title">Your messages live here</h4>
                    <p class="empty-state-text">Select a conversation or start a new one to begin messaging</p>
                    <button class="empty-state-btn" id="empty-new-message-btn">
                        <i class="bi bi-plus-lg"></i> New Message
                    </button>
                    <div class="empty-state-tip">Tip: You can search for people by name or email</div>
                </div>
                
                <!-- Message List -->
                <div class="message-list d-none" id="message-list"></div>
                
                <!-- Message Input -->
                <div class="message-input-container d-none" id="message-input-container">
                    <div class="message-input-group">
                        <textarea class="message-input" id="message-content" rows="1" placeholder="Type a message..."></textarea>
                        <button class="send-button" id="send-button" type="button">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="search-input" id="recipient-search" placeholder="Search for users...">
                    <div id="search-results" class="search-results">
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-search" style="font-size: 1.5rem;"></i>
                            <p>Search for users to message</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="start-conversation" disabled>Start Conversation</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Pusher globally
        var pusher = new Pusher('d9d029433bbefa08b6a2', {
            cluster: 'ap1',
            encrypted: true
        });

        var currentChannel = null;
        var subscribedChannels = {};
        let currentThreadId = null;
        let currentParticipant = null;
        let currentUser = <?php echo json_encode($currentUser); ?>;
        let newMessageModal = new bootstrap.Modal(document.getElementById('newMessageModal'));

        function subscribeToThread(threadId) {
            if (currentChannel && currentChannel.name !== 'thread_' + threadId) {
                pusher.unsubscribe(currentChannel.name);
            }

            if (!subscribedChannels['thread_' + threadId]) {
                currentChannel = pusher.subscribe('thread_' + threadId);
                subscribedChannels['thread_' + threadId] = true;

                currentChannel.bind('new_message', function(data) {
                    handleNewMessage(data, threadId);
                });

                currentChannel.bind('thread_update', function(data) {
                    handleThreadUpdate(data);
                });
            }
        }

        function handleNewMessage(data, threadId) {
            if (data.thread_id === threadId || !currentThreadId) {
                const messageList = document.getElementById('message-list');
                const isSender = data.sender_type === currentUser.entity_type && 
                                data.sender_id == currentUser.entity_id;
                
                if (data.thread_id === currentThreadId) {
                    const messageEl = document.createElement('div');
                    messageEl.className = `message ${isSender ? 'message-sent' : 'message-received'}`;
                    messageEl.innerHTML = `
                        <div>${data.content}</div>
                        <div class="message-time">${formatTime(data.sent_at)}</div>
                    `;
                    messageList.appendChild(messageEl);
                    
                    messageList.scrollTop = messageList.scrollHeight;
                    
                    if (!isSender) {
                        markMessagesAsRead(threadId);
                    }
                }
                
                loadThreads();
            }
        }

        function handleThreadUpdate(data) {
            if (data.thread_id === currentThreadId || data.action === 'update_all') {
                loadThreads();
            }
        }

        function loadThreads() {
            fetch('../controllers/messages_controller.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_threads`
            })
            .then(response => response.json())
            .then(data => {
                const threadsList = document.getElementById('threads-list');
                
                if (data.status === 'success') {
                    threadsList.innerHTML = '';
                    
                    if (data.threads.length === 0) {
                        threadsList.innerHTML = `
                            <div class="text-center py-5">
                                <i class="bi bi-chat-square-text fs-1 text-muted mb-3"></i>
                                <p class="text-muted">No conversations yet</p>
                                <button class="btn btn-sm btn-primary" id="empty-list-new-message-btn">
                                    Start a conversation
                                </button>
                            </div>
                        `;
                        
                        document.getElementById('empty-list-new-message-btn').addEventListener('click', () => {
                            newMessageModal.show();
                        });
                        return;
                    }

                    data.threads.forEach(thread => {
                        const threadEl = document.createElement('a');
                        threadEl.className = `list-group-item list-group-item-action thread ${thread.unread_count > 0 ? 'thread-unread' : ''}`;
                        threadEl.innerHTML = `
                            <div class="d-flex align-items-center gap-3">
                                <img src="${thread.participant_picture || `https://api.dicebear.com/7.x/initials/svg?seed=${thread.participant_name || 'user'}`}" 
                                     alt="${thread.participant_name}" 
                                     class="rounded-circle" 
                                     style="width: 40px; height: 40px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <strong>${thread.participant_name}</strong>
                                        <small class="thread-time">${formatTime(thread.last_message_time)}</small>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <p class="thread-last-message mb-0">${thread.last_message_content}</p>
                                        ${thread.unread_count > 0 ? `<span class="badge bg-primary rounded-pill">${thread.unread_count}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                        threadEl.addEventListener('click', () => openThread(thread.thread_id));
                        threadsList.appendChild(threadEl);
                    });

                    data.threads.forEach(thread => {
                        if (!subscribedChannels['thread_' + thread.thread_id]) {
                            subscribeToThread(thread.thread_id);
                        }
                    });
                } else {
                    threadsList.innerHTML = `
                        <div class="alert alert-danger">Error loading conversations</div>
                    `;
                }
            });
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            
            if (date.toDateString() === now.toDateString()) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } else if (date.getFullYear() === now.getFullYear()) {
                return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
            } else {
                return date.toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' });
            }
        }

        function openThread(threadId) {
            unsubscribeFromCurrentChannel();
            currentThreadId = threadId;
            subscribeToThread(threadId);    

            currentChannel = pusher.subscribe('thread_' + threadId);
            currentChannel.unbind('new_message');

            currentChannel.bind('new_message', function(data) {
                if (data.thread_id === threadId) {
                    const messageList = document.getElementById('message-list');
                    const isSender = data.sender_type === currentUser.entity_type && 
                                    data.sender_id == currentUser.entity_id;
                    
                    const messageEl = document.createElement('div');
                    messageEl.className = `message ${isSender ? 'message-sent' : 'message-received'}`;
                    messageEl.innerHTML = `
                        <div>${data.content}</div>
                        <div class="message-time">${formatTime(data.sent_at)}</div>
                    `;
                    messageList.appendChild(messageEl);
                    
                    messageList.scrollTop = messageList.scrollHeight;
                    
                    if (!isSender) {
                        markMessagesAsRead(threadId);
                    }
                }
            });
            
            if (window.innerWidth <= 768) {
                document.querySelector('.sidebar-nav').style.display = 'none';
                document.getElementById('chat-area').classList.add('active');
            }
            
            currentThreadId = threadId;
            document.getElementById('empty-chat-state').classList.add('d-none');
            document.getElementById('message-list').classList.remove('d-none');
            document.getElementById('message-input-container').classList.remove('d-none');
            
            fetch('../controllers/messages_controller.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_messages&thread_id=${threadId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    currentParticipant = data.participant;
                    
                    fetch('../controllers/messages_controller.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=get_user_info&entity_type=${currentParticipant.entity_type}&entity_id=${currentParticipant.entity_id}`
                    })
                    .then(response => response.json())
                    .then(userData => {
                        if (userData.status === 'success') {
                            document.getElementById('chat-with-name').textContent = userData.user.name;
                            
                            const chatAvatar = document.getElementById('chat-avatar');
                            chatAvatar.innerHTML = '';
                            if (userData.user.picture) {
                                const img = document.createElement('img');
                                img.src = `../Uploads/${userData.user.picture}`;
                                img.alt = userData.user.name;
                                chatAvatar.appendChild(img);
                            } else {
                                let iconContainer = chatAvatar.querySelector('div');
                                if (!iconContainer) {
                                    iconContainer = document.createElement('div');
                                    chatAvatar.appendChild(iconContainer);
                                }
                                const icon = document.createElement('i');
                                icon.className = 'bi bi-person-fill text-white';
                                iconContainer.appendChild(icon);
                            }
                        }
                    });

                    const messageList = document.getElementById('message-list');
                    messageList.innerHTML = '';
                    
                    if (data.messages.length === 0) {
                        messageList.innerHTML = `
                            <div class="text-center py-5">
                                <p class="text-muted">No messages yet in this conversation</p>
                            </div>
                        `;
                    } else {
                        data.messages.forEach(message => {
                            const messageEl = document.createElement('div');
                            const isSender = message.sender_type === currentUser.entity_type && 
                                            message.sender_id == currentUser.entity_id;
                            
                            messageEl.className = `message ${isSender ? 'message-sent' : 'message-received'}`;
                            messageEl.innerHTML = `
                                <div>${message.content}</div>
                                <div class="message-time">${formatTime(message.sent_at)}</div>
                            `;
                            messageList.appendChild(messageEl);
                        });
                    }
                    
                    messageList.scrollTop = messageList.scrollHeight;
                    
                    if (data.unread_count > 0) {
                        markMessagesAsRead(threadId);
                    }
                }
            });
        }

        function unsubscribeFromCurrentChannel() {
            if (currentChannel) {
                currentChannel.unbind('new_message');
                currentChannel.unbind('thread_update');
                pusher.unsubscribe(currentChannel.name);
                delete subscribedChannels[currentChannel.name];
                currentChannel = null;
            }
        }

        window.addEventListener('beforeunload', () => {
            unsubscribeFromCurrentChannel();
        });

        function markMessagesAsRead(threadId) {
            fetch('../controllers/messages_controller.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_as_read&thread_id=${threadId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    loadThreads();
                }
            });
        }

        function sendMessage() {
            const content = document.getElementById('message-content').value.trim();
            if (!content) return;
            
            if (!currentThreadId && !currentParticipant) {
                alert('Please select a conversation first');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('receiver_type', currentParticipant.entity_type);
            formData.append('receiver_id', currentParticipant.entity_id);
            formData.append('content', content);
            if (currentThreadId) formData.append('thread_id', currentThreadId);

            fetch('../controllers/messages_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('message-content').value = '';
                    if (!currentThreadId) {
                        currentThreadId = data.thread_id;
                        subscribeToThread(currentThreadId);
                    }
                    openThread(currentThreadId);
                    loadThreads();
                }
            });
        }

        document.getElementById('send-button').addEventListener('click', sendMessage);
        
        document.getElementById('message-content').addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        document.getElementById('message-content').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        let selectedRecipient = null;
        
        document.getElementById('new-message-btn').addEventListener('click', () => {
            newMessageModal.show();
        });
        
        document.getElementById('empty-new-message-btn').addEventListener('click', () => {
            newMessageModal.show();
        });

        document.getElementById('recipient-search').addEventListener('input', (e) => {
            const searchTerm = e.target.value.trim();
            const resultsContainer = document.getElementById('search-results');
            
            if (searchTerm.length < 2) {
                resultsContainer.innerHTML = '<div class="list-group-item text-muted text-center">Type at least 2 characters to search</div>';
                return;
            }

            fetch('../controllers/messages_controller.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=search_users&term=${encodeURIComponent(searchTerm)}&current_user_type=${currentUser.entity_type}&current_user_id=${currentUser.entity_id}`
            })
            .then(response => response.json())
            .then(data => {
                resultsContainer.innerHTML = '';
                
                if (data.status === 'success' && data.users.length > 0) {
                    data.users.forEach(user => {
                        const userEl = document.createElement('button');
                        userEl.className = 'list-group-item list-group-item-action';
                        userEl.type = 'button';
                        userEl.innerHTML = `
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-3">
                                    ${user.picture ? 
                                        `<img src="../Uploads/${user.picture}" alt="${user.name}">` : 
                                        `<div class="bg-secondary d-flex align-items-center justify-content-center h-100">
                                            <i class="bi bi-person-fill text-white"></i>
                                        </div>`}
                                </div>
                                <div>
                                    <div class="fw-bold">${user.name}</div>
                                    <small class="text-muted">${user.role}</small>
                                </div>
                            </div>
                        `;
                        
                        userEl.addEventListener('click', () => {
                            document.querySelectorAll('#search-results button').forEach(el => {
                                el.classList.remove('active');
                            });
                            
                            userEl.classList.add('active');
                            
                            selectedRecipient = {
                                entity_type: user.entity_type,
                                entity_id: user.entity_id,
                                name: user.name,
                                picture: user.picture
                            };
                            
                            document.getElementById('start-conversation').disabled = false;
                        });
                        
                        resultsContainer.appendChild(userEl);
                    });
                } else {
                    resultsContainer.innerHTML = '<div class="list-group-item text-muted text-center">No users found</div>';
                }
            });
        });

        document.getElementById('start-conversation').addEventListener('click', () => {
            if (!selectedRecipient) return;
            
            newMessageModal.hide();
            document.getElementById('search-results').innerHTML = '';
            document.getElementById('recipient-search').value = '';
            document.getElementById('start-conversation').disabled = true;
            
            currentThreadId = null;
            currentParticipant = {
                entity_type: selectedRecipient.entity_type,
                entity_id: selectedRecipient.entity_id
            };
            
            document.getElementById('empty-chat-state').classList.add('d-none');
            document.getElementById('message-list').classList.remove('d-none');
            document.getElementById('message-input-container').classList.remove('d-none');
            document.getElementById('chat-with-name').textContent = selectedRecipient.name;
            
            const chatAvatar = document.getElementById('chat-avatar');
            chatAvatar.innerHTML = '';
            if (selectedRecipient.picture) {
                const img = document.createElement('img');
                img.src = `../Uploads/${selectedRecipient.picture}`;
                img.alt = selectedRecipient.name;
                chatAvatar.appendChild(img);
            } else {
                let iconContainer = chatAvatar.querySelector('div');
                if (!iconContainer) {
                    iconContainer = document.createElement('div');
                    chatAvatar.appendChild(iconContainer);
                }
                const icon = document.createElement('i');
                icon.className = 'bi bi-person-fill text-white';
                iconContainer.appendChild(icon);
            }
            
            document.getElementById('message-list').innerHTML = `
                <div class="text-center py-5">
                    <p class="text-muted">Start a new conversation with ${selectedRecipient.name}</p>
                </div>
            `;
            document.getElementById('message-content').focus();
            
            if (window.innerWidth <= 768) {
                document.querySelector('.sidebar').style.display = 'none';
                document.getElementById('chat-area').classList.add('active');
            }
            
            selectedRecipient = null;
        });

        document.getElementById('back-to-inbox').addEventListener('click', () => {
            document.querySelector('.sidebar-nav').style.display = 'flex';
            document.getElementById('chat-area').classList.remove('active');
        });

        document.addEventListener('DOMContentLoaded', () => {
            loadThreads();
            
            const userChannel = pusher.subscribe('user_' + currentUser.entity_id);
            userChannel.bind('update', function(data) {
                if (data.type === 'message') {
                    loadThreads();
                }
            });

            const urlParams = new URLSearchParams(window.location.search);
            const threadId = urlParams.get('thread_id');
            if (threadId) {
                openThread(threadId);
            }
            
            document.getElementById('message-content').addEventListener('focus', function() {
                const messageList = document.getElementById('message-list');
                messageList.scrollTop = messageList.scrollHeight;
            });
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                document.querySelector('.sidebar').style.display = 'block';
                document.getElementById('chat-area').classList.remove('active');
            }
        });

        window.addEventListener('beforeunload', () => {
            Object.keys(subscribedChannels).forEach(channel => {
                pusher.unsubscribe(channel);
            });
        });

        pusher.connection.bind('error', (err) => {
            console.error('Pusher error:', err);
        });
    </script>
</body>
</html>