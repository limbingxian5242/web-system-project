<?php
/**
 * Security helper functions to prevent XSS and SQL injection
 */

/**
 * Sanitize output to prevent XSS attacks
 * @param string $data The data to sanitize
 * @return string Sanitized data
 */
function h($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize data for database queries (use with prepared statements)
 * @param mixed $data The data to sanitize
 * @return mixed Sanitized data
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

/**
 * Set secure headers to protect against common web vulnerabilities
 */
function set_security_headers() {
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Enable XSS protection in browsers
    header('X-XSS-Protection: 1; mode=block');
    
    // Enforce HTTPS
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    
    // Restrict referrer information
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Disable caching of sensitive pages
    if (basename($_SERVER['PHP_SELF']) === 'login.php' || 
        basename($_SERVER['PHP_SELF']) === 'register.php' ||
        basename($_SERVER['PHP_SELF']) === 'checkout.php' ||
        basename($_SERVER['PHP_SELF']) === 'profile.php') {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

/**
 * Validate that input matches expected data type/pattern
 * @param string $data The data to validate
 * @param string $type The expected type (email, name, postal, etc.)
 * @return bool Whether data is valid
 */
function validate_input($data, $type) {
    switch($type) {
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL) !== false;
        case 'name':
            return preg_match('/^[a-zA-Z \'\-\.]+$/', $data);
        case 'postal':
            return preg_match('/^\d{6}$/', $data);
        case 'card_number':
            $clean = preg_replace('/\D/', '', $data);
            return strlen($clean) >= 13 && strlen($clean) <= 19;
        case 'card_cvv':
            return preg_match('/^\d{3,4}$/', $data);
        case 'card_expiry':
            return preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $data);
        default:
            return true;
    }
}

/**
 * Generate a CSRF token and store it in the session
 * @param string $form_name The name of the form
 * @return string CSRF token
 */
function generate_csrf_token($form_name) {
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$form_name] = [
        'token' => $token,
        'expiry' => time() + 3600 // Token expires after 1 hour
    ];
    
    return $token;
}

/**
 * Verify a CSRF token
 * @param string $form_name The name of the form
 * @param string $token The token to verify
 * @return bool Whether token is valid
 */
function verify_csrf_token($form_name, $token) {
    if (!isset($_SESSION['csrf_tokens'][$form_name])) {
        return false;
    }
    
    $stored = $_SESSION['csrf_tokens'][$form_name];
    
    // Check if token has expired
    if (time() > $stored['expiry']) {
        unset($_SESSION['csrf_tokens'][$form_name]);
        return false;
    }
    
    // Verify token
    if (hash_equals($stored['token'], $token)) {
        // Remove token after use for one-time use
        unset($_SESSION['csrf_tokens'][$form_name]);
        return true;
    }
    
    return false;
}

/**
 * Prepare a PDO statement with bound parameters to prevent SQL injection
 * @param PDO $pdo The PDO connection
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind
 * @return PDOStatement The prepared statement
 */
function prepare_statement($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        // Determine parameter type
        $type = PDO::PARAM_STR;
        if (is_int($value)) {
            $type = PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            $type = PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            $type = PDO::PARAM_NULL;
        }
        
        // Bind parameter
        if (is_string($key) && $key[0] === ':') {
            $stmt->bindValue($key, $value, $type);
        } else {
            $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $type);
        }
    }
    
    return $stmt;
}

/**
 * Sanitize data for database table and column names (never use user input for these!)
 * @param string $identifier The identifier to sanitize
 * @return string Sanitized identifier
 */
function sanitize_identifier($identifier) {
    // Only allow alphanumeric characters and underscores
    $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    
    // Ensure it doesn't start with a number
    if (preg_match('/^[0-9]/', $sanitized)) {
        $sanitized = '_' . $sanitized;
    }
    
    return $sanitized;
}

/**
 * Create a Content Security Policy header to prevent XSS attacks
 * @param bool $report_only Whether to only report violations (not enforce)
 */
function set_content_security_policy($report_only = false) {
    $policy = [
        "default-src 'self'",
        "script-src 'self' https://cdn.jsdelivr.net",
        "style-src 'self' https://cdn.jsdelivr.net",
        "img-src 'self' data: blob:",
        "font-src 'self' https://cdn.jsdelivr.net",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "form-action 'self'",
        "base-uri 'self'"
    ];
    
    $header_name = $report_only ? 
        'Content-Security-Policy-Report-Only' : 
        'Content-Security-Policy';
    
    header("$header_name: " . implode('; ', $policy));
}

// Add Content Security Policy by default
set_content_security_policy(false);

// Set security headers for all pages
set_security_headers(); 