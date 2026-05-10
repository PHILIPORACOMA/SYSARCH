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
            $sitin_points = floor($row['total_sitins'] / 3);
            $max_sitin_points = 100; 
            $sitin_score = min(($sitin_points / $max_sitin_points) * 100, 100) * 0.50;
            
            // 20% - Tasks completed
            $max_tasks = 100; 
            $tasks_score = min(($row['tasks_completed'] / $max_tasks) * 100, 100) * 0.20;
            
            $total_score = round($hours_score + $sitin_score + $tasks_score, 2);
            
            $leaderboard[] = [
                'rank' => $rank,
                'id' => $row['IdNumber'],
                'name' => $row['FirstName'] . ' ' . $row['LastName'],
                'course' => $row['Course'],
                'photo_path' => $row['PhotoPath'],
                'total_score' => $total_score,
                'hours' => $hours,
                'sitin_points' => $sitin_points,
                'tasks_completed' => $row['tasks_completed']
            ];
            $rank++;
        }
    }
    
    // Sort by total score descending and limit
    usort($leaderboard, function($a, $b) {
        return $b['total_score'] <=> $a['total_score'];
    });
    
    // Re-assign ranks after sorting
    foreach ($leaderboard as $index => &$entry) {
        $entry['rank'] = $index + 1;
    }
    
    return array_slice($leaderboard, 0, $limit);
}

function getStudentLeaderboardRank($conn, $student_id) {
    $leaderboard = getLeaderboardData($conn, 1000);
    foreach ($leaderboard as $index => $student) {
        if ($student['id'] === $student_id) {
            return [
                'rank' => $student['rank'],
                'total_rank' => count($leaderboard),
                'score' => $student['total_score']
            ];
        }
    }
    return null;
}

function getLeaderboardStyles() {
    return <<<CSS
    <style>
        .leaderboard-container {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.05);
            margin-bottom: 20px;
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.9rem;
        }

        .leaderboard-table thead {
            background: rgba(92, 43, 122, 0.03);
            color: #5c2b7a;
        }

        .leaderboard-table thead th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .leaderboard-table tbody tr { transition: all 0.2s ease; }
        .leaderboard-table tbody tr:hover { background: rgba(255, 255, 255, 0.8); }

        .leaderboard-table td {
            padding: 12px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.02);
            vertical-align: middle;
        }

        /* Column Widths */
        .rank-col { width: 90px; text-align: center !important; }
        .name-col { min-width: 200px; }
        .course-col { width: 150px; }
        .score-col { width: 120px; text-align: right !important; }
        .detail-col { width: 80px; text-align: center !important; color: #64748b; font-size: 0.75rem; font-weight: 600; }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px; height: 34px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.85rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .rank-badge.gold { background: linear-gradient(135deg, #ffd700, #ffc107); color: #000; }
        .rank-badge.silver { background: linear-gradient(135deg, #e2e8f0, #cbd5e1); color: #475569; }
        .rank-badge.bronze { background: linear-gradient(135deg, #fed7aa, #fdba74); color: #9a3412; }
        .rank-number { background: #f3eaf9; color: #5c2b7a; border-radius: 10px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }

        .student-info { display: flex; align-items: center; gap: 12px; }
        
        .student-avatar, .student-avatar-initials {
            width: 38px; height: 38px; border-radius: 12px;
            object-fit: cover; box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 2px solid white; flex-shrink: 0;
        }
        
        .student-avatar-initials {
            background: linear-gradient(135deg, #5c2b7a 0%, #7b3da3 100%);
            color: white; display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.9rem;
        }
        
        .student-name { font-weight: 700; color: #2d3436; font-size: 0.9rem; }
        .course-text { font-size: 0.8rem; color: #64748b; font-weight: 600; }
        
        .score-badge {
            background: #5c2b7a; color: white;
            padding: 6px 14px; border-radius: 10px;
            font-weight: 800; font-size: 0.85rem;
            box-shadow: 0 4px 12px rgba(92, 43, 122, 0.15);
        }

        @media (max-width: 768px) {
            .course-col, .detail-col { display: none; }
            .leaderboard-table td, .leaderboard-table th { padding: 10px 15px; }
            .name-col { min-width: 150px; }
        }
    </style>
CSS;
}

function displayLeaderboard($leaderboard, $show_details = false, $class_name = '') {
    static $styles_printed = false;
    if (!$styles_printed) {
        echo getLeaderboardStyles();
        $styles_printed = true;
    }
    
    if (empty($leaderboard)) {
        echo '<div class="text-center py-5 text-muted small fw-600"><i class="fa-solid fa-ranking-star opacity-25 me-2"></i>No data available.</div>';
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
                    <th class="score-col">Total Score</th>
                    <?php if ($show_details): ?>
                        <th class="detail-col">Hours</th>
                        <th class="detail-col">Points</th>
                        <th class="detail-col">Tasks</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaderboard as $entry): ?>
                    <tr>
                        <td class="rank-col">
                            <?php if ($entry['rank'] == 1): ?>
                                <span class="rank-badge gold"><i class="fa fa-crown"></i></span>
                            <?php elseif ($entry['rank'] == 2): ?>
                                <span class="rank-badge silver"><i class="fa fa-medal"></i></span>
                            <?php elseif ($entry['rank'] == 3): ?>
                                <span class="rank-badge bronze"><i class="fa fa-medal"></i></span>
                            <?php else: ?>
                                <span class="rank-number"><?php echo $entry['rank']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="name-col">
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
                        <td class="course-col"><span class="course-text"><?php echo htmlspecialchars($entry['course']); ?></span></td>
                        <td class="score-col">
                            <span class="score-badge"><?php echo number_format($entry['total_score'], 2); ?></span>
                        </td>
                        <?php if ($show_details): ?>
                            <td class="detail-col"><?php echo number_format($entry['hours'], 1); ?>h</td>
                            <td class="detail-col"><?php echo $entry['sitin_points']; ?></td>
                            <td class="detail-col"><?php echo $entry['tasks_completed']; ?></td>
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
    
    $rank_info = getStudentLeaderboardRank($conn, $student_id);
    $total_students = $conn->query("SELECT COUNT(*) as c FROM students_info WHERE is_admin=0")->fetch_assoc()['c'];
    
    $hours = $stats['total_minutes'] ? round($stats['total_minutes'] / 60, 1) : 0;
    $sitins = $stats['total_sitins'] ? (int)$stats['total_sitins'] : 0;
    $tasks_completed = $stats['tasks_completed'] ? (int)$stats['tasks_completed'] : 0;
    $tasks_incomplete = $stats['tasks_incomplete'] ? (int)$stats['tasks_incomplete'] : 0;
    $task_completion_rate = ($sitins > 0) ? round(($tasks_completed / $sitins) * 100) : 0;
    $rank = $rank_info['rank'] ?? 999;
    $score = $rank_info['score'] ?? 0;
    
    if ($hours < 10) {
        $recommendations[] = [
            'type' => 'hours', 'icon' => 'fa-clock', 'title' => '⏱️ Increase Lab Hours',
            'message' => 'You have ' . $hours . ' hours. Try to reach at least 20 hours for better lab experience.',
            'priority' => 'high', 'color' => '#dc3545'
        ];
    }
    
    if ($sitins < 12) {
        $recommendations[] = [
            'type' => 'sitins', 'icon' => 'fa-chair', 'title' => '🪑 More Sit-in Sessions',
            'message' => 'You\'ve completed ' . $sitins . ' sit-ins. Target 30 sit-ins for maximum leaderboard points.',
            'priority' => 'medium', 'color' => '#ff6b6b'
        ];
    }
    
    if ($task_completion_rate < 50 && $sitins > 0) {
        $recommendations[] = [
            'type' => 'tasks', 'icon' => 'fa-check-circle', 'title' => '✓ Focus on Tasks',
            'message' => 'Only ' . $task_completion_rate . '% of your tasks are completed. Try to finish all assigned tasks.',
            'priority' => 'high', 'color' => '#fd7e14'
        ];
    }
    
    if ($rank <= 3) {
        $recommendations[] = [
            'type' => 'rank', 'icon' => 'fa-crown', 'title' => '👑 Top Performer!',
            'message' => 'You\'re ranked #' . $rank . ' on the leaderboard! Keep it up!',
            'priority' => 'low', 'color' => '#ffc107'
        ];
    }
    
    return $recommendations;
}
?>