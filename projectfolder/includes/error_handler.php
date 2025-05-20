<?php
require_once __DIR__ . '/../config.php';

class ErrorHandler {
    private static $log_file = 'error.log';
    
    public static function handleError($errno, $errstr, $errfile, $errline) {
        $error_message = date('Y-m-d H:i:s') . " [$errno] $errstr in $errfile on line $errline\n";
        
        // Log error
        error_log($error_message, 3, UPLOAD_DIR . self::$log_file);
        
        if (ini_get('display_errors')) {
            // Development environment: show detailed error
            echo "<div class='alert alert-danger'>";
            echo "<h4>An error occurred:</h4>";
            echo "<p>$errstr</p>";
            echo "<p>File: $errfile</p>";
            echo "<p>Line: $errline</p>";
            echo "</div>";
        } else {
            // Production environment: show generic error
            self::showErrorPage('An unexpected error occurred. Please try again later.');
        }
        
        return true;
    }
    
    public static function handleException($exception) {
        $error_message = date('Y-m-d H:i:s') . " [Exception] " . 
                        $exception->getMessage() . " in " . 
                        $exception->getFile() . " on line " . 
                        $exception->getLine() . "\n" . 
                        $exception->getTraceAsString() . "\n";
        
        // Log exception
        error_log($error_message, 3, UPLOAD_DIR . self::$log_file);
        
        if (ini_get('display_errors')) {
            // Development environment: show detailed error
            echo "<div class='alert alert-danger'>";
            echo "<h4>An exception occurred:</h4>";
            echo "<p>" . $exception->getMessage() . "</p>";
            echo "<p>File: " . $exception->getFile() . "</p>";
            echo "<p>Line: " . $exception->getLine() . "</p>";
            echo "<pre>" . $exception->getTraceAsString() . "</pre>";
            echo "</div>";
        } else {
            // Production environment: show generic error
            self::showErrorPage('An unexpected error occurred. Please try again later.');
        }
    }
    
    public static function handle404() {
        http_response_code(404);
        include __DIR__ . '/../404.php';
        exit();
    }
    
    private static function showErrorPage($message) {
        include __DIR__ . '/../includes/header.php';
        echo "<div class='container mt-5'>";
        echo "<div class='alert alert-danger text-center'>";
        echo "<h3>Error</h3>";
        echo "<p>$message</p>";
        echo "<a href='/' class='btn btn-primary'>Go Home</a>";
        echo "</div>";
        echo "</div>";
        include __DIR__ . '/../includes/footer.php';
    }
}

// Set error and exception handlers
set_error_handler([ErrorHandler::class, 'handleError']);
set_exception_handler([ErrorHandler::class, 'handleException']);
?> 