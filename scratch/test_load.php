<?php
require 'vendor/autoload.php';
try {
    $controller = new \App\Http\Controllers\SupervisorController();
    echo "Class loaded successfully\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
