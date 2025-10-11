[file name]: feedback.php
[file content begin]
<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Debug: Check what tables exist
$tables_result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $tables_result->fetch_row()) {
    $tables[] = $row[0];
}
error_log("Available tables: " . implode(', ', $tables));

// Check if feedback table exists and has correct structure
$feedback_table = $conn->query("SHOW TABLES LIKE 'feedback'");
if ($feedback_table->num_rows === 0) {
    // Create feedback table without foreign key first
    $create_feedback = "CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        user_name VARCHAR(100) NOT NULL,
        rating INT NOT NULL,
        comments TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_feedback)) {
        error_log("Feedback table created successfully");
    } else {
        error_log("Feedback table creation failed: " . $conn->error);
    }
} else {
    // Check if user_name column exists, if not add it
    $check_columns = $conn->query("SHOW COLUMNS FROM feedback LIKE 'user_name'");
    if ($check_columns->num_rows === 0) {
        $add_column = "ALTER TABLE feedback ADD COLUMN user_name VARCHAR(100) NOT NULL AFTER user_id";
        if ($conn->query($add_column)) {
            error_log("Added user_name column to feedback table");
        } else {
            error_log("Failed to add user_name column: " . $conn->error);
        }
    }
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];
    $user_id = $user['id'];
    $user_name = $user['full_name'];
    
    // Debug log
    error_log("Feedback submission attempt:");
    error_log(" - User ID: " . $user_id);
    error_log(" - User Name: " . $user_name);
    error_log(" - Rating: " . $rating);
    error_log(" - Comments: " . $comments);
    
    try {
        // Insert feedback with user_name to avoid foreign key issues
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, user_name, rating, comments, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("isis", $user_id, $user_name, $rating, $comments);
        
        if ($stmt->execute()) {
            $success = "Thank you for your feedback! We appreciate your input.";
            error_log("Feedback submitted successfully for user: " . $user_name);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        $error = "Error submitting feedback: " . $e->getMessage();
        error_log("Feedback submission error: " . $e->getMessage());
        
        // Try alternative insert without user_id if foreign key fails
        try {
            $stmt = $conn->prepare("INSERT INTO feedback (user_name, rating, comments, created_at) VALUES (?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("sis", $user_name, $rating, $comments);
                if ($stmt->execute()) {
                    $success = "Thank you for your feedback! We appreciate your input.";
                    error_log("Feedback submitted successfully (alternative method) for user: " . $user_name);
                }
            }
        } catch (Exception $e2) {
            error_log("Alternative feedback submission also failed: " . $e2->getMessage());
        }
    }
}

// Fetch user's previous feedback
$previous_feedback = [];
try {
    // First try to get feedback by user_id
    $stmt = $conn->prepare("SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    if ($stmt) {
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $previous_feedback = $result->fetch_all(MYSQLI_ASSOC);
        error_log("Found " . count($previous_feedback) . " previous feedback entries by user_id for user: " . $user['id']);
    }
    
    // If no feedback found by user_id, try by user_name
    if (empty($previous_feedback)) {
        $stmt = $conn->prepare("SELECT * FROM feedback WHERE user_name = ? ORDER BY created_at DESC LIMIT 5");
        if ($stmt) {
            $stmt->bind_param("s", $user['full_name']);
            $stmt->execute();
            $result = $stmt->get_result();
            $previous_feedback = $result->fetch_all(MYSQLI_ASSOC);
            error_log("Found " . count($previous_feedback) . " previous feedback entries by user_name for user: " . $user['full_name']);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching previous feedback: " . $e->getMessage());
}

// Debug: Check current feedback count
$feedback_count = $conn->query("SELECT COUNT(*) as count FROM feedback")->fetch_assoc();
error_log("Total feedback entries in database: " . $feedback_count['count']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foodhouse | Feedback</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .feedback-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .feedback-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .feedback-header h1 {
            color: #333;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .user-info {
            text-align: center;
            margin-bottom: 20px;
            color: #666;
            font-size: 1.1rem;
        }

        .nav {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .nav a {
            text-decoration: none;
            color: #e74c3c;
            font-weight: 600;
            margin: 0 15px;
            padding: 8px 16px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .nav a:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }

        .nav a.active {
            background: #e74c3c;
            color: white;
        }

        .feedback-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border-left: 5px solid #e74c3c;
        }

        .feedback-card h2 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.8rem;
        }

        .star-rating {
            text-align: center;
            margin-bottom: 20px;
        }

        .star {
            font-size: 3rem;
            color: #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0 8px;
        }

        .star:hover {
            transform: scale(1.2);
            color: #ffc107;
        }

        .star.active {
            color: #ffc107;
            transform: scale(1.1);
        }

        .rating-text {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
            min-height: 24px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            resize: vertical;
            min-height: 120px;
            transition: all 0.3s ease;
        }

        textarea:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover:not(:disabled) {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .previous-feedback {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .previous-feedback h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5rem;
            text-align: center;
        }

        .feedback-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #e74c3c;
        }

        .feedback-rating {
            color: #ffc107;
            font-size: 1.3rem;
            margin-bottom: 8px;
        }

        .feedback-comments {
            color: #555;
            margin-bottom: 8px;
            font-style: italic;
        }

        .feedback-date {
            color: #888;
            font-size: 0.9rem;
            text-align: right;
        }

        .no-feedback {
            text-align: center;
            color: #666;
            padding: 40px;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 0.8rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="feedback-container">
        <!-- Header -->
        <div class="feedback-header">
            <h1>🍖 Foodhouse Grillhouse</h1>
            <div class="user-info">
                Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav">
            <a href="user_home.php">🏠 Home</a>
            <a href="feedback.php" class="active">⭐ Feedback</a>
            <a href="auth.php?action=logout">🚪 Logout</a>
        </div>

        <!-- Messages -->
        <?php if (isset($success)): ?>
            <div class="message success">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error">
                ❌ <?php echo $error; ?>
                <div class="debug-info">
                    <strong>Debug Info:</strong><br>
                    User: <?php echo $user['full_name']; ?> (ID: <?php echo $user['id']; ?>)<br>
                    Total Feedback in DB: <?php echo $feedback_count['count']; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Feedback Form -->
        <div class="feedback-card">
            <h2>🌟 Share Your Experience</h2>
            
            <form method="POST" id="feedbackForm">
                <!-- Star Rating -->
                <div class="star-rating">
                    <input type="hidden" name="rating" id="ratingValue" required>
                    <span class="star" data-rating="1" title="Poor">★</span>
                    <span class="star" data-rating="2" title="Fair">★</span>
                    <span class="star" data-rating="3" title="Good">★</span>
                    <span class="star" data-rating="4" title="Very Good">★</span>
                    <span class="star" data-rating="5" title="Excellent">★</span>
                </div>
                <div class="rating-text" id="ratingText">Click on the stars to rate your experience</div>

                <!-- Comments -->
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; color: #333; font-weight: 600;">
                        💬 Additional Comments (Optional)
                    </label>
                    <textarea name="comments" placeholder="Tell us more about your experience... What did you love? What can we improve?" maxlength="500"></textarea>
                </div>

                <button type="submit" name="submit_feedback" class="submit-btn" id="submitBtn" disabled>
                    Submit Feedback
                </button>
            </form>
        </div>

        <!-- Previous Feedback -->
        <div class="previous-feedback">
            <h3>📝 Your Previous Feedback</h3>
            <?php if ($previous_feedback): ?>
                <?php foreach ($previous_feedback as $feedback): ?>
                    <div class="feedback-item">
                        <div class="feedback-rating">
                            <?php echo str_repeat('★', $feedback['rating']); ?>
                            <span style="color: #666; font-size: 1rem;">
                                (<?php echo $feedback['rating']; ?>/5)
                            </span>
                        </div>
                        <?php if (!empty($feedback['comments'])): ?>
                            <div class="feedback-comments">"<?php echo htmlspecialchars($feedback['comments']); ?>"</div>
                        <?php endif; ?>
                        <div class="feedback-date">
                            <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-feedback">
                    <p>You haven't submitted any feedback yet.</p>
                    <p>Be the first to share your experience! 🌟</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star');
            const ratingValue = document.getElementById('ratingValue');
            const ratingText = document.getElementById('ratingText');
            const submitBtn = document.getElementById('submitBtn');
            let currentRating = 0;

            const ratingTexts = {
                1: "Poor - We're sorry to hear about your experience",
                2: "Fair - We appreciate your honest feedback",
                3: "Good - Thank you for your feedback",
                4: "Very Good - We're glad you enjoyed!",
                5: "Excellent - We're thrilled you loved it!"
            };

            stars.forEach(star => {
                star.addEventListener('click', function() {
                    currentRating = parseInt(this.getAttribute('data-rating'));
                    ratingValue.value = currentRating;
                    
                    // Update star display
                    stars.forEach(s => {
                        const starRating = parseInt(s.getAttribute('data-rating'));
                        if (starRating <= currentRating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                    
                    ratingText.textContent = ratingTexts[currentRating];
                    submitBtn.disabled = false;
                });
            });
        });
    </script>
</body>
</html>
[file content end]