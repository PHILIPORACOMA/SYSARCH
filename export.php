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
$filename = "sitin_report_" . date('Y-m-d') . "." . ($type === 'xls' ? 'xls' : ($type === 'pdf' ? 'html' : 'csv'));

// Fetch Data
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
        'Time Out' => $r['TimeOut'] ?: 'LIVE',
        'Status' => $r['Status']
    ];
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
    echo "<html><head><title>Downloading Report...</title>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'></script>
    <style>
        body { font-family: sans-serif; visibility: hidden; }
        #report-content { visibility: visible; padding: 40px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #5c2b7a; color: white; }
        .header { text-align: center; margin-bottom: 20px; }
    </style></head><body>";
    echo "<div id='report-content'>";
    echo "<div class='header'>
            <img src='UCLogo-removebg-preview.png' style='width:60px;'><br>
            <h2 style='margin-bottom:5px;'>Sit-in Usage Report</h2>
            <p style='font-size:12px; color:#666;'>University of Cebu - College of Computer Studies<br>Generated on " . date('Y-m-d H:i') . "</p>
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
    }
    echo "</tbody></table></div>";
    echo "<script>
        window.onload = function() {
            const element = document.getElementById('report-content');
            const opt = {
                margin: 0.5,
                filename: '$filename',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save().then(() => {
                setTimeout(() => { window.close(); }, 500);
            });
        }
    </script></body></html>";
    exit();
}
?>