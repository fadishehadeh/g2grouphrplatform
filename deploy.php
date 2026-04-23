<?php
/**
 * GitHub Auto-Deployment Webhook Handler
 * 
 * This script receives push notifications from GitHub and automatically
 * deploys the latest code to the server.
 * 
 * Setup:
 * 1. Add GITHUB_WEBHOOK_SECRET to your .env file
 * 2. Configure GitHub webhook to POST to: https://yourdomain.com/deploy.php
 * 3. Set Content type to application/json
 * 4. Select "Push events" only
 */

// Load environment variables
require __DIR__ . '/app/Support/helpers.php';

// Get webhook secret from environment
$secret = env('GITHUB_WEBHOOK_SECRET');
$logFile = __DIR__ . '/storage/deployments.log';

// Helper function to log deployments
function log_deployment($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    
    // Ensure storage directory exists
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Verify webhook signature
function verify_signature($payload, $signature, $secret) {
    if (!$secret) {
        log_deployment('WARNING: GITHUB_WEBHOOK_SECRET not configured, skipping signature verification');
        return true;
    }
    
    $hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($hash, $signature);
}

// Start logging
log_deployment('=== Webhook received ===');

try {
    // Get request body
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    
    // Verify signature
    if (!verify_signature($payload, $signature, $secret)) {
        log_deployment('ERROR: Invalid webhook signature');
        http_response_code(403);
        die('Invalid signature');
    }
    
    // Parse payload
    $data = json_decode($payload, true);
    
    if (!$data) {
        log_deployment('ERROR: Invalid JSON payload');
        http_response_code(400);
        die('Invalid JSON');
    }
    
    // Extract branch name
    $ref = $data['ref'] ?? '';
    $branch = str_replace('refs/heads/', '', $ref);
    
    log_deployment("Branch: {$branch}");
    log_deployment("Repository: " . ($data['repository']['full_name'] ?? 'unknown'));
    log_deployment("Commits: " . count($data['commits'] ?? []));
    
    // Only deploy on push to main branch
    if ($branch !== 'main' && $branch !== 'master') {
        log_deployment("SKIPPED: Not main/master branch");
        http_response_code(200);
        echo "Skipped (branch: {$branch})";
        exit;
    }
    
    // Check if it's a push event
    if (!isset($data['ref'])) {
        log_deployment("SKIPPED: Not a push event");
        http_response_code(200);
        echo "Skipped (not a push event)";
        exit;
    }
    
    // Execute git pull
    log_deployment("Starting deployment...");
    chdir(__DIR__);
    
    $output = [];
    $returnCode = 0;
    exec('git pull origin ' . escapeshellarg($branch) . ' 2>&1', $output, $returnCode);
    
    $outputText = implode("\n", $output);
    log_deployment("Git output:\n{$outputText}");
    
    if ($returnCode === 0) {
        log_deployment("SUCCESS: Deployment completed");
        log_deployment("=== End deployment ===\n");
        
        http_response_code(200);
        echo "Deployment successful";
    } else {
        log_deployment("ERROR: Git pull failed with code {$returnCode}");
        log_deployment("=== End deployment ===\n");
        
        http_response_code(500);
        echo "Deployment failed";
    }
    
} catch (Exception $e) {
    log_deployment("EXCEPTION: " . $e->getMessage());
    log_deployment("=== End deployment ===\n");
    
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
?>
