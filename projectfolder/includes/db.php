<?php
require_once __DIR__ . '/../config.php';

function getDbConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            $conn->set_charset("utf8");
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Sorry, we're experiencing technical difficulties. Please try again later.");
        }
    }
    
    return $conn;
}

// Helper function to safely prepare SQL statements
function prepareStatement($sql) {
    $conn = getDbConnection();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        die("Sorry, we're experiencing technical difficulties. Please try again later.");
    }
    
    return $stmt;
}

// Helper function to escape strings
function escapeString($str) {
    $conn = getDbConnection();
    return $conn->real_escape_string($str);
} 