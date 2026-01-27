<?php
/**
 * 商品模块统一入口 - 包含分类、商品、库存、图片上传
 * 
 * === 分类接口 ===
 * GET    ?action=category_list              获取分类列表(树形)
 * GET    ?action=category_detail&id=1       获取分类详情
 * POST   ?action=category_add               添加分类 (管理员)
 * POST   ?action=category_update            更新分类 (管理员)
 * POST   ?action=category_delete            删除分类 (管理员)
 * 
 * === 商品接口 ===
 * GET    ?action=product_list               商品列表(支持搜索/筛选/分页)
 * GET    ?action=product_detail&id=1        商品详情
 * POST   ?action=product_add                添加商品 (管理员)
 * POST   ?action=product_update             更新商品 (管理员)
 * POST   ?action=product_delete             删除商品 (管理员)
 * POST   ?action=product_toggle_status      切换商品状态 (管理员)
 * 
 * === 库存接口 ===
 * GET    ?action=inventory_get&product_id=1 获取库存信息
 * POST   ?action=inventory_update           更新库存 (管理员)
 * 
 * === 图片上传 ===
 * POST   ?action=upload_image               上传图片 (管理员)
 */

header('Content-Type: application/json; charset=utf-8');

//引入核心文件
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/request.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    // 分类接口
    case 'category_list':
        handleCategoryList();
        break;
    case 'category_detail':
        handleCategoryDetail();
        break;
    case 'category_add':
        handleCategoryAdd();
        break;
    case 'category_update':
        handleCategoryUpdate();
        break;
    case 'category_delete':
        handleCategoryDelete();
        break;
    // 商品接口
    case 'product_list':
        handleProductList();
        break;
    case 'product_detail':
        handleProductDetail();
        break;
    case 'product_add':
        handleProductAdd();
        break;
    case 'product_update':
        handleProductUpdate();
        break;
    case 'product_delete':
        handleProductDelete();
        break;
    case 'product_toggle_status':
        handleProductToggleStatus();
        break;
    // 库存接口
    case 'inventory_get':
        handleInventoryGet();
        break;
    case 'inventory_update':
        handleInventoryUpdate();
        break;
    // 图片上传
    case 'upload_image':
        handleUploadImage();
        break;
    default:
        Response::error('无效的操作', 400);
}

// 辅助函数

/**
 * 验证管理员权限
 */
function requireAdmin(): void
{
    Auth::requireLogin();

    $pdo = getDB();
    $userId = Auth::getUserId();

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] != 1) {
        Response::error('需要管理员权限', 403);
    }
}

/**
 * 构建分类树形结构
 */
function buildCategoryTree(array $categories, int $parentId = 0): array
{
    $tree = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parentId) {
            $children = buildCategoryTree($categories, $category['id']);
            if ($children) {
                $category['children'] = $children;
            }
            $tree[] = $category;
        }
    }
    return $tree;
}

/**
 * 获取分类的所有子分类ID(包括自身)
 */
function getCategoryChildIds(PDO $pdo, int $categoryId): array
{
    $ids = [$categoryId];

    $stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
    $stmt->execute([$categoryId]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($children as $childId) {
        $ids = array_merge($ids, getCategoryChildIds($pdo, (int)$childId));
    }

    return $ids;
}

//分类处理函数

/**
 * 获取分类列表(树形结构)
 */
function handleCategoryList(): void
{
    Request::allowMethods('GET');

    $pdo = getDB();
    $flat = $_GET['flat'] ?? '0';
    $showHidden = $_GET['show_hidden'] ?? '0';//是否显示隐藏分类

    $sql = "SELECT id, name, parent_id, sort_order, status, created_at FROm categories";
    if ($showHidden !== '1') {
        $sql .= " WHERE status = 1";
    }
    $sql .= " ORDER BY sort_order ASC, id ASC";

    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //转换数据类型
    foreach ($categories as &$cat) {
        $cat['id'] = (int)$cat['id'];
        $cat['parent_id'] = (int)$cat['parent_id'];
        $cat['sort_order'] = (int)$cat['sort_order'];
        $cat['status'] = (int)$cat['status'];
    }
    unset($cat);

    if ($flat === '1') {
        Response::success($categories, '获取分类列表成功');
    } else {
        $tree = buildCategoryTree($categories, 0);
        Response::success($tree, '获取分类列表成功');
    }
}

/**
 * 获取分类详情
 */
function handleCategoryDetail(): void
{
    Request::allowMethods('GET');

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        Response::error('分类ID无效', 400);
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, name, parent_id, sort_order, status, created_at, updated_at FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        Response::error('分类不存在', 404);
    }

//获取父类分类名称
    $sparentName = null;
    if ($category['parent_id'] > 0) {
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$category['parent_id']]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        $sparentName = $parent ? $parent['name'] : null;   
    }

    $category['id'] = (int)$category['id'];
    $category['parent_id'] = (int)$category['parent_id'];
    $category['parent_name'] = $sparentName;
    $category['sort_order'] = (int)$category['sort_order'];
    $category['status'] = (int)$category['status'];

    Response::success($category, '获取成功');
}

