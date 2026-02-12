<?php
/**
 * 用户模块统一入口
 * 
 * POST /php/api/user.php?action=register  注册
 * POST /php/api/user.php?action=login     登录
 * POST /php/api/user.php?action=logout    登出
 * GET  /php/api/user.php?action=profile   获取用户信息
 * 
 * === 收藏接口 ===
 * GET  /php/api/user.php?action=favorite_list     收藏列表
 * POST /php/api/user.php?action=favorite_add      添加收藏
 * POST /php/api/user.php?action=favorite_delete   取消收藏
 * GET  /php/api/user.php?action=favorite_check    检查是否收藏
 * 
 * === 足迹接口 ===
 * GET  /php/api/user.php?action=footprint_list    足迹列表
 * POST /php/api/user.php?action=footprint_clear   清空足迹
 * POST /php/api/user.php?action=footprint_delete  删除单条足迹
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
    // 收藏接口
    case 'favorite_list':
        handleFavoriteList();
        break;
    case 'favorite_add':
        handleFavoriteAdd();
        break;
    case 'favorite_delete':
        handleFavoriteDelete();
        break;
    case 'favorite_check':
        handleFavoriteCheck();
        break;
    // 足迹接口
    case 'footprint_list':
        handleFootprintList();
        break;
    case 'footprint_clear':
        handleFootprintClear();
        break;
    case 'footprint_delete':
        handleFootprintDelete();
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

// ============ 收藏处理函数 ============

/**
 * 获取收藏列表
 */
function handleFavoriteList(): void
{
    Request::allowMethods('GET');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $pdo = getDB();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($_GET['page_size'] ?? 20)));
    $offset = ($page - 1) * $pageSize;

    // 总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_favorites WHERE user_id = ?");
    $stmt->execute([$userId]);
    $total = (int)$stmt->fetchColumn();
    $totalPages = $total > 0 ? ceil($total / $pageSize) : 0;

    // 列表（关联商品信息）
    $stmt = $pdo->prepare("SELECT f.id, f.product_id, f.created_at,
                                  p.name, p.cover_image, p.price, p.original_price, p.status,
                                  p.sales_count, p.rating
                           FROM user_favorites f
                           LEFT JOIN products p ON f.product_id = p.id
                           WHERE f.user_id = ?
                           ORDER BY f.created_at DESC
                           LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($favorites as &$fav) {
        $fav['id'] = (int)$fav['id'];
        $fav['product_id'] = (int)$fav['product_id'];
        $fav['price'] = $fav['price'] ? (float)$fav['price'] : null;
        $fav['original_price'] = $fav['original_price'] ? (float)$fav['original_price'] : null;
        $fav['status'] = $fav['status'] !== null ? (int)$fav['status'] : null;
        $fav['sales_count'] = (int)($fav['sales_count'] ?? 0);
        $fav['rating'] = (float)($fav['rating'] ?? 5.0);
    }
    unset($fav);

    Response::success([
        'list' => $favorites,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages
        ]
    ], '获取成功');
}

/**
 * 添加收藏
 */
function handleFavoriteAdd(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $productId = (int)Request::input('product_id', 0);

    if ($productId <= 0) {
        Response::error('商品ID无效', 400);
    }

    $pdo = getDB();

    // 检查商品是否存在
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) {
        Response::error('商品不存在', 404);
    }

    // 检查是否已收藏
    $stmt = $pdo->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    if ($stmt->fetch()) {
        Response::error('已收藏该商品', 409);
    }

    $stmt = $pdo->prepare("INSERT INTO user_favorites (user_id, product_id) VALUES (?, ?)");
    $stmt->execute([$userId, $productId]);

    Response::success(null, '收藏成功', 201);
}

/**
 * 取消收藏
 */
function handleFavoriteDelete(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $productId = (int)Request::input('product_id', 0);

    if ($productId <= 0) {
        Response::error('商品ID无效', 400);
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("DELETE FROM user_favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);

    if ($stmt->rowCount() === 0) {
        Response::error('未收藏该商品', 404);
    }

    Response::success(null, '取消收藏成功');
}

/**
 * 检查是否已收藏
 */
function handleFavoriteCheck(): void
{
    Request::allowMethods('GET');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $productId = (int)($_GET['product_id'] ?? 0);

    if ($productId <= 0) {
        Response::error('商品ID无效', 400);
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);

    Response::success(['is_favorited' => (bool)$stmt->fetch()], '获取成功');
}

// ============ 足迹处理函数 ============

/**
 * 获取足迹列表
 */
function handleFootprintList(): void
{
    Request::allowMethods('GET');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $pdo = getDB();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($_GET['page_size'] ?? 20)));
    $offset = ($page - 1) * $pageSize;

    // 总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_footprints WHERE user_id = ?");
    $stmt->execute([$userId]);
    $total = (int)$stmt->fetchColumn();
    $totalPages = $total > 0 ? ceil($total / $pageSize) : 0;

    // 列表
    $stmt = $pdo->prepare("SELECT fp.id, fp.product_id, fp.updated_at as visit_time,
                                  p.name, p.cover_image, p.price, p.original_price, p.status
                           FROM user_footprints fp
                           LEFT JOIN products p ON fp.product_id = p.id
                           WHERE fp.user_id = ?
                           ORDER BY fp.updated_at DESC
                           LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $footprints = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($footprints as &$fp) {
        $fp['id'] = (int)$fp['id'];
        $fp['product_id'] = (int)$fp['product_id'];
        $fp['price'] = $fp['price'] ? (float)$fp['price'] : null;
        $fp['original_price'] = $fp['original_price'] ? (float)$fp['original_price'] : null;
        $fp['status'] = $fp['status'] !== null ? (int)$fp['status'] : null;
    }
    unset($fp);

    Response::success([
        'list' => $footprints,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages
        ]
    ], '获取成功');
}

/**
 * 清空足迹
 */
function handleFootprintClear(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();

    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM user_footprints WHERE user_id = ?");
    $stmt->execute([Auth::getUserId()]);

    Response::success(null, '足迹已清空');
}

/**
 * 删除单条足迹
 */
function handleFootprintDelete(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();

    $productId = (int)Request::input('product_id', 0);
    if ($productId <= 0) {
        Response::error('商品ID无效', 400);
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM user_footprints WHERE user_id = ? AND product_id = ?");
    $stmt->execute([Auth::getUserId(), $productId]);

    Response::success(null, '删除成功');
}