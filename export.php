<?php
session_start();
include "db.php";

// Check if admin
$chk = $conn->prepare("SELECT is_admin FROM students_info WHERE IdNumber = ?");
$chk->bind_param("s", $_SESSION['user_id']);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
if (!$row || !$row['is_admin']) { die("Unauthorized access."); }

$type = $_GET['type'] ?? 'csv';
$source = $_GET['source'] ?? 'history';
$filename = ($source === 'students' ? "student_list_" : "sitin_report_") . date('Y-m-d') . "." . ($type === 'xls' ? 'xls' : ($type === 'pdf' ? 'pdf' : 'csv'));

// Fetch Data
if ($source === 'students') {
    $res = $conn->query("SELECT si.IdNumber, si.FirstName, si.LastName, si.MiddleName, si.Email, si.Course, si.CourseLevel, 
                                COUNT(s.SessionID) as TotalSessions 
                         FROM students_info si 
                         LEFT JOIN sit_in_sessions s ON s.StudentID = si.IdNumber AND (s.Status = 'Completed' OR s.Status = 'Active')
                         WHERE si.is_admin = 0 
                         GROUP BY si.IdNumber 
                         ORDER BY si.LastName ASC");
    $data = [];
    while($r = $res->fetch_assoc()) {
        $data[] = [
            'ID Number' => $r['IdNumber'],
            'Name' => $r['LastName'] . ', ' . $r['FirstName'] . ' ' . $r['MiddleName'],
            'Email' => $r['Email'],
            'Course' => $r['Course'],
            'Year Level' => $r['CourseLevel'],
            'Total Sit-ins' => $r['TotalSessions']
        ];
    }
    $report_title = "Student Registry Report";
} else {
    $res = $conn->query("SELECT s.*, st.FirstName, st.LastName 
                        FROM sit_in_sessions s 
                        JOIN students_info st ON s.StudentID = st.IdNumber 
                        ORDER BY s.SessionDate DESC, s.TimeIn DESC");

    $data = [];
    while($r = $res->fetch_assoc()) {
        $data[] = [
            'Date' => $r['SessionDate'],
            'Student ID' => $r['StudentID'],
            'Name' => $r['FirstName'] . ' ' . $r['LastName'],
            'Lab' => $r['Lab'],
            'PC' => $r['PCNumber'] ?: 'Any',
            'Purpose' => $r['Purpose'],
            'Time In' => $r['TimeIn'],
            'Time Out' => $r['TimeOut'] ? $r['TimeOut'] : (strtolower($r['Status'])==='active' ? 'LIVE' : date('H:i', strtotime($r['TimeIn'] . ' + 3 hours'))),
            'Status' => $r['Status']
        ];
    }
    $report_title = "Sit-in Usage Report";
}

if ($type === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) fputcsv($output, $row);
    }
    fclose($output);
    exit();

} elseif ($type === 'xls') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo "<h3>$report_title</h3>";
    echo "<table border='1'><tr>";
    if (!empty($data)) {
        foreach(array_keys($data[0]) as $h) echo "<th style='background-color:#5c2b7a; color:white;'>$h</th>";
        echo "</tr>";
        foreach ($data as $row) {
            echo "<tr>";
            foreach($row as $v) echo "<td>$v</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    exit();

} elseif ($type === 'pdf') {
    // Instant PDF Download using Client-side Library
    echo "<html><head><title>Generating Report...</title>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: white; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 9999; }
        #report-content { background: white; width: 1000px; margin: 0 auto; padding: 40px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
        th, td { border: 1px solid #eee; padding: 10px 8px; text-align: left; }
        th { background: #5c2b7a; color: white; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #5c2b7a; padding-bottom: 20px; }
        .footer-info { margin-top: 30px; font-size: 10px; color: #888; text-align: center; }
    </style></head><body>";
    
    echo "<div class='loading-overlay' id='loader'>
            <div style='border: 4px solid #f3f3f3; border-top: 4px solid #5c2b7a; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite;'></div>
            <p style='margin-top:15px; font-weight:bold; color:#5c2b7a;'>Generating your PDF report...</p>
            <p style='font-size:12px; color:#666;'>Please wait a moment.</p>
          </div>
          <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>";

    echo "<div id='report-content'>";
    echo "<div class='header'>
            <img src='UCLogo-removebg-preview.png' style='width:70px; height:auto;' onerror=\"this.style.display='none'\">
            <h1 style='margin:10px 0 5px; color:#333; font-size:24px;'>$report_title</h1>
            <p style='font-size:13px; color:#555; margin:0;'>University of Cebu - College of Computer Studies</p>
            <p style='font-size:11px; color:#888; margin:5px 0 0;'>Generated on " . date('F d, Y \a\t h:i A') . "</p>
          </div>";
    echo "<table><thead><tr>";
    if (!empty($data)) {
        foreach(array_keys($data[0]) as $h) echo "<th>$h</th>";
        echo "</tr></thead><tbody>";
        foreach ($data as $row) {
            echo "<tr>";
            foreach($row as $v) echo "<td>$v</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='10' style='text-align:center; padding:40px;'>No data available for this report.</td></tr>";
    }
    echo "</tbody></table>
          <div class='footer-info'>
            This is a computer-generated document from the CCS Sit-in Monitoring System.
          </div>
    </div>";

    echo "<script>
        window.onload = function() {
            // Small delay to ensure styles and images are fully processed by the browser
            setTimeout(() => {
                const element = document.getElementById('report-content');
                const opt = {
                    margin: [0.5, 0.5],
                    filename: '$filename',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, logging: false },
                    jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
                };
                
                html2pdf().set(opt).from(element).save().then(() => {
                    document.getElementById('loader').innerHTML = '<p style=\"color:#198754; font-weight:bold;\">✅ Download Started!</p>';
                    setTimeout(() => { window.close(); }, 1500);
                }).catch(err => {
                    console.error('PDF Error:', err);
                    document.getElementById('loader').innerHTML = '<p style=\"color:#dc3545;\">Error generating PDF. Please try again.</p>';
                });
            }, 800);
        }
    </script></body></html>";
    exit();
}
?>