/**
 * 添加分类 (管理员)
 */
function handleCategoryAdd(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $name = trim(Request::input('name', ''));
    $parentId = (int)Request::input('parent_id', 0);
    $sortOrder = (int)Request::input('sort_order', 0);
    $status = (int)Request::input('status', 1);

    if (empty($name)) {
        Response::error('分类名称不能为空', 400);
    }
    if (mb_strlen($name) > 50) {
        Response::error('分类名称不能超过50个字符', 400);
    }

    $pdo = getDB();

    //检查父分类是否存在
    if ($parentId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$parentId]);
        if (!$stmt->fetch()) {
            Response::error('父分类不存在', 400);
        }
    }

    //检查同级是否有重名分类
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND parent_id = ?");
    $stmt->execute([$name, $parentId]);
    if ($stmt->fetch()) {
        Response::error('同级分类已存在相同名称的分类', 400);   
    }

    $stmt = $pdo->prepare("INSERT INTO categories (name, parent_id, sort_order, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $parentId, $sortOrder, $status]);

    Response::success(['category_id' => (int)$pdo->lastInsertId()], '添加成功', 201);
}

/**
 * 更新分类 (管理员)
 */
function handleCategoryUpdate(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $id = (int)Request::input('id', 0);
    if ($id <= 0) {
        Response::error('分类ID无效', 400);
    }

    $pdo = getDB();

    //检查分类是否存在
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$category) {
        Response::error('分类不存在', 404);
    }

    $name = trim(Request::input('name', $category['name']));
    $parentId = Request::input('parent_id');
    $sortOrder = Request::input('sort_order');
    $status = Request::input('status');

    $parentId = $parentId !== null ? (int)$parentId : (int)$category['parent_id'];
    $sortOrder = $sortOrder !== null ? (int)$sortOrder : (int)$category['sort_order'];
    $status = $status !== null ? (int)$status : (int)$category['status'];

    if (empty($name)) {
        Response::error('分类名称不能为空', 400);
    }
    if (mb_strlen($name) > 50) {
        Response::error('分类名称不能超过50个字符', 400);   
    }

    //不能将自己设为父分类
    if ($parentId == $id) {
        Response::error('不能将自己设为父分类', 400);
    }

    //检查是否会造成循环引用
    if ($parentId > 0) {
        $childIds = getCategoryChildIds($pdo, $id);
        if (in_array($parentId, $childIds)) {
            Response::error('不能将子分类设为父分类', 400);
        }
    }

    //检查同级是否有重名分类（排除自己）
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND parent_id = ? AND id != ?");
    $stmt->execute([$name, $parentId, $id]);
    if ($stmt->fetch()) {
        Response::error('同级分类已存在相同名称的分类', 409);
    }

    $stmt = $pdo->prepare("UPDATE categories SET name = ?, parent_id = ?, sort_order = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $parentId, $sortOrder, $status, $id]);

    Response::success(null, '更新成功');
}

/**
 * 删除分类 (管理员)
 */
function handleCategoryDelete(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $id = (int)Request::input('id', 0);
    if ($id <= 0) {
        Response::error('分类ID无效', 400);
    }

    $pdo = getDB();

    //检查分类是否存在
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        Response::error('分类不存在', 404);
    }

    //检查是否有子分类
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetch()) {
        Response::error('请先删除子分类', 400);
    }

    //检查是否有商品关联
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        Response::error('该分类下存在商品，无法删除', 400);
    }

    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);

    Response::success(null, '删除成功');
}

//商品处理函数

/**
 * 获取商品列表(支持搜索/筛选/分页)
 */
function handleProductList(): void
{
    Request::allowMethods('GET');

    $pdo = getDB();

    //获取参数
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int)($_GET['page_size'] ?? 20)));
    $keyword = trim($_GET['keyword'] ?? '');
    $categoryId = $_GET['category_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $minPrice = $_GET['min_price'] ?? '';
    $maxPrice = $_GET['max_price'] ?? '';
    $sortBy = $_GET['sort_by'] ?? 'id';
    $sortOrder = strtolower($_GET['sort_order'] ?? 'DESC');
    $hasStock = $_GET['has_stock'] ?? '';//是否只显示有库存的商品

    //构建查询条件
    $where = [];
    $params = [];

    //关键词搜索(名称和描述)
    if ($keyword !== '') {
        $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%{$keyword}%";
        $params[] = "%{$keyword}%"; 
    }

    //分类筛选(包含子分类)
    if ($categoryId !== '' && (int)$categoryId > 0) {
        $categoryIds = getCategoryChildIds($pdo, (int)$categoryId);
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $where[] = "p.category_id IN ({$placeholders})";
        $params = array_merge($params, $categoryIds);
    }

    //状态筛选
    if ($status !== '') {
        $where[] = "p.status = ?";
        $params[] = (int)$status;
    }
    
    // 价格区间
    if ($minPrice !== '' && is_numeric($minPrice)) {
        $where[] = "p.price >= ?";
        $params[] = (float)$minPrice;
    }
    if ($maxPrice !== '' && is_numeric($maxPrice)) {
        $where[] = "p.price <= ?";
        $params[] = (float)$maxPrice;
    }
    
    // 只显示有库存的
    if ($hasStock === '1') {
        $where[] = "COALESCE(i.stock, 0) - COALESCE(i.locked_stock, 0) > 0";
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // 排序字段白名单
    $allowedSortBy = ['id', 'name', 'price', 'created_at', 'updated_at'];
    if (!in_array($sortBy, $allowedSortBy)) {
        $sortBy = 'id';
    }
    if (!in_array($sortOrder, ['ASC', 'DESC'])) {
        $sortOrder = 'DESC';
    }

    // 获取总数
    $countSql = "SELECT COUNT(*) 
                 FROM products p 
                 LEFT JOIN inventory i ON p.id = i.product_id 
                 {$whereClause}";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    
    // 计算分页
    $totalPages = $total > 0 ? ceil($total / $pageSize) : 0;
    $offset = ($page - 1) * $pageSize;
    
    // 获取列表
    $sql = "SELECT p.id, p.name, p.cover_image, p.price, p.original_price, 
                   p.category_id, c.name as category_name, p.status, 
                   p.created_at, p.updated_at,
                   COALESCE(i.stock, 0) as stock,
                   COALESCE(i.locked_stock, 0) as locked_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN inventory i ON p.id = i.product_id
            {$whereClause}
            ORDER BY p.{$sortBy} {$sortOrder}
            LIMIT {$pageSize} OFFSET {$offset}";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 转换数据类型
    foreach ($products as &$product) {
        $product['id'] = (int)$product['id'];
        $product['price'] = (float)$product['price'];
        $product['original_price'] = $product['original_price'] ? (float)$product['original_price'] : null;
        $product['category_id'] = $product['category_id'] ? (int)$product['category_id'] : null;
        $product['status'] = (int)$product['status'];
        $product['stock'] = (int)$product['stock'];
        $product['locked_stock'] = (int)$product['locked_stock'];
        $product['available_stock'] = $product['stock'] - $product['locked_stock'];
    }
    unset($product);
    
     Response::success([
        'list' => $products,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages
        ]
    ], '获取成功');
}

/**
 * 获取商品详情
 */
 function handleProductDetail(): void
 {
    Request::allowMethods('GET');

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        Response::error('商品ID无效', 400);
    }

    $pdo = getDB();

    $sql = "SELECT p.*, c.name as category_name,
                   COALESCE(i.stock, 0) as stock,
                   COALESCE(i.locked_stock, 0) as locked_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        Response::error('商品不存在', 404);
    }

    //转换数据类型
    $product['id'] = (int)$product['id'];
    $product['price'] = (float)$product['price'];
    $product['original_price'] = $product['original_price'] ? (float)$product['original_price'] : null;
    $product['category_id'] = $product['category_id'] ? (int)$product['category_id'] : null;
    $product['status'] = (int)$product['status'];
    $product['stock'] = (int)$product['stock'];
    $product['locked_stock'] = (int)$product['locked_stock'];
    $product['available_stock'] = $product['stock'] - $product['locked_stock'];

    Response::success($product, '获取成功');
 }

 /**
 * 添加商品 (管理员)
 */

function handleProductAdd(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $name = trim(Request::input('name', ''));
    $description = trim(Request::input('description', ''));
    $coverImage = trim(Request::input('cover_image', ''));
    $price = Request::input('price', '');
    $originalPrice = Request::input('original_price', '');
    $categoryId = Request::input('category_id');
    $status = (int)Request::input('status', 1);
    $initialStock = (int)Request::input('initial_stock', 0);

    //验证
    if (empty($name)) {
        Response::error('商品名称不能为空', 400);
    }
    if (mb_strlen($name) > 200) {
        Response::error('商品名称不能超过200个字符', 400);
    }
    if ($price === '' || !is_numeric($price) || (float)$price < 0) {
        Response::error('请输入有效的商品价格', 400);
    }
    if ($originalPrice !== '' && (!is_numeric($originalPrice) || $originalPrice < 0)) {
        Response::error('原价格式无效', 400);
    }

    $pdo = getDB();

    // 检查分类是否存在【允许为空】
    $categoryId = $categoryId !== null && $categoryId !== '' ? (int)$categoryId : null;
    if ($categoryId !== null) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        if (!$stmt->fetch()) {
            Response::error('分类不存在', 400);
        }
    }
    
    $pdo->beginTransaction();
    try {
        //输入商品
        $stmt = $pdo->prepare("INSERT INTO products (name, description, cover_image, price, original_price, category_id, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name,
            $description ?: null,
            $coverImage ?: null,
            (float)$price,
            $originalPrice !== '' ? (float)$originalPrice : null,
            $categoryId,
            $status
        ]);
        $productId = (int)$pdo->lastInsertId();

        //初始化库存
        $stmt = $pdo->prepare("INSERT INTO inventory (product_id, stock, locked_stock) VALUES (?, ?, 0)");
        $stmt->execute([$productId, $initialStock]);

        $pdo->commit();

        Response::success(['product_id' => $productId], '商品添加成功', 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        Response::error('商品添加失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 更新商品 (管理员)
 */
function handleProductUpdate(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $id = (int)Request::input('id', 0);
    if ($id <= 0) {
        Response::error('商品ID无效', 400);
    }

    $pdo = getDB();

    //检查商品是否存在
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        Response::error('商品不存在', 404);
    }

    //获取更新字段
    $name = Request::input('name');
    $description = Request::input('description');
    $coverImage = Request::input('cover_image');
    $price = Request::input('price');
    $originalPrice = Request::input('original_price');
    $categoryId = Request::input('category_id');
    $status = Request::input('status');

    //构建更新
    $updates = [];
    $params = [];

    if ($name !== null) {
        $name = trim($name);
        if (empty($name)) {
            Response::error('商品名称不能为空', 400);
        }
        if (mb_strlen($name) > 200) {
            Response::error('商品名称不能超过200个字符', 400);
        }
        $updates[] = "name = ?";
        $params[] = $name;
    }

    if ($description !== null) {
        $updates[] = "description = ?";
        $params[] = trim($description) ?: null;
    }

    if ($coverImage !== null) {
        $updates[] = "cover_image = ?";
        $params[] = trim($coverImage) ?: null;
    }

    if ($price !== null) {
        if (!is_numeric($price) || (float)$price < 0) {
            Response::error('请输入有效的商品价格', 400);
        }
        $updates[] = "price = ?";
        $params[] = (float)$price;
    }

    if ($originalPrice !== null) {
        if ($originalPrice !== '' && (!is_numeric($originalPrice) || (float)$originalPrice < 0)) {
            Response::error('原价格式无效', 400);
        }
        $updates[] = "original_price = ?";
        $params[] = $originalPrice !== '' ? (float)$originalPrice : null;
    }

    if ($categoryId !== null) {
        if ($categoryId === '' || $categoryId === '0 ') {
            $updates[] = "category_id = ?";
            $params[] = null;
        } else {
            $categoryId = (int)$categoryId;
            //检查分类是否存在
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            if (!$stmt->fetch()) {
                Response::error('分类不存在', 400);
            }
            $updates[] = "category_id = ?";
            $params[] = $categoryId;
        }
    }

    if ($status !== null) {
        $updates[] = "status = ?";
        $params[] = (int)$status;
    }

    if (empty($updates)) {
        Response::error('没有提供任何更新字段', 400);
    }

    $params[] = $id;
    $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    Response::success(null, '商品更新成功');
}

/**
 * 删除商品 (管理员)
 */
function handleProductDelete(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $id = (int)Request::input('id', 0);
    if ($id <= 0) {
        Response::error('商品ID无效', 400);
    }

    $pdo = getDB();

    //检查商品是否存在
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        Response::error('商品不存在', 404);
    }

    //检查是否有未完成的订单
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items oi 
                           JOIN orders o ON oi.order_id = o.id 
                           WHERE oi.product_id = ? AND o.status IN (0, 1, 2)");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        Response::error('该商品有未完成的订单，无法删除', 400);
    }

    //检查是否存在购物车中
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart_items WHERE product_id = ?");
    $stmt->execute([$id]);
    $cartCount = $stmt->fetchColumn();

    $pdo->beginTransaction();
    try {
        //删除购物车中的记录
        if ($cartCount > 0) {
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE product_id = ?");
            $stmt->execute([$id]);
        }

        //删除库存记录。（由于有外键 CASCADE， 可能会自动删除）
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE product_id = ?");
        $stmt->execute([$id]);

        //删除商品
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        Response::success(null, '商品删除成功');
    } catch (Exception $e) {
        $pdo->rollBack();
        Response::error('商品删除失败: ' . $e->getMessage(), 500);
    } 
}

/**
 * 切换商品状态 (管理员)
 */
function handleProductToggleStatus(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $id = (int)Request::input('id', 0);
    if ($id <= 0) {
        Response::error('商品ID无效', 400);
    }

    $pdo = getDB();

    //检查商品是否存在
    $stmt = $pdo->prepare("SELECT status FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        Response::error('商品不存在', 404);
    }

    $newStatus = $product['status'] == 1 ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);

    $statusText = $newStatus == 1 ? '上架' : '下架';
    Response::success(['status' => $newStatus], "商品已{$statusText}");

}

//库存处理函数

/**
 * 获取库存信息
 */
function handleInventoryGet(): void
{
    Request::allowMethods('GET');

    $productId = (int)($_GET['product_id'] ?? 0);
    if ($productId <= 0) {
        Response::error('商品ID无效', 400);
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT p.id, p.name, 
                                  COALESCE(i.stock, 0) as stock, 
                                  COALESCE(i.locked_stock, 0) as locked_stock,
                                  i.updated_at
                           FROM products p 
                           LEFT JOIN inventory i ON p.id = i.product_id 
                           WHERE p.id = ?");
    $stmt->execute([$productId]);
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventory) {
        Response::error('商品不存在', 404);
    }

    $inventory['id'] = (int)$inventory['id'];
    $inventory['stock'] = (int)$inventory['stock'];
    $inventory['locked_stock'] = (int)$inventory['locked_stock'];
    $inventory['available_stock'] = $inventory['stock'] - $inventory['locked_stock'];
    
    Response::success($inventory, '获取成功');
}

/**
 * 更新库存 (管理员)
 */
function handleInventoryUpdate(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $productId = (int)Request::input('product_id', 0);
    $quantity = Request::input('quantity', '');
    $type = Request::input('type', 'set');
    $reason = trim(Request::input('reason', ''));

    if ($productId <= 0) {
        Response::error('商品ID无效', 400);
    }
    if ($quantity === '' || !is_numeric($quantity)) {
        Response::error('请输入有效的数量', 400);
    }
    $quantity = (int)$quantity;

    $pdo = getDB();

    // 检查商品是否存在并获取库存信息
    $stmt = $pdo->prepare("SELECT p.id, p.name, 
                                  COALESCE(i.stock, 0) as stock, 
                                  COALESCE(i.locked_stock, 0) as locked_stock
                           FROM products p 
                           LEFT JOIN inventory i ON p.id = i.product_id 
                           WHERE p.id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        Response::error('商品不存在', 404);
    }

    $currentStock = (int)$product['stock'];
    $lockedStock = (int)$product['locked_stock'];
    
    // 计算新库存
    switch ($type) {
        case 'set':
            if ($quantity < 0) {
                Response::error('库存不能为负数', 400);
            }
            if ($quantity < $lockedStock) {
                Response::error("库存不能小于锁定数量({$lockedStock})", 400);
            }
            $newStock = $quantity;
            break;
        case 'add':  // 修复：分号改为冒号
            if ($quantity <= 0) {
                Response::error('增加数量必须大于0', 400);
            }
            $newStock = $currentStock + $quantity;  // 修复：设置 $newStock
            break;
        case 'reduce':  // 修复：分号改为冒号
            if ($quantity <= 0) {
                Response::error('减少数量必须大于0', 400);
            }
            $availableStock = $currentStock - $lockedStock;
            if ($quantity > $availableStock) {
                Response::error("可用库存不足，当前可用库存为{$availableStock}", 400);
            }
            $newStock = $currentStock - $quantity;
            break;
        default:
            Response::error('无效的操作类型', 400);
    }

    // 检查库存记录是否存在
    $stmt = $pdo->prepare("SELECT product_id FROM inventory WHERE product_id = ?");
    $stmt->execute([$productId]);
    $inventoryExists = $stmt->fetch();

    // 更新库存
    if ($inventoryExists) {
        $stmt = $pdo->prepare("UPDATE inventory SET stock = ? WHERE product_id = ?");
        $stmt->execute([$newStock, $productId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO inventory (product_id, stock, locked_stock) VALUES (?, ?, 0)");
        $stmt->execute([$productId, $newStock]);
    }

    Response::success([
        'product_id' => $productId,
        'product_name' => $product['name'],  // 修复：使用正确的变量
        'before_stock' => $currentStock,
        'after_stock' => $newStock,
        'change' => $newStock - $currentStock,
        'locked_stock' => $lockedStock,
        'available_stock' => $newStock - $lockedStock
    ], '库存更新成功');
}

//图片上传处理函数

/**
 * 上传图片 (管理员)
 */
function handleUploadImage(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    //检查是否有文件上传
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止',
        ];
        $error = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
        Response::error($errorMessages[$error] ?? '文件上传失败', 400);
    }

    $file = $_FILES['image'];

    //验证文件类型
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        Response::error('只支持JPEG, PNG, GIF, 和 WEBP格式的图片', 400);
    }

    //验证文件大小(最大5MB)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        Response::error('图片大小不能超过5MB', 400);
    }

    // 创建上传目录
    $uploadDir = __DIR__ . '/../uploads/products/' . date('Ym');
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            Response::error('无法创建上传目录', 500);
        }
    }

    //生成文件名
    $extension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ][$mimeType];
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . '/' . $filename;

    // 移动文件
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        Response::error('文件保存失败', 500);
    }

    //返回相对路径
    $relativePath = '/uploads/products/' . date('Ym') . '/' . $filename;

     Response::success([
        'url' => $relativePath,
        'filename' => $filename,
        'size' => $file['size'],
        'mime_type' => $mimeType
    ], '上传成功');
}