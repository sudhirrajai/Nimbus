<?php
require 'vendor/autoload.php';
try {
    $middleware = new \App\Http\Middleware\SecurityMiddleware();
    echo "Class loaded successfully\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
