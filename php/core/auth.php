<?php
/**
 * 认证处理类
 * 管理用户登录状态
 */
class Auth
{
    /**
     * 初始化 Session
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * 设置登录状态
     * @param array $user 用户信息
     */
    public static function login(array $user): void
    {
        self::init();
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['logged_in']  = true;
        $_SESSION['login_time'] = time();
    }

    /**
     * 检查是否已登录
     * @return bool
     */
    public static function check(): bool
    {
        self::init();
        return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * 要求登录（未登录则终止请求）
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            Response::error('请先登录', 401);
        }
    }

    /**
     * 获取当前登录用户 ID
     * @return int|null
     */
    public static function getUserId(): ?int
    {
        self::init();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * 获取当前登录用户名
     * @return string|null
     */
    public static function getUsername(): ?string
    {
        self::init();
        return $_SESSION['username'] ?? null;
    }

    /**
     * 登出
     */
    public static function logout(): void
    {
        self::init();
        
        // 清空 Session 数据
        $_SESSION = [];
        
        // 删除 Session Cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // 销毁 Session
        session_destroy();
    }
}