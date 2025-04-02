<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth {
    private $conn;
    private $key = "kunci_rahasia_anda_ganti_ini"; // Ganti dengan kunci rahasia yang aman
    private $algorithm = 'HS256';
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function generateToken($userId, $email) {
        $issuedAt = time();
        $expirationTime = $issuedAt + 3600; // Token berlaku selama 1 jam
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'user_id' => $userId,
            'email' => $email
        ];
        
        return JWT::encode($payload, $this->key, $this->algorithm);
    }
    
    // Fungsi untuk mendapatkan semua header pada berbagai konfigurasi server
    private function getAllHeaders() {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        
        // Cek Authorization header khusus
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . ($_SERVER['PHP_AUTH_PW'] ?? ''));
        }
        
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers['Authorization'] = $requestHeaders['Authorization'];
            }
        }
        
        return $headers;
    }
    
    public function validateToken() {
        $headers = $this->getAllHeaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        // Jika header Authorization tidak ditemukan, coba cek langsung dari $_SERVER
        if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        
        // Untuk debug, log semua headers yang diterima
        error_log("All headers: " . print_r($headers, true));
        error_log("Authorization header: " . $authHeader);
        
        // Periksa apakah header Authorization ada
        if (!$authHeader) {
            return [
                'success' => false,
                'message' => 'Token diperlukan'
            ];
        }
        
        // Periksa apakah format header benar (Bearer token)
        if (!preg_match('/^Bearer\s+(.*)$/', $authHeader, $matches)) {
            return [
                'success' => false,
                'message' => 'Format token tidak valid'
            ];
        }
        
        $token = $matches[1];
        
        try {
            $decoded = JWT::decode($token, new Key($this->key, $this->algorithm));
            return [
                'success' => true,
                'data' => $decoded
            ];
        } catch (\Exception $e) {
            error_log("JWT Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Token tidak valid atau kadaluarsa'
            ];
        }
    }
    
    public function register($email, $password) {
        // Validasi input
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Email dan password diperlukan'
            ];
        }
        
        // Cek apakah email sudah digunakan
        $checkSql = "SELECT * FROM users WHERE email = ?";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->execute([$email]);
        
        if ($checkStmt->rowCount() > 0) {
            return [
                'success' => false,
                'message' => 'Email sudah digunakan'
            ];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Simpan ke database
        $sql = "INSERT INTO users (email, password) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt->execute([$email, $hashedPassword])) {
            return [
                'success' => true,
                'message' => 'Registrasi berhasil',
                'user_id' => $this->conn->lastInsertId()
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Registrasi gagal'
        ];
    }
    
    public function login($email, $password) {
        // Validasi input
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Email dan password diperlukan'
            ];
        }
        
        // Cek apakah user ada di database
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() == 0) {
            return [
                'success' => false,
                'message' => 'Email atau password salah'
            ];
        }
        
        $user = $stmt->fetch();
        
        // Verifikasi password
        if (!password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Email atau password salah'
            ];
        }
        
        // Generate token
        $token = $this->generateToken($user['id'], $user['email']);
        
        return [
            'success' => true,
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email']
            ]
        ];
    }
}