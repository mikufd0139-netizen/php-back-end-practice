<?php
/**
 * 用户模块统一入口
 * 
 * POST /php/api/user.php?action=register  注册
 * POST /php/api/user.php?action=login     登录
 * POST /php/api/user.php?action=logout    登出
 * GET  /php/api/user.php?action=profile   获取用户信息
 */

header('Content-Type: application/json; charset=utf-8');

//引入核心文件
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/request.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../config/database.php';

//获取操作类型
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register' :
        handleRegister();
        break;
    case 'login' :
        handleLogin();
        break;
    case 'logout' :
        handleLogout();
        break;
    case 'profile' :
        handleProfile();
        break;
    default :
        Response::error('无效的操作', 400);
}

// 处理注册请求
function handleRegister(): void
{
    Request::allowMethods('POST');
    
    $username = trim(Request::input('username', ''));
    $password = Request::input('password', '');
    $email    = trim(Request::input('email', '')) ?: null;
    $phone    = trim(Request::input('phone', '')) ?: null;

    // 验证
    Validator::check([
        'username' => [$username, 'username'],
        'password' => [$password, 'password'],
        'email'    => [$email, 'email'],
        'phone'    => [$phone, 'phone'],
    ]);

    $pdo = getDB();

    // 检查用户名
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        Response::error('用户名已被注册', 409);
    }

    // 检查邮箱
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            Response::error('邮箱已被注册', 409);
        }
    }

    // 检查手机号
    if ($phone) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            Response::error('手机号已被注册', 409);
        }
    }

    // 插入用户
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $hashedPassword, $email, $phone]);

    Response::success(['user_id' => (int)$pdo->lastInsertId()], '注册成功', 201);
}

// 处理登录请求
function handleLogin(): void
{
    Request::allowMethods('POST');

    $account = trim(Request::input('account', ''));
    $password = Request::input('password', '');

    if (empty($account) || empty($password)) {
        Response::error('账号和密码不能为空', 400);
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("
        SELECT id, username, password, email, phone, status 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
    ");
    $stmt->execute([$account, $account, $account]);
    $user = $stmt->fetch();

    if (!$user) {
        Response::error('账号或密码错误', 401);
    }

    if (!password_verify($password, $user['password'])) {
        Response::error('账号或密码错误', 401);
    }

    if ($user['status'] == 0) {
        Response::error('账号已被禁用', 403);
    }

    Auth::login($user);

    Response::success([
        'user_id'  => (int)$user['id'],
        'username' => $user['username'],
        'email'    => $user['email'],
        'phone'    => $user['phone'],
    ], '登录成功');
}

// 处理登出请求
function handleLogout(): void
{
    Request::allowMethods('POST');

    if (!Auth::check()) {
        Response::error('您尚未登陆', 400);
    }

    Auth::logout();
    Response::success(null, '登出成功');
}

// 处理获取用户信息请求
function handleProfile(): void
{
    Request::allowMethods('GET');
    Auth::requireLogin();

    $pdo = getDB();

    $stmt = $pdo->prepare("
    SELECT id, username, email, phone, status, created_at, updated_at 
        FROM users WHERE id = ?
    ");
    $stmt->execute([Auth::getUserId()]);
    $user = $stmt->fetch();

    if (!$user) {
        Auth::logout();
        Response::error('用户不存在，请重新登录', 401);
    }

    if ($user['status'] == 0) {
        Auth::logout();
        Response::error('账号已被禁用，请联系管理员', 403);
    }

    Response::success([
        'user_id'    => (int)$user['id'],
        'username'   => $user['username'],
        'email'      => $user['email'],
        'phone'      => $user['phone'],
        'status'     => (int)$user['status'],
        'created_at' => $user['created_at'],
        'updated_at' => $user['updated_at']
    ], '获取用户信息成功');
}