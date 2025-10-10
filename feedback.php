<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];
    $user_id = $user['id'];
    
    try {
        // Use $conn instead of $pdo since your db.php uses MySQLi
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, rating, comments, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $user_id, $rating, $comments);
        $stmt->execute();
        $success = "Thank you for your feedback! We appreciate your input.";
    } catch (Exception $e) {
        $error = "Error submitting feedback. Please try again.";
    }
}
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
        /* Enhanced Feedback Styles */
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
            transition: transform 0.3s ease;
        }

        .feedback-card:hover {
            transform: translateY(-5px);
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
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .star:hover {
            transform: scale(1.2);
            color: #ffc107;
        }

        .star.active {
            color: #ffc107;
            transform: scale(1.1);
            text-shadow: 0 4px 8px rgba(255,193,7,0.3);
        }

        .rating-text {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
            min-height: 24px;
            font-size: 1.1rem;
            font-weight: 500;
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
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
            transition: all 0.3s ease;
        }

        .feedback-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
            line-height: 1.5;
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
            font-size: 1.1rem;
        }

        /* Enhanced Message Styles */
        .message {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            animation: slideDown 0.5s ease;
        }

        .success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 2px solid #c3e6cb;
            box-shadow: 0 4px 15px rgba(21, 87, 36, 0.1);
        }

        .error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 2px solid #f5c6cb;
            box-shadow: 0 4px 15px rgba(114, 28, 36, 0.1);
        }

        /* Floating Notification */
        .floating-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 20px 25px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            z-index: 1000;
            animation: slideInRight 0.5s ease, fadeOut 0.5s ease 2.5s forwards;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            max-width: 300px;
        }

        .notification-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }

        .notification-error {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        /* Animations */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .pulse {
            animation: pulse 0.5s ease;
        }

        /* Stats Section */
        .feedback-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-top: 4px solid #e74c3c;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .feedback-container {
                padding: 10px;
            }
            
            .nav a {
                display: block;
                margin: 5px 0;
            }
            
            .star {
                font-size: 2.5rem;
                margin: 0 5px;
            }
            
            .feedback-stats {
                grid-template-columns: 1fr;
            }
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
                <div style="font-size: 2rem; margin-bottom: 10px;">🎉</div>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error">
                <div style="font-size: 2rem; margin-bottom: 10px;">⚠️</div>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Feedback Stats -->
        <div class="feedback-stats">
            <div class="stat-card">
                <div class="stat-number" id="totalFeedback">0</div>
                <div class="stat-label">Total Feedback</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="averageRating">0.0</div>
                <div class="stat-label">Average Rating</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">⭐</div>
                <div class="stat-label">Your Opinion Matters</div>
            </div>
        </div>

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
                    <textarea name="comments" placeholder="Tell us more about your experience... What did you love? What can we improve? ✨" maxlength="500"></textarea>
                    <div style="text-align: right; margin-top: 5px; color: #888; font-size: 0.9rem;">
                        <span id="charCount">0</span>/500 characters
                    </div>
                </div>

                <button type="submit" name="submit_feedback" class="submit-btn" id="submitBtn" disabled>
                    <span id="submitText">📤 Submit Feedback</span>
                    <span id="loadingText" style="display: none;">⏳ Submitting...</span>
                </button>
            </form>
        </div>

        <!-- Previous Feedback -->
        <div class="previous-feedback">
            <h3>📝 Your Previous Feedback</h3>
            <?php
            // Fetch user's previous feedback using MySQLi
            $stmt = $conn->prepare("SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $previous_feedback = $result->fetch_all(MYSQLI_ASSOC);
            
            if ($previous_feedback): 
                foreach ($previous_feedback as $feedback): 
            ?>
                <div class="feedback-item">
                    <div class="feedback-rating">
                        <?php echo str_repeat('★', $feedback['rating']); ?>
                        <span style="color: #666; font-size: 1rem; margin-left: 10px;">
                            (<?php echo $feedback['rating']; ?>/5)
                        </span>
                    </div>
                    <?php if (!empty($feedback['comments'])): ?>
                        <div class="feedback-comments">"<?php echo htmlspecialchars($feedback['comments']); ?>"</div>
                    <?php endif; ?>
                    <div class="feedback-date">
                        📅 <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?>
                    </div>
                </div>
            <?php 
                endforeach; 
            else: 
            ?>
                <div class="no-feedback">
                    <div style="font-size: 4rem; margin-bottom: 20px;">📝</div>
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
            const submitText = document.getElementById('submitText');
            const loadingText = document.getElementById('loadingText');
            const charCount = document.getElementById('charCount');
            const commentsTextarea = document.querySelector('textarea[name="comments"]');
            let currentRating = 0;

            const ratingTexts = {
                1: "😞 Poor - We're sorry to hear about your experience",
                2: "😐 Fair - We appreciate your honest feedback",
                3: "😊 Good - Thank you for your feedback",
                4: "😄 Very Good - We're glad you enjoyed!",
                5: "🤩 Excellent - We're thrilled you loved it!"
            };

            // Character count for comments
            commentsTextarea.addEventListener('input', function() {
                const length = this.value.length;
                charCount.textContent = length;
                
                if (length > 450) {
                    charCount.style.color = '#e74c3c';
                } else if (length > 400) {
                    charCount.style.color = '#f39c12';
                } else {
                    charCount.style.color = '#888';
                }
            });

            // Star rating functionality
            stars.forEach(star => {
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    highlightStars(rating);
                    ratingText.textContent = ratingTexts[rating] || '';
                    ratingText.classList.add('pulse');
                });

                star.addEventListener('mouseleave', function() {
                    highlightStars(currentRating);
                    ratingText.textContent = currentRating ? ratingTexts[currentRating] : 'Click on the stars to rate your experience';
                    ratingText.classList.remove('pulse');
                });

                star.addEventListener('click', function() {
                    currentRating = parseInt(this.getAttribute('data-rating'));
                    ratingValue.value = currentRating;
                    highlightStars(currentRating);
                    ratingText.textContent = ratingTexts[currentRating] || '';
                    submitBtn.disabled = false;
                    
                    // Add celebration effect for 5-star rating
                    if (currentRating === 5) {
                        ratingText.innerHTML = ratingTexts[5] + ' 🎉';
                    }
                });
            });

            function highlightStars(rating) {
                stars.forEach(star => {
                    const starRating = parseInt(star.getAttribute('data-rating'));
                    if (starRating <= rating) {
                        star.classList.add('active');
                    } else {
                        star.classList.remove('active');
                    }
                });
            }

            // Form submission with loading state
            document.getElementById('feedbackForm').addEventListener('submit', function(e) {
                if (currentRating > 0) {
                    submitText.style.display = 'none';
                    loadingText.style.display = 'inline';
                    submitBtn.disabled = true;
                    
                    // Show floating notification after a short delay
                    setTimeout(() => {
                        showFloatingNotification('🎉 Feedback submitted successfully!', 'success');
                    }, 1000);
                }
            });

            // Floating notification function
            function showFloatingNotification(message, type) {
                const notification = document.createElement('div');
                notification.className = `floating-notification notification-${type}`;
                notification.innerHTML = `
                    <div style="font-size: 1.5rem; margin-bottom: 8px;">${type === 'success' ? '⭐' : '⚠️'}</div>
                    <div>${message}</div>
                `;
                document.body.appendChild(notification);
                
                // Remove notification after animation
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 3000);
            }

            // Update stats (you can replace with actual data from your database)
            function updateStats() {
                // This is a placeholder - you can fetch actual stats from your database
                document.getElementById('totalFeedback').textContent = '<?php echo count($previous_feedback); ?>';
                
                // Calculate average rating
                const ratings = <?php echo json_encode(array_column($previous_feedback, 'rating')); ?>;
                const average = ratings.length > 0 ? 
                    (ratings.reduce((a, b) => a + b, 0) / ratings.length).toFixed(1) : '0.0';
                document.getElementById('averageRating').textContent = average;
            }

            updateStats();
        });
    </script>
</body>
</html>