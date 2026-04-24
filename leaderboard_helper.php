<?php
/**
 * Leaderboard Helper Functions
 * Scoring: 30% Hours, 50% Reservations, 20% Tasks
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
            COALESCE(SUM(CASE WHEN sit.Type='Reservation' THEN 1 ELSE 0 END), 0) as total_reservations,
            COALESCE(SUM(CASE WHEN sit.Type='Sit-in' OR sit.Type IS NULL THEN 1 ELSE 0 END), 0) as total_sitins
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
            
            // 50% - Reservations (1 point per reservation, normalize to 100 max)
            $max_reservations = 50; // 50 reservations as max reference
            $reservation_score = min(($row['total_reservations'] / $max_reservations) * 100, 100) * 0.50;
            
            // 20% - Sit-in tasks completed (1 point per sit-in session, normalize to 100 max)
            $max_tasks = 100; // 100 sit-ins as max reference
            $tasks_score = min(($row['total_sitins'] / $max_tasks) * 100, 100) * 0.20;
            
            $total_score = round($hours_score + $reservation_score + $tasks_score, 2);
            
            $leaderboard[] = [
                'rank' => $rank,
                'id' => $row['IdNumber'],
                'name' => $row['FirstName'] . ' ' . $row['LastName'],
                'course' => $row['Course'],
                'photo_path' => $row['PhotoPath'],
                'total_score' => $total_score,
                'hours_score' => round($hours_score, 2),
                'reservation_score' => round($reservation_score, 2),
                'tasks_score' => round($tasks_score, 2),
                'hours' => $hours,
                'reservations' => $row['total_reservations'],
                'sitins' => $row['total_sitins']
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
                        <th class="detail-col">Reservations</th>
                        <th class="detail-col">Tasks</th>
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
                                <small><?php echo $entry['reservations']; ?></small>
                            </td>
                            <td class="detail-cell">
                                <small><?php echo $entry['sitins']; ?></small>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
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
        .course-col { width: 140px; }
        .score-col { width: 100px; text-align: right; }
        .detail-col { width: 90px; text-align: center; }
        
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
?>
