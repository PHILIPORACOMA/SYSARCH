<?php
/**
 * Database Initialization Script for Photo Column
 * Run this once to add PhotoPath column to students_info table
 */

include "db.php";

try {
    // Check if PhotoPath column exists
    $check_col = $conn->query("SHOW COLUMNS FROM students_info LIKE 'PhotoPath'");
    
    if ($check_col && $check_col->num_rows === 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE students_info ADD COLUMN PhotoPath VARCHAR(255) NULL AFTER Password";
        
        if ($conn->query($sql)) {
            echo "✓ PhotoPath column successfully added to students_info table!";
        } else {
            echo "✗ Error adding PhotoPath column: " . $conn->error;
        }
    } else {
        echo "✓ PhotoPath column already exists.";
    }
    
    // Create uploads directory
    if (!is_dir('uploads/student_photos')) {
        mkdir('uploads/student_photos', 0755, true);
        echo "<br>✓ Uploads directory created.";
    } else {
        echo "<br>✓ Uploads directory already exists.";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
