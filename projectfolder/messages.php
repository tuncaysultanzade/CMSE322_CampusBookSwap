<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$current_user_id = getCurrentUserId();
$other_user_id = $_GET['user'] ?? null;
$listing_id = $_GET['listing'] ?? null;
$error = '';
$success = '';
$conn = getDbConnection();

// Get user's conversations (grouped by other user)
$stmt = prepareStatement("
    WITH LastMessages AS (
        SELECT 
            CASE 
                WHEN sender_id = ? THEN receiver_id
                ELSE sender_id
            END as other_user_id,
            text,
            sender_id,
            timestamp,
            ROW_NUMBER() OVER (PARTITION BY 
                CASE 
                    WHEN sender_id = ? THEN receiver_id
                    ELSE sender_id
                END 
                ORDER BY timestamp DESC
            ) as rn
        FROM message
        WHERE sender_id = ? OR receiver_id = ?
    )
    SELECT 
        lm.other_user_id,
        u.name as other_user_name,
        lm.text as last_message,
        lm.sender_id as last_message_sender,
        lm.timestamp as last_message_time,
        COUNT(CASE WHEN m.is_read = 0 AND m.sender_id != ? THEN 1 END) as unread_count
    FROM LastMessages lm
    JOIN user u ON u.user_id = lm.other_user_id
    LEFT JOIN message m ON (
        (m.sender_id = lm.other_user_id AND m.receiver_id = ?) OR
        (m.receiver_id = lm.other_user_id AND m.sender_id = ?)
    )
    WHERE lm.rn = 1
    GROUP BY lm.other_user_id, u.name, lm.text, lm.sender_id, lm.timestamp
    ORDER BY lm.timestamp DESC
");

$stmt->bind_param("iiiiiii", 
    $current_user_id, // CASE in CTE
    $current_user_id, // Second CASE in CTE
    $current_user_id, // WHERE sender_id in CTE
    $current_user_id, // WHERE receiver_id in CTE
    $current_user_id, // COUNT CASE
    $current_user_id, // JOIN message sender_id
    $current_user_id  // JOIN message receiver_id
);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// If other_user_id is provided, get messages
if ($other_user_id) {
    // Verify conversation exists or user exists
    $user_exists = false;
    foreach ($conversations as $conv) {
        if ($conv['other_user_id'] == $other_user_id) {
            $user_exists = true;
            $other_user_name = $conv['other_user_name'];
            break;
        }
    }
    
    if (!$user_exists) {
        // Check if user exists but no messages yet
        $stmt = prepareStatement("SELECT name FROM user WHERE user_id = ?");
        $stmt->bind_param("i", $other_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_exists = true;
            $other_user_name = $result->fetch_assoc()['name'];
        } else {
            header('Location: messages.php');
            exit();
        }
    }
    
    // Mark messages as read
    $stmt = prepareStatement("
        UPDATE message 
        SET is_read = 1
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("ii", $other_user_id, $current_user_id);
    $stmt->execute();
    
    // Get messages between users
    $stmt = prepareStatement("
        SELECT m.*,
               u.name as sender_name,
               NULL as listing_id,
               NULL as book_title,
               NULL as book_price,
               NULL as book_image
        FROM message m
        JOIN user u ON m.sender_id = u.user_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.timestamp ASC
    ");
    $stmt->bind_param("iiii", 
        $current_user_id, $other_user_id,
        $other_user_id, $current_user_id
    );
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    $to_user_id = $_POST['to_user_id'];
    
    if (!empty($message) && $to_user_id) {
        try {
            $conn = getDbConnection();
            
            // Insert message
            $stmt = prepareStatement("
                INSERT INTO message (
                    sender_id, receiver_id,
                    text, timestamp
                ) VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param("iis", 
                $current_user_id,
                $to_user_id,
                $message
            );
            $stmt->execute();
            
            header("Location: messages.php?user=" . $to_user_id);
            exit();
            
        } catch (Exception $e) {
            $error = 'Error sending message. Please try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- Conversations List -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Messages</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($conversations)): ?>
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-comments fa-2x mb-2"></i>
                            <p class="mb-0">No conversations yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <a href="?user=<?php echo $conv['other_user_id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo $other_user_id == $conv['other_user_id'] ? 'conversation-active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($conv['other_user_name']); ?></h6>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo $conv['unread_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($conv['last_message']): ?>
                                    <p class="mb-1 small text-truncate">
                                        <?php if ($conv['last_message_sender'] == $current_user_id): ?>
                                            <i class="fas fa-reply text-muted"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($conv['last_message']); ?>
                                    </p>
                                    <small class="text-muted">
                                        <?php echo date('M j, g:i a', strtotime($conv['last_message_time'])); ?>
                                    </small>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div class="col-md-8">
            <?php if ($other_user_id): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <?php echo htmlspecialchars($other_user_name); ?>
                        </h5>
                        <a href="profile.php?id=<?php echo $other_user_id; ?>" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-user"></i> View Profile
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="messages-container" style="height: 400px; overflow-y: auto;">
                            <?php if (isset($messages)): foreach ($messages as $message): ?>
                                <div class="mb-3 <?php echo $message['sender_id'] == $current_user_id ? 'text-end' : ''; ?>">
                                    <div class="d-inline-block p-2 rounded <?php 
                                        echo $message['sender_id'] == $current_user_id 
                                            ? 'bg-primary text-white' 
                                            : 'bg-light'; 
                                    ?>" style="max-width: 75%;">
                                        <?php echo nl2br(htmlspecialchars($message['text'])); ?>
                                    </div>
                                    <div>
                                        <small class="text-muted">
                                            <?php echo date('M j, g:i a', strtotime($message['timestamp'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>

                        <hr>

                        <form method="POST" class="message-form">
                            <input type="hidden" name="to_user_id" 
                                   value="<?php echo $other_user_id; ?>">
                            <div class="form-group">
                                <textarea class="form-control" name="message" rows="3" 
                                          placeholder="Type your message..." required></textarea>
                            </div>
                            <div class="text-end mt-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                        <p class="lead">Select a conversation to view messages</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Scroll to bottom of messages container
    var messagesContainer = $('.messages-container');
    if (messagesContainer.length) {
        messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
    }
    
    // Auto-expand textarea
    $('textarea').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});
</script>

<?php include 'includes/footer.php'; ?> 