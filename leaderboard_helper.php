<?php
/**
 * Leaderboard Helper Functions
 * Scoring: 30% Hours, 50% Sit-ins (every 3 = 1 point), 20% Tasks Completed
 */

function getLeaderboardData($conn, $limit = 10) {
    $leaderboard = [];
    
    // Get all students with their stats
    $query = "
        SELECT 
            s.IdNumber,
            s.FirstName,
            s.LastName,
            s.Course,
            s.PhotoPath,
            COALESCE(COUNT(DISTINCT sit.SessionID), 0) as total_sessions,
            COALESCE(SUM(CASE 
                WHEN sit.TimeOut IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, sit.TimeIn, sit.TimeOut) 
                ELSE 0 
            END), 0) as total_minutes,
            COALESCE(SUM(CASE WHEN sit.Type='Sit-in' OR sit.Type IS NULL THEN 1 ELSE 0 END), 0) as total_sitins,
            COALESCE(SUM(CASE WHEN (sit.Type='Sit-in' OR sit.Type IS NULL) AND sit.TaskCompleted >= 0.5 THEN 1 ELSE 0 END), 0) as tasks_completed
        FROM students_info s
        LEFT JOIN sit_in_sessions sit ON s.IdNumber = sit.StudentID AND sit.Status='Completed'
        WHERE s.is_admin = 0
        GROUP BY s.IdNumber, s.FirstName, s.LastName, s.Course, s.PhotoPath
        ORDER BY total_minutes DESC
    ";
    
    $result = $conn->query($query);
    $rank = 1;
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Calculate scores
            $hours = round($row['total_minutes'] / 60, 1);
            
            // 30% - Most hours (normalize to 100 max)
            $max_hours = 100; // 100 hours as max reference
            $hours_score = min(($hours / $max_hours) * 100, 100) * 0.30;
            
            // 50% - Sit-ins (every 3 sit-ins = 1 point, normalize to 100 max)
            // total_sitins / 3 gives us points, max 100 points = 300 sit-ins
            $sitin_points = floor($row['total_sitins'] / 3);
            $max_sitin_points = 100; // 100 points as max reference (300 sit-ins)
            $sitin_score = min(($sitin_points / $max_sitin_points) * 100, 100) * 0.50;
            
            // 20% - Tasks completed (count sit-ins where task was completed, normalize to 100 max)
            $max_tasks = 100; // 100 completed tasks as max reference
            $tasks_score = min(($row['tasks_completed'] / $max_tasks) * 100, 100) * 0.20;
            
            $total_score = round($hours_score + $sitin_score + $tasks_score, 2);
            
            $leaderboard[] = [
                'rank' => $rank,
                'id' => $row['IdNumber'],
                'name' => $row['FirstName'] . ' ' . $row['LastName'],
                'course' => $row['Course'],
                'photo_path' => $row['PhotoPath'],
                'total_score' => $total_score,
                'hours_score' => round($hours_score, 2),
                'sitin_score' => round($sitin_score, 2),
                'tasks_score' => round($tasks_score, 2),
                'hours' => $hours,
                'sitin_points' => $sitin_points,
                'sitins' => $row['total_sitins'],
                'tasks_completed' => $row['tasks_completed']
            ];
            $rank++;
        }
    }
    
    // Sort by total score descending and limit
    usort($leaderboard, function($a, $b) {
        return $b['total_score'] <=> $a['total_score'];
    });
    
    return array_slice($leaderboard, 0, $limit);
}

function getStudentLeaderboardRank($conn, $student_id) {
    $leaderboard = getLeaderboardData($conn, 1000);
    
    foreach ($leaderboard as $index => $student) {
        if ($student['id'] === $student_id) {
            return [
                'rank' => $index + 1,
                'total_rank' => count($leaderboard),
                'score' => $student['total_score']
            ];
        }
    }
    
    return null;
}

function displayLeaderboard($leaderboard, $show_details = false, $class_name = '') {
    if (empty($leaderboard)) {
        echo '<p class="text-center text-muted" style="padding: 20px;">No leaderboard data available.</p>';
        return;
    }
    ?>
    <div class="leaderboard-container <?php echo htmlspecialchars($class_name); ?>">
        <table class="leaderboard-table">
            <thead>
                <tr>
                    <th class="rank-col">Rank</th>
                    <th class="name-col">Student</th>
                    <th class="course-col">Course</th>
                    <th class="score-col">Score</th>
                    <?php if ($show_details): ?>
                        <th class="detail-col">Hours</th>
                        <th class="detail-col">Sit-ins Pts</th>
                        <th class="detail-col">Tasks Done</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaderboard as $entry): ?>
                    <tr class="leaderboard-row <?php echo $entry['rank'] <= 3 ? 'top-rank rank-' . $entry['rank'] : ''; ?>">
                        <td class="rank-cell">
                            <?php if ($entry['rank'] == 1): ?>
                                <span class="rank-badge gold"><i class="fa fa-crown"></i> 1st</span>
                            <?php elseif ($entry['rank'] == 2): ?>
                                <span class="rank-badge silver"><i class="fa fa-medal"></i> 2nd</span>
                            <?php elseif ($entry['rank'] == 3): ?>
                                <span class="rank-badge bronze"><i class="fa fa-medal"></i> 3rd</span>
                            <?php else: ?>
                                <span class="rank-number"><?php echo $entry['rank']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="name-cell">
                            <div class="student-info">
                                <?php if ($entry['photo_path'] && file_exists($entry['photo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($entry['photo_path']); ?>" alt="Photo" class="student-avatar">
                                <?php else: ?>
                                    <div class="student-avatar-initials">
                                        <?php echo strtoupper(substr($entry['name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="student-name"><?php echo htmlspecialchars($entry['name']); ?></span>
                            </div>
                        </td>
                        <td class="course-cell"><?php echo htmlspecialchars($entry['course']); ?></td>
                        <td class="score-cell">
                            <span class="score-badge"><?php echo number_format($entry['total_score'], 2); ?></span>
                        </td>
                        <?php if ($show_details): ?>
                            <td class="detail-cell">
                                <small><?php echo number_format($entry['hours'], 1); ?>h</small>
                            </td>
                            <td class="detail-cell">
                                <small><?php echo $entry['sitin_points']; ?> pts</small>
                            </td>
                            <td class="detail-cell">
                                <small><?php echo $entry['tasks_completed']; ?> done</small>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Get AI Recommendations for a Student
 * Analyzes student performance and generates personalized suggestions
 */
function getStudentRecommendations($conn, $student_id) {
    $recommendations = [];
    
    // Get student stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT sit.SessionID) as total_sessions,
            SUM(CASE WHEN sit.TimeOut IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, sit.TimeIn, sit.TimeOut) ELSE 0 END) as total_minutes,
            SUM(CASE WHEN sit.Type='Sit-in' OR sit.Type IS NULL THEN 1 ELSE 0 END) as total_sitins,
            SUM(CASE WHEN (sit.Type='Sit-in' OR sit.Type IS NULL) AND sit.TaskCompleted >= 0.5 THEN 1 ELSE 0 END) as tasks_completed,
            COUNT(DISTINCT CASE WHEN sit.TaskCompleted = 0 AND sit.TaskCompleted IS NOT NULL THEN sit.SessionID END) as tasks_incomplete
        FROM sit_in_sessions sit
        WHERE sit.StudentID=? AND sit.Status='Completed'
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get leaderboard rank
    $rank_info = getStudentLeaderboardRank($conn, $student_id);
    
    // Get total students
    $total_students = $conn->query("SELECT COUNT(*) as c FROM students_info WHERE is_admin=0")->fetch_assoc()['c'];
    
    $hours = $stats['total_minutes'] ? round($stats['total_minutes'] / 60, 1) : 0;
    $sitins = $stats['total_sitins'] ? (int)$stats['total_sitins'] : 0;
    $tasks_completed = $stats['tasks_completed'] ? (int)$stats['tasks_completed'] : 0;
    $tasks_incomplete = $stats['tasks_incomplete'] ? (int)$stats['tasks_incomplete'] : 0;
    $task_completion_rate = ($sitins > 0) ? round(($tasks_completed / $sitins) * 100) : 0;
    $rank = $rank_info['rank'] ?? 999;
    $score = $rank_info['score'] ?? 0;
    
    // Recommendation 1: Hours
    if ($hours < 10) {
        $recommendations[] = [
            'type' => 'hours',
            'icon' => 'fa-clock',
            'title' => '⏱️ Increase Lab Hours',
            'message' => 'You have ' . $hours . ' hours. Try to reach at least 20 hours for better lab experience and skills.',
            'priority' => 'high',
            'color' => '#dc3545'
        ];
    } elseif ($hours >= 50) {
        $recommendations[] = [
            'type' => 'hours',
            'icon' => 'fa-star',
            'title' => '⭐ Excellent Dedication!',
            'message' => 'You\'ve logged ' . $hours . ' hours in the lab! Keep maintaining this excellent habit.',
            'priority' => 'low',
            'color' => '#28a745'
        ];
    }
    
    // Recommendation 2: Sit-ins (every 3 = 1 point)
    $sitin_points = floor($sitins / 3);
    if ($sitins < 12) {
        $recommendations[] = [
            'type' => 'sitins',
            'icon' => 'fa-chair',
            'title' => '🪑 More Sit-in Sessions',
            'message' => 'You\'ve completed ' . $sitins . ' sit-ins (' . $sitin_points . ' points). Target 30 sit-ins for maximum points.',
            'priority' => 'high',
            'color' => '#ff6b6b'
        ];
    } elseif ($sitins >= 30) {
        $recommendations[] = [
            'type' => 'sitins',
            'icon' => 'fa-trophy',
            'title' => '🏆 Outstanding Attendance!',
            'message' => 'You\'ve had ' . $sitins . ' sit-ins! This shows great commitment to your lab work.',
            'priority' => 'low',
            'color' => '#ffc107'
        ];
    }
    
    // Recommendation 3: Task Completion
    if ($task_completion_rate < 50) {
        $recommendations[] = [
            'type' => 'tasks',
            'icon' => 'fa-check-circle',
            'title' => '✓ Focus on Task Completion',
            'message' => 'Only ' . $task_completion_rate . '% of your tasks are completed. Try to complete all assigned tasks during sessions.',
            'priority' => 'high',
            'color' => '#fd7e14'
        ];
    } elseif ($task_completion_rate >= 85) {
        $recommendations[] = [
            'type' => 'tasks',
            'icon' => 'fa-star',
            'title' => '⭐ Great Task Completion!',
            'message' => $task_completion_rate . '% task completion rate! You\'re doing excellent work.',
            'priority' => 'low',
            'color' => '#28a745'
        ];
    }
    
    // Recommendation 4: Leaderboard Rank
    if ($rank <= 3) {
        $recommendations[] = [
            'type' => 'rank',
            'icon' => 'fa-crown',
            'title' => '👑 Top Performer!',
            'message' => 'You\'re ranked #' . $rank . ' on the leaderboard! Outstanding performance!',
            'priority' => 'low',
            'color' => '#ffc107'
        ];
    } elseif ($rank <= 10) {
        $recommendations[] = [
            'type' => 'rank',
            'icon' => 'fa-medal',
            'title' => '🎖️ Top 10 Achiever',
            'message' => 'You\'re in the top 10 with rank #' . $rank . '! Keep up the great work!',
            'priority' => 'low',
            'color' => '#17a2b8'
        ];
    } elseif ($rank > ($total_students * 0.75)) {
        $recommendations[] = [
            'type' => 'rank',
            'icon' => 'fa-arrow-up',
            'title' => '📈 Room for Improvement',
            'message' => 'You\'re ranked #' . $rank . '. Push harder to climb the leaderboard!',
            'priority' => 'medium',
            'color' => '#6c757d'
        ];
    }
    
    // Recommendation 5: Incomplete tasks feedback
    if ($tasks_incomplete > 0 && $sitins > 0) {
        $incomplete_rate = round(($tasks_incomplete / $sitins) * 100);
        if ($incomplete_rate >= 30) {
            $recommendations[] = [
                'type' => 'incomplete_tasks',
                'icon' => 'fa-exclamation-triangle',
                'title' => '⚠️ Address Incomplete Tasks',
                'message' => $incomplete_rate . '% of your sessions have incomplete tasks. Review feedback and improve next session.',
                'priority' => 'high',
                'color' => '#dc3545'
            ];
        }
    }
    
    // Recommendation 6: Overall score
    if ($score >= 80) {
        $recommendations[] = [
            'type' => 'score',
            'icon' => 'fa-chart-line',
            'title' => '📊 Excellent Overall Score!',
            'message' => 'Your overall score is ' . number_format($score, 2) . '! You\'re excelling across all metrics.',
            'priority' => 'low',
            'color' => '#28a745'
        ];
    }
    
    return $recommendations;
}

function getLeaderboardStyles() {
    return <<<CSS
    <style>
        .leaderboard-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        .leaderboard-table thead {
            background-color: #5c2b7a;
            color: white;
            font-weight: 600;
        }
        
        .leaderboard-table thead th {
            padding: 12px 16px;
            text-align: left;
            border: none;
        }
        
        .rank-col { width: 80px; }
        .name-col { flex: 1; min-width: 180px; }
        .course-col { width: 110px; max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .score-col { width: 100px; text-align: right; }
        .detail-col { width: 70px; text-align: center; }
        
        .leaderboard-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }
        
        .leaderboard-table tbody tr:hover {
            background-color: #f8f4fc;
        }
        
        .leaderboard-table tbody tr.top-rank {
            background-color: #faf6ff;
        }
        
        .leaderboard-table tbody tr.rank-1 {
            border-left: 4px solid #ffc107;
        }
        
        .leaderboard-table tbody tr.rank-2 {
            border-left: 4px solid #c0c0c0;
        }
        
        .leaderboard-table tbody tr.rank-3 {
            border-left: 4px solid #cd7f32;
        }
        
        .leaderboard-table td {
            padding: 12px 16px;
        }
        
        .rank-cell {
            text-align: center;
            font-weight: 600;
        }
        
        .rank-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
        }
        
        .rank-badge.gold {
            background-color: #ffc107;
            color: #1a1a1a;
        }
        
        .rank-badge.silver {
            background-color: #c0c0c0;
            color: #1a1a1a;
        }
        
        .rank-badge.bronze {
            background-color: #cd7f32;
            color: white;
        }
        
        .rank-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: #5c2b7a;
            color: white;
            font-weight: 700;
            font-size: 0.85rem;
        }
        
        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .student-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e0e0;
        }
        
        .student-avatar-initials {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #5c2b7a;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .student-name {
            font-weight: 600;
            color: #333;
        }
        
        .course-cell {
            font-size: 0.85rem;
            color: #666;
        }
        
        .score-cell {
            text-align: right;
            font-weight: 700;
        }
        
        .score-badge {
            display: inline-block;
            background-color: #5c2b7a;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .detail-cell {
            text-align: center;
            color: #666;
            font-size: 0.85rem;
        }
        
        .detail-cell small {
            display: block;
        }
        
        @media (max-width: 768px) {
            .leaderboard-table thead {
                font-size: 0.8rem;
            }
            
            .leaderboard-table td {
                padding: 10px 8px;
                font-size: 0.85rem;
            }
            
            .course-col {
                display: none;
            }
            
            .detail-col {
                display: none;
            }
            
            .rank-col { width: 60px; }
            .score-col { width: 80px; }
        }
    </style>
    CSS;
}
