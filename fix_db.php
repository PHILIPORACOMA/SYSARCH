<?php
include 'db.php';

$sql = "CREATE TABLE IF NOT EXISTS disabled_pcs (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    LabName VARCHAR(100) NOT NULL,
    PCNumber INT NOT NULL,
    Reason VARCHAR(255),
    DateDisabled TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (LabName, PCNumber)
)";

if ($conn->query($sql) === TRUE) {
    echo "<h1>Success!</h1>";
    echo "<p>Table <strong>disabled_pcs</strong> created successfully.</p>";
    echo "<p><a href='admin_dashboard.php'>Go back to Dashboard</a></p>";
} else {
    echo "<h1>Error!</h1>";
    echo "<p>Error creating table: " . $conn->error . "</p>";
}
?>