<?php
/**
 * 管理员模块接口
 * 
 * GET  /php/api/admin.php?action=users       获取用户列表
 * GET  /php/api/admin.php?action=user_count  获取用户统计
 * POST /php/api/admin.php?action=disable     禁用用户
 * POST /php/api/admin.php?action=enable      启用用户
 */

header('Content-Type: application/json; charset=utf-8');

// 引入核心文件
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/request.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../config/database.php';

// 获取操作类型
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'users' :
        handleGetUsers();
        break;
    case 'user_count' :
        handleGetUserCount();
        break;
    case 'disable' :
        handleDisableUser();
        break;
    case 'enable' :
        handleEnableUser();
        break;
    default :
        Response::error('无效的操作', 400);
}

/**
 * 验证管理员权限
 * 必须登录且 role = 1
 */
function requireAdmin(): void
{
    // 先检查是否登陆
    Auth::requireLogin();

    // 再检查是否是管理员
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([Auth::getUserId()]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] != 1) {
        Response::error('权限不足，需要管理员身份', 403);
    }
}

/**
 * 获取用户列表
 * GET /php/api/admin.php?action=users
 * 可选参数: page (页码), limit (每页数量)
 */
function handleGetUsers(): void
{
    Request::allowMethods('GET');
    requireAdmin();

    // 分页参数
    $page = max(1,(int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
    $offset = ($page -1) * $limit;

    $pdo = getDB();

    // 获取总数
    $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM users");
    $total = (int)$countStmt->fetch()['total'];

    // 获取用户列表
    $stmt = $pdo->prepare("
        SELECT id, username, email, phone, status, role, created_at, updated_at 
        FROM users 
        ORDER BY id DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();

    //格式化数据
    $formattedUsers = array_map(function($user){
        return [
            'id'          => (int)$user['id'],
            'username'    => $user['username'],
            'email'       => $user['email'],
            'phone'       => $user['phone'],
            'status'      => (int)$user['status'],
            'role'        => (int)$user['role'],
            'role_name'   => $user['role'] == 1 ? '管理员' : '普通用户',
            'status_name' => $user['status'] == 1 ? '正常' : '禁用',
            'created_at'  => $user['created_at'],
            'updated_at'  => $user['updated_at']
        ];
    }, $users);

    Response::success([
        'users'       => $formattedUsers,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => ceil($total / $limit)
    ], '获取用户列表成功');
}

/**
 * 获取用户统计
 * GET /php/api/admin.php?action=user_count
 */
function handleGetUserCount(): void
{
    Request::allowMethods('GET');
    requireAdmin();

    $pdo = getDB();

    //总用户数量
    $countStmt = $pdo->query("SELECT COUNT(*) AS count FROM users");
    $total = (int)$countStmt->fetch()['count'];

    //正常用户数量
    $activeStmt = $pdo->query("SELECT COUNT(*) AS count FROM users WHERE status = 1");
    $active = (int)$activeStmt->fetch()['count'];

    //禁用的用户数量
    $disabled = $total - $active;

    //管理员数量
    $adminStmt = $pdo->query("SELECT COUNT(*) AS count FROM users WHERE role = 1");
    $admins = (int)$adminStmt->fetch()['count'];

    //今日新增
    $todayStmt = $pdo->prepare("SELECT COUNT(*) AS count FROM users WHERE DATE(created_at) = CURDATE()");
    $today = (int)$todayStmt->fetch()['count'];

    Response::success([
        'total'    => $total,
        'active'   => $active,
        'disabled' => $disabled,
        'admins'   => $admins,
        'today'    => $today
    ], '获取用户统计成功');
}

/**
 * 禁用用户
 * POST /php/api/admin.php?action=disable
 * 参数: user_id
 */
function handleDisableUser(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $userId = (int)Request::input('user_id', 0);

    if ($userId <= 0) {
        Response::error('无效的用户ID', 400);
    }

    //不能禁用自己
    if ($userId == Auth::getUserId()) {
        Response::error('不能禁用自己', 400);
    }

    $pdo = getDB();

    //检查用户是否存在
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        Response::error('用户不存在', 404);
    }

    //不能禁用其他管理员
    if ($user['role'] == 1) {
        Response::error('不能禁用其他管理员', 403);
    }

    //执行禁用
    $stmt = $pdo->prepare("UPDATE users SET status = 0 WHERE id = ?");
    $stmt->execute([$userId]);

    Response::success(null, '用户已被禁用');
}

/**
 * 启用用户
 * POST /php/api/admin.php?action=enable
 * 参数: user_id
 */
function handleEnableUser(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $userId = (int)Request::input('user_id', 0);

    if ($userId <= 0) {
        Response::error('无效的用户ID', 400);
    }

    $pdo = getDB();

    //检查用户是否存在
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    if (!$stmt->fetch()) {
        Response::error('用户不存在', 404);
    }

    //执行启用
    $stmt = $pdo->prepare("UPDATE users SET status = 1 WHERE id = ?");
    $stmt->execute([$userId]);

    Response::success(null, '用户已被启用');
}