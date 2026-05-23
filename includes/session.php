<?php
/**
 * Session Management System
 * Secure session handling with regeneration and proper configuration
 */

// ============================================
// SESSION SECURITY CONFIGURATION
// ============================================

// Set secure session cookie parameters BEFORE session start
if (session_status() === PHP_SESSION_NONE) {
    
    // Get current domain
    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               ($_SERVER['SERVER_PORT'] ?? 0) == 443;
    
    // Set session cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,                    // Session cookie expires when browser closes
        'path' => '/',                      // Available throughout the domain
        'domain' => '',                     // Current domain only
        'secure' => $isHttps,               // Only send over HTTPS if enabled
        'httponly' => true,                 // Prevent JavaScript access
        'samesite' => 'Strict'              // Prevent CSRF attacks
    ]);
    
    // Set session name (custom name for security)
    session_name('HIS_SID');  // HIS = Hotel Inventory System
    
    // Start the session
    session_start();
}

// ============================================
// SESSION REGENERATION (Prevent Session Fixation)
// ============================================

// Check if we need to regenerate session ID
if (!isset($_SESSION['last_regeneration'])) {
    // New session - regenerate immediately
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
    $_SESSION['created_at'] = time();
} else {
    // Regenerate every 30 minutes for security
    $regeneration_interval = 1800; // 30 minutes in seconds
    
    if (time() - $_SESSION['last_regeneration'] > $regeneration_interval) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// ============================================
// SESSION VALIDATION FUNCTIONS
// ============================================

/**
 * Check if session is valid (not expired)
 */
function isSessionValid() {
    // Check if session has inactivity timeout (30 minutes)
    $inactivity_timeout = 1800; // 30 minutes
    
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $inactivity_timeout) {
            // Session expired due to inactivity
            destroySession();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Destroy session completely
 */
function destroySession() {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Regenerate session ID and preserve data
 */
function regenerateSession() {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
    return true;
}

/**
 * Set session variable with validation
 */
function setSession($key, $value) {
    // Validate key
    if (empty($key) || !is_string($key)) {
        return false;
    }
    
    // Sanitize value if needed
    if (is_string($value)) {
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    $_SESSION[$key] = $value;
    return true;
}

/**
 * Get session variable with default
 */
function getSession($key, $default = null) {
    return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
}

/**
 * Check if session variable exists
 */
function hasSession($key) {
    return isset($_SESSION[$key]);
}

/**
 * Remove session variable
 */
function removeSession($key) {
    if (isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
        return true;
    }
    return false;
}

/**
 * Get all session data (for debugging - use carefully)
 */
function getAllSession() {
    return $_SESSION;
}

/**
 * Clear specific session data for password reset flow
 */
function clearResetSession() {
    removeSession('reset_user_id');
    removeSession('reset_email');
    removeSession('reset_fullname');
    removeSession('reset_phone');
    removeSession('reset_token');
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return getSession('role', null);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return getSession('user_id', 0);
}

/**
 * Get current user fullname
 */
function getCurrentUserFullname() {
    return getSession('fullname', 'Guest');
}

// ============================================
// ADDITIONAL SECURITY HEADERS
// ============================================

// Set security headers if not already sent
if (!headers_sent()) {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ============================================
// SESSION GARBAGE COLLECTION (Optional)
// ============================================

// Randomly clean up expired sessions (1% chance)
if (mt_rand(1, 100) <= 1) {
    // Delete expired sessions from database if using database sessions
    // This is a placeholder for future database session handling
    // For now, PHP handles it automatically
}

// ============================================
// INITIALIZE DEFAULT SESSION VARIABLES (if needed)
// ============================================

// Set last activity if not set
if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

// Set user agent hash for additional security (optional)
if (!isset($_SESSION['user_agent']) && isset($_SERVER['HTTP_USER_AGENT'])) {
    $_SESSION['user_agent'] = md5($_SERVER['HTTP_USER_AGENT']);
}

/**
 * Verify user agent matches (prevents session hijacking)
 */
function verifyUserAgent() {
    if (isset($_SESSION['user_agent']) && isset($_SERVER['HTTP_USER_AGENT'])) {
        $current_agent = md5($_SERVER['HTTP_USER_AGENT']);
        if ($_SESSION['user_agent'] !== $current_agent) {
            destroySession();
            return false;
        }
    }
    return true;
}

/**
 * Verify IP address (optional - use with caution for mobile users)
 * Uncomment if you want IP verification
 */
/*
function verifyIpAddress() {
    $allowed_ips = ['127.0.0.1', '::1']; // Add your IPs
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!in_array($client_ip, $allowed_ips)) {
        // For production, you might want to check if IP changed
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $client_ip) {
            destroySession();
            return false;
        }
        $_SESSION['ip_address'] = $client_ip;
    }
    return true;
}
*/

// Run validation on every request
if (isLoggedIn()) {
    // Check session validity
    if (!isSessionValid()) {
        destroySession();
        if (!headers_sent()) {
            header('Location: /hotel_inventory_system/auth/login.php');
            exit();
        }
    }
    
    // Verify user agent
    if (!verifyUserAgent()) {
        if (!headers_sent()) {
            header('Location: /hotel_inventory_system/auth/login.php');
            exit();
        }
    }
}
?>