<?php
/**
 * 数据库配置文件
 */

// 数据库连接参数
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'shop_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * 获取 PDO 连接实例（单例模式）
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // 加载 Response 类（如果尚未加载）
            if (!class_exists('Response')) {
                require_once __DIR__ . '/../core/Response.php';
            }
            Response::error('数据库连接失败', 500);
        }
    }
    
    return $pdo;
}