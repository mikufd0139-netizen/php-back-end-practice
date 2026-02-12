<?php
/**
 * 商品模块统一入口 - 包含分类、品牌、商品、商品图片、库存、评价、图片上传
 * 
 * === 分类接口 ===
 * GET    ?action=category_list              获取分类列表(树形)
 * GET    ?action=category_detail&id=1       获取分类详情
 * POST   ?action=category_add               添加分类 (管理员)
 * POST   ?action=category_update            更新分类 (管理员)
 * POST   ?action=category_delete            删除分类 (管理员)
 * 
 * === 品牌接口 ===
 * GET    ?action=brand_list                 品牌列表
 * GET    ?action=brand_detail&id=1          品牌详情
 * POST   ?action=brand_add                  添加品牌 (管理员)
 * POST   ?action=brand_update               更新品牌 (管理员)
 * POST   ?action=brand_delete               删除品牌 (管理员)
 * 
 * === 商品接口 ===
 * GET    ?action=product_list               商品列表(支持搜索/筛选/分页)
 * GET    ?action=product_detail&id=1        商品详情(含图片/品牌/评价统计)
 * POST   ?action=product_add                添加商品 (管理员)
 * POST   ?action=product_update             更新商品 (管理员)
 * POST   ?action=product_delete             删除商品 (管理员)
 * POST   ?action=product_toggle_status      切换商品状态 (管理员)
 * 
 * === 商品图片接口 ===
 * GET    ?action=product_image_list&product_id=1  获取商品图片列表
 * POST   ?action=product_image_add                添加商品图片 (管理员)
 * POST   ?action=product_image_delete             删除商品图片 (管理员)
 * POST   ?action=product_image_sort               更新图片排序 (管理员)
 * POST   ?action=product_image_set_cover          设为封面图 (管理员)
 * 
 * === 库存接口 ===
 * GET    ?action=inventory_get&product_id=1 获取库存信息
 * POST   ?action=inventory_update           更新库存 (管理员，自动记录日志)
 * GET    ?action=inventory_log_list         库存变动日志 (管理员)
 * 
 * === 商品评价接口 ===
 * GET    ?action=review_list&product_id=1   获取商品评价列表 (公开)
 * POST   ?action=review_add                 提交评价 (用户)
 * GET    ?action=admin_review_list          管理员评价列表
 * POST   ?action=admin_review_reply         管理员回复评价
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
    // 品牌接口
    case 'brand_list':
        handleBrandList();
        break;
    case 'brand_detail':
        handleBrandDetail();
        break;
    case 'brand_add':
        handleBrandAdd();
        break;
    case 'brand_update':
        handleBrandUpdate();
        break;
    case 'brand_delete':
        handleBrandDelete();
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
    // 商品图片接口
    case 'product_image_list':
        handleProductImageList();
        break;
    case 'product_image_add':
        handleProductImageAdd();
        break;
    case 'product_image_delete':
        handleProductImageDelete();
        break;
    case 'product_image_sort':
        handleProductImageSort();
        break;
    case 'product_image_set_cover':
        handleProductImageSetCover();
        break;
    // 库存接口
    case 'inventory_get':
        handleInventoryGet();
        break;
    case 'inventory_update':
        handleInventoryUpdate();
        break;
    case 'inventory_log_list':
        handleInventoryLogList();
        break;
    // 商品评价接口
    case 'review_list':
        handleReviewList();
        break;
    case 'review_add':
        handleReviewAdd();
        break;
    case 'admin_review_list':
        handleAdminReviewList();
        break;
    case 'admin_review_reply':
        handleAdminReviewReply();
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
 * 获取分类的所有子分类ID(包括自身) —— 基于 path 字段，单条SQL
 */
function getCategoryChildIds(PDO $pdo, int $categoryId): array
{
    // 先拿当前分类的 path
    $stmt = $pdo->prepare("SELECT path FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $path = $stmt->fetchColumn();

    if (!$path) {
        return [$categoryId];
    }

    // 用 LIKE 查所有以该 path 开头的子孙分类
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE path LIKE ?");
    $stmt->execute([$path . ',%']);
    $childIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return array_merge([$categoryId], array_map('intval', $childIds));
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

    $sql = "SELECT id, name, icon, image, description, parent_id, level, path, sort_order, status, created_at, updated_at FROM categories";
    if ($showHidden !== '1') {
        $sql .= " WHERE status = 1";
    }
    $sql .= " ORDER BY level ASC, sort_order ASC, id ASC";

    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //转换数据类型
    foreach ($categories as &$cat) {
        $cat['id'] = (int)$cat['id'];
        $cat['parent_id'] = (int)$cat['parent_id'];
        $cat['level'] = (int)$cat['level'];
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
    $stmt = $pdo->prepare("SELECT id, name, icon, image, description, parent_id, level, path, sort_order, status, created_at, updated_at FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        Response::error('分类不存在', 404);
    }

    //获取父分类名称
    $parentName = null;
    if ($category['parent_id'] > 0) {
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$category['parent_id']]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        $parentName = $parent ? $parent['name'] : null;
    }

    // 获取子分类数量
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
    $stmt->execute([$id]);
    $childCount = (int)$stmt->fetchColumn();

    $category['id'] = (int)$category['id'];
    $category['parent_id'] = (int)$category['parent_id'];
    $category['parent_name'] = $parentName;
    $category['level'] = (int)$category['level'];
    $category['sort_order'] = (int)$category['sort_order'];
    $category['status'] = (int)$category['status'];
    $category['child_count'] = $childCount;

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
    $icon = trim(Request::input('icon', ''));
    $image = trim(Request::input('image', ''));
    $description = trim(Request::input('description', ''));

    if (empty($name)) {
        Response::error('分类名称不能为空', 400);
    }
    if (mb_strlen($name) > 50) {
        Response::error('分类名称不能超过50个字符', 400);
    }

    $pdo = getDB();

    // 计算 level 和 path
    $level = 1;
    $parentPath = '0';
    if ($parentId > 0) {
        $stmt = $pdo->prepare("SELECT id, level, path FROM categories WHERE id = ?");
        $stmt->execute([$parentId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$parent) {
            Response::error('父分类不存在', 400);
        }
        if ((int)$parent['level'] >= 3) {
            Response::error('最多支持三级分类', 400);
        }
        $level = (int)$parent['level'] + 1;
        $parentPath = $parent['path'];
    }

    //检查同级是否有重名分类
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND parent_id = ?");
    $stmt->execute([$name, $parentId]);
    if ($stmt->fetch()) {
        Response::error('同级分类已存在相同名称的分类', 400);   
    }

    $stmt = $pdo->prepare("INSERT INTO categories (name, icon, image, description, parent_id, level, path, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, '', ?, ?)");
    $stmt->execute([
        $name,
        $icon ?: null,
        $image ?: null,
        $description ?: null,
        $parentId,
        $level,
        $sortOrder,
        $status
    ]);
    $newId = (int)$pdo->lastInsertId();

    // 更新 path（需要知道自己的 id）
    $path = $parentPath . ',' . $newId;
    $stmt = $pdo->prepare("UPDATE categories SET path = ? WHERE id = ?");
    $stmt->execute([$path, $newId]);

    Response::success(['category_id' => $newId], '添加成功', 201);
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
    $icon = Request::input('icon');
    $image = Request::input('image');
    $description = Request::input('description');

    $parentId = $parentId !== null ? (int)$parentId : (int)$category['parent_id'];
    $sortOrder = $sortOrder !== null ? (int)$sortOrder : (int)$category['sort_order'];
    $status = $status !== null ? (int)$status : (int)$category['status'];
    $icon = $icon !== null ? trim($icon) : $category['icon'];
    $image = $image !== null ? trim($image) : $category['image'];
    $description = $description !== null ? trim($description) : $category['description'];

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

    // 计算新的 level 和 path
    $oldParentId = (int)$category['parent_id'];
    $level = (int)$category['level'];
    $path = $category['path'];

    if ($parentId !== $oldParentId) {
        // 父分类变了，重新计算 level 和 path
        if ($parentId === 0) {
            $level = 1;
            $path = '0,' . $id;
        } else {
            $stmt = $pdo->prepare("SELECT level, path FROM categories WHERE id = ?");
            $stmt->execute([$parentId]);
            $newParent = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$newParent) {
                Response::error('父分类不存在', 400);
            }
            if ((int)$newParent['level'] >= 3) {
                Response::error('最多支持三级分类', 400);
            }
            $level = (int)$newParent['level'] + 1;
            $path = $newParent['path'] . ',' . $id;
        }

        // 更新所有子孙分类的 path 和 level
        $oldPath = $category['path'];
        $stmt = $pdo->prepare("SELECT id, path, level FROM categories WHERE path LIKE ? AND id != ?");
        $stmt->execute([$oldPath . ',%', $id]);
        $descendants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($descendants as $desc) {
            $newDescPath = str_replace($oldPath, $path, $desc['path']);
            $newDescLevel = substr_count($newDescPath, ',');
            $stmtUp = $pdo->prepare("UPDATE categories SET path = ?, level = ? WHERE id = ?");
            $stmtUp->execute([$newDescPath, $newDescLevel, $desc['id']]);
        }
    }

    $stmt = $pdo->prepare("UPDATE categories SET name = ?, icon = ?, image = ?, description = ?, parent_id = ?, level = ?, path = ?, sort_order = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $icon ?: null, $image ?: null, $description ?: null, $parentId, $level, $path, $sortOrder, $status, $id]);

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

// ============ 品牌处理函数 ============

/**
 * 获取品牌列表
 */
function handleBrandList(): void
{
    Request::allowMethods('GET');

    $pdo = getDB();
    $showHidden = ($_GET['show_hidden'] ?? '0') === '1';

    $where = $showHidden ? '' : 'WHERE status = 1';
    $stmt = $pdo->query("SELECT id, name, name_en, logo, description, sort_order, status, created_at 
                         FROM brands {$where} ORDER BY sort_order ASC, id ASC");
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($brands as &$brand) {
        $brand['id'] = (int)$brand['id'];
        $brand['sort_order'] = (int)$brand['sort_order'];
        $brand['status'] = (int)$brand['status'];
    }
    unset($brand);

    Response::success($brands, '获取成功');
}

/**
 * 获取品牌详情
 */
function handleBrandDetail(): void
{
    Request::allowMethods('GET');

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        Response::error('品牌ID无效', 400);
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$id]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$brand) {
        Response::error('品牌不存在', 404);
    }

    // 统计该品牌下的商品数量
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE brand_id = ?");
    $stmt->execute([$id]);
    $brand['product_count'] = (int)$stmt->fetchColumn();

    $brand['id'] = (int)$brand['id'];
    $brand['sort_order'] = (int)$brand['sort_order'];
    $brand['status'] = (int)$brand['status'];

    Response::success($brand, '获取成功');
}

/**
 * 添加品牌 (管理员)
 */
function handleBrandAdd(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $name = trim(Request::input('name', ''));
    $nameEn = trim(Request::input('name_en', ''));
    $logo = trim(Request::input('logo', ''));
    $description = trim(Request::input('description', ''));
    $sortOrder = (int)Request::input('sort_order', 0);
    $status = (int)Request::input('status', 1);

    if (empty($name)) {
        Response::error('品牌名称不能为空', 400);
    }
    if (mb_strlen($name) > 100) {
        Response::error('品牌名称不能超过100个字符', 400);
    }

    $pdo = getDB();

    // 检查品牌名称是否重复
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        Response::error('品牌名称已存在', 409);
    }

    $stmt = $pdo->prepare("INSERT INTO brands (name, name_en, logo, description, sort_order, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $name,
        $nameEn ?: null,
        $logo ?: null,
        $description ?: null,
        $sortOrder,
        $status
    ]);

    Response::success(['brand_id' => (int)$pdo->lastInsertId()], '品牌添加成功', 201);
}

/**
 * 更新品牌 (管理员)
 */
function handleBrandUpdate(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $id = (int)Request::input('id', 0);
    if ($id <= 0) {
        Response::error('品牌ID无效', 400);
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$id]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$brand) {
        Response::error('品牌不存在', 404);
    }

    $name = Request::input('name');
    $nameEn = Request::input('name_en');
    $logo = Request::input('logo');
    $description = Request::input('description');
    $sortOrder = Request::input('sort_order');
    $status = Request::input('status');

    $updates = [];
    $params = [];

    if ($name !== null) {
        $name = trim($name);
        if (empty($name)) {
            Response::error('品牌名称不能为空', 400);
        }
        // 检查重名（排除自己）
        $stmt = $pdo->prepare("SELECT id FROM brands WHERE name = ? AND id != ?");
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) {
            Response::error('品牌名称已存在', 409);
        }
        $updates[] = "name = ?";
        $params[] = $name;
    }
    if ($nameEn !== null) {
        $updates[] = "name_en = ?";
        $params[] = trim($nameEn) ?: null;
    }
    if ($logo !== null) {
        $updates[] = "logo = ?";
        $params[] = trim($logo) ?: null;
    }
    if ($description !== null) {
        $updates[] = "description = ?";
        $params[] = trim($description) ?: null;
    }
    if ($sortOrder !== null) {
        $updates[] = "sort_order = ?";
        $params[] = (int)$sortOrder;
    }
    if ($status !== null) {
        $updates[] = "status = ?";
        $params[] = (int)$status;
    }

    if (empty($updates)) {
        Response::error('没有提供任何更新字段', 400);
    }

    $params[] = $id;
    $sql = "UPDATE brands SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    Response::success(null, '品牌更新成功');
}

/**
 * 删除品牌 (管理员)
 */
function handleBrandDelete(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $id = (int)Request::input('id', 0);
    if ($id <= 0) {
        Response::error('品牌ID无效', 400);
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT id FROM brands WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        Response::error('品牌不存在', 404);
    }

    // 检查是否有商品关联
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE brand_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        Response::error('该品牌下存在商品，无法删除', 400);
    }

    $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
    $stmt->execute([$id]);

    Response::success(null, '品牌删除成功');
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
    $isRecommend = $_GET['is_recommend'] ?? '';
    $isNew = $_GET['is_new'] ?? '';
    $isHot = $_GET['is_hot'] ?? '';

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

    // 推荐/新品/热销筛选
    if ($isRecommend === '1') {
        $where[] = "p.is_recommend = 1";
    }
    if ($isNew === '1') {
        $where[] = "p.is_new = 1";
    }
    if ($isHot === '1') {
        $where[] = "p.is_hot = 1";
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // 排序字段白名单
    $allowedSortBy = ['id', 'name', 'price', 'created_at', 'updated_at', 'sales_count', 'rating', 'view_count'];
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
    $sql = "SELECT p.id, p.spu_no, p.name, p.subtitle, p.cover_image, p.price, p.original_price, 
                   p.category_id, p.brand_id, c.name as category_name, b.name as brand_name, p.status, 
                   p.sales_count, p.view_count, p.rating,
                   p.is_recommend, p.is_new, p.is_hot,
                   p.created_at, p.updated_at,
                   COALESCE(i.stock, 0) as stock,
                   COALESCE(i.locked_stock, 0) as locked_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
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
        $product['brand_id'] = $product['brand_id'] ? (int)$product['brand_id'] : null;
        $product['status'] = (int)$product['status'];
        $product['sales_count'] = (int)$product['sales_count'];
        $product['view_count'] = (int)$product['view_count'];
        $product['rating'] = (float)$product['rating'];
        $product['is_recommend'] = (int)$product['is_recommend'];
        $product['is_new'] = (int)$product['is_new'];
        $product['is_hot'] = (int)$product['is_hot'];
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
                   b.name as brand_name, b.logo as brand_logo,
                   COALESCE(i.stock, 0) as stock,
                   COALESCE(i.locked_stock, 0) as locked_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        Response::error('商品不存在', 404);
    }

    // 浏览量+1
    $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);

    // 记录用户足迹（如果已登录）
    if (Auth::check()) {
        $userId = Auth::getUserId();
        $stmt = $pdo->prepare("SELECT id FROM user_footprints WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $id]);
        if ($stmt->fetch()) {
            $pdo->prepare("UPDATE user_footprints SET updated_at = NOW() WHERE user_id = ? AND product_id = ?")
                ->execute([$userId, $id]);
        } else {
            $pdo->prepare("INSERT INTO user_footprints (user_id, product_id) VALUES (?, ?)")
                ->execute([$userId, $id]);
        }
    }

    //转换数据类型
    $product['id'] = (int)$product['id'];
    $product['price'] = (float)$product['price'];
    $product['original_price'] = $product['original_price'] ? (float)$product['original_price'] : null;
    $product['category_id'] = $product['category_id'] ? (int)$product['category_id'] : null;
    $product['brand_id'] = $product['brand_id'] ? (int)$product['brand_id'] : null;
    $product['status'] = (int)$product['status'];
    $product['sales_count'] = (int)$product['sales_count'];
    $product['view_count'] = (int)$product['view_count'] + 1;
    $product['rating'] = (float)$product['rating'];
    $product['is_recommend'] = (int)$product['is_recommend'];
    $product['is_new'] = (int)$product['is_new'];
    $product['is_hot'] = (int)$product['is_hot'];
    $product['stock'] = (int)$product['stock'];
    $product['locked_stock'] = (int)$product['locked_stock'];
    $product['available_stock'] = $product['stock'] - $product['locked_stock'];

    // 获取商品图片列表
    $stmt = $pdo->prepare("SELECT id, image_url, sort_order, is_cover FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$id]);
    $product['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($product['images'] as &$img) {
        $img['id'] = (int)$img['id'];
        $img['sort_order'] = (int)$img['sort_order'];
        $img['is_cover'] = (int)$img['is_cover'];
    }
    unset($img);

    // 获取评价统计
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_reviews, AVG(rating) as avg_rating FROM product_reviews WHERE product_id = ? AND status = 1");
    $stmt->execute([$id]);
    $reviewStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $product['total_reviews'] = (int)$reviewStats['total_reviews'];
    $product['avg_rating'] = $reviewStats['avg_rating'] ? round((float)$reviewStats['avg_rating'], 1) : 5.0;

    // 检查当前用户是否已收藏
    $product['is_favorited'] = false;
    if (Auth::check()) {
        $stmt = $pdo->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND product_id = ?");
        $stmt->execute([Auth::getUserId(), $id]);
        $product['is_favorited'] = (bool)$stmt->fetch();
    }

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
    $subtitle = trim(Request::input('subtitle', ''));
    $keywords = trim(Request::input('keywords', ''));
    $description = trim(Request::input('description', ''));
    $coverImage = trim(Request::input('cover_image', ''));
    $price = Request::input('price', '');
    $originalPrice = Request::input('original_price', '');
    $categoryId = Request::input('category_id');
    $brandId = Request::input('brand_id');
    $status = (int)Request::input('status', 1);
    $initialStock = (int)Request::input('initial_stock', 0);
    $isRecommend = (int)Request::input('is_recommend', 0);
    $isNew = (int)Request::input('is_new', 0);
    $isHot = (int)Request::input('is_hot', 0);
    $spuNo = trim(Request::input('spu_no', ''));

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
    
    // 检查品牌ID
    $brandId = $brandId !== null && $brandId !== '' ? (int)$brandId : null;
    
    $pdo->beginTransaction();
    try {
        //输入商品
        $stmt = $pdo->prepare("INSERT INTO products (spu_no, name, subtitle, keywords, description, cover_image, price, original_price, category_id, brand_id, status, is_recommend, is_new, is_hot) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $spuNo ?: null,
            $name,
            $subtitle ?: null,
            $keywords ?: null,
            $description ?: null,
            $coverImage ?: null,
            (float)$price,
            $originalPrice !== '' ? (float)$originalPrice : null,
            $categoryId,
            $brandId,
            $status,
            $isRecommend,
            $isNew,
            $isHot
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
    $subtitle = Request::input('subtitle');
    $keywords = Request::input('keywords');
    $description = Request::input('description');
    $coverImage = Request::input('cover_image');
    $price = Request::input('price');
    $originalPrice = Request::input('original_price');
    $categoryId = Request::input('category_id');
    $brandId = Request::input('brand_id');
    $status = Request::input('status');
    $isRecommend = Request::input('is_recommend');
    $isNew = Request::input('is_new');
    $isHot = Request::input('is_hot');
    $spuNo = Request::input('spu_no');

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

    if ($subtitle !== null) {
        $updates[] = "subtitle = ?";
        $params[] = trim($subtitle) ?: null;
    }

    if ($keywords !== null) {
        $updates[] = "keywords = ?";
        $params[] = trim($keywords) ?: null;
    }

    if ($spuNo !== null) {
        $updates[] = "spu_no = ?";
        $params[] = trim($spuNo) ?: null;
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

    if ($brandId !== null) {
        $updates[] = "brand_id = ?";
        $params[] = ($brandId !== '' && $brandId !== '0') ? (int)$brandId : null;
    }

    if ($isRecommend !== null) {
        $updates[] = "is_recommend = ?";
        $params[] = (int)$isRecommend;
    }

    if ($isNew !== null) {
        $updates[] = "is_new = ?";
        $params[] = (int)$isNew;
    }

    if ($isHot !== null) {
        $updates[] = "is_hot = ?";
        $params[] = (int)$isHot;
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

        //删除库存记录
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE product_id = ?");
        $stmt->execute([$id]);

        //删除商品图片记录
        $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);

        //删除收藏记录
        $pdo->prepare("DELETE FROM user_favorites WHERE product_id = ?")->execute([$id]);

        //删除足迹记录
        $pdo->prepare("DELETE FROM user_footprints WHERE product_id = ?")->execute([$id]);

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

    // 记录库存变动日志
    $logType = match($type) { 'set' => 1, 'add' => 2, 'reduce' => 3, default => 1 };
    $stmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, type, quantity, before_stock, after_stock, operator_id, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$productId, $logType, $newStock - $currentStock, $currentStock, $newStock, Auth::getUserId(), $reason ?: null]);

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

// ============ 库存变动日志 ============

/**
 * 获取库存变动日志列表 (管理员)
 */
function handleInventoryLogList(): void
{
    Request::allowMethods('GET');
    requireAdmin();

    $pdo = getDB();

    $productId = $_GET['product_id'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int)($_GET['page_size'] ?? 20)));

    $where = [];
    $params = [];

    if ($productId !== '' && (int)$productId > 0) {
        $where[] = "il.product_id = ?";
        $params[] = (int)$productId;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // 总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_logs il {$whereClause}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $totalPages = $total > 0 ? ceil($total / $pageSize) : 0;
    $offset = ($page - 1) * $pageSize;

    // 列表
    $sql = "SELECT il.*, p.name as product_name, u.username as operator_name
            FROM inventory_logs il
            LEFT JOIN products p ON il.product_id = p.id
            LEFT JOIN users u ON il.operator_id = u.id
            {$whereClause}
            ORDER BY il.created_at DESC
            LIMIT {$pageSize} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $typeMap = [1 => '设置', 2 => '入库', 3 => '出库', 4 => '订单锁定', 5 => '订单释放', 6 => '订单扣减'];
    foreach ($logs as &$log) {
        $log['id'] = (int)$log['id'];
        $log['product_id'] = (int)$log['product_id'];
        $log['type'] = (int)$log['type'];
        $log['type_text'] = $typeMap[$log['type']] ?? '未知';
        $log['quantity'] = (int)$log['quantity'];
        $log['before_stock'] = (int)$log['before_stock'];
        $log['after_stock'] = (int)$log['after_stock'];
    }
    unset($log);

    Response::success([
        'list' => $logs,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages
        ]
    ], '获取成功');
}

// ============ 商品图片处理函数 ============

/**
 * 获取商品图片列表
 */
function handleProductImageList(): void
{
    Request::allowMethods('GET');

    $productId = (int)($_GET['product_id'] ?? 0);
    if ($productId <= 0) {
        Response::error('商品ID无效', 400);
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, product_id, image_url, sort_order, is_cover, created_at FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($images as &$img) {
        $img['id'] = (int)$img['id'];
        $img['product_id'] = (int)$img['product_id'];
        $img['sort_order'] = (int)$img['sort_order'];
        $img['is_cover'] = (int)$img['is_cover'];
    }
    unset($img);

    Response::success($images, '获取成功');
}

/**
 * 添加商品图片 (管理员)
 */
function handleProductImageAdd(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $productId = (int)Request::input('product_id', 0);
    $imageUrl = trim(Request::input('image_url', ''));
    $sortOrder = (int)Request::input('sort_order', 0);
    $isCover = (int)Request::input('is_cover', 0);

    if ($productId <= 0) {
        Response::error('商品ID无效', 400);
    }
    if (empty($imageUrl)) {
        Response::error('图片地址不能为空', 400);
    }

    $pdo = getDB();

    // 检查商品是否存在
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) {
        Response::error('商品不存在', 404);
    }

    // 如果设为封面，取消其他封面
    if ($isCover) {
        $pdo->prepare("UPDATE product_images SET is_cover = 0 WHERE product_id = ?")->execute([$productId]);
        // 同时更新商品封面图
        $pdo->prepare("UPDATE products SET cover_image = ? WHERE id = ?")->execute([$imageUrl, $productId]);
    }

    $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, sort_order, is_cover) VALUES (?, ?, ?, ?)");
    $stmt->execute([$productId, $imageUrl, $sortOrder, $isCover]);

    Response::success(['image_id' => (int)$pdo->lastInsertId()], '图片添加成功', 201);
}

/**
 * 删除商品图片 (管理员)
 */
function handleProductImageDelete(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $id = (int)Request::input('id', 0);
    if ($id <= 0) {
        Response::error('图片ID无效', 400);
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$image) {
        Response::error('图片不存在', 404);
    }

    $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$id]);

    // 如果删除的是封面图，清空商品封面或设置下一张为封面
    if ($image['is_cover']) {
        $stmt = $pdo->prepare("SELECT id, image_url FROM product_images WHERE product_id = ? ORDER BY sort_order ASC LIMIT 1");
        $stmt->execute([$image['product_id']]);
        $next = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($next) {
            $pdo->prepare("UPDATE product_images SET is_cover = 1 WHERE id = ?")->execute([$next['id']]);
            $pdo->prepare("UPDATE products SET cover_image = ? WHERE id = ?")->execute([$next['image_url'], $image['product_id']]);
        } else {
            $pdo->prepare("UPDATE products SET cover_image = NULL WHERE id = ?")->execute([$image['product_id']]);
        }
    }

    Response::success(null, '图片删除成功');
}

/**
 * 更新商品图片排序 (管理员)
 */
function handleProductImageSort(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $images = Request::input('images', []);
    if (empty($images) || !is_array($images)) {
        Response::error('请提供图片排序数据', 400);
    }

    $pdo = getDB();
    foreach ($images as $item) {
        if (isset($item['id']) && isset($item['sort_order'])) {
            $pdo->prepare("UPDATE product_images SET sort_order = ? WHERE id = ?")
                ->execute([(int)$item['sort_order'], (int)$item['id']]);
        }
    }

    Response::success(null, '排序更新成功');
}

/**
 * 设为封面图 (管理员)
 */
function handleProductImageSetCover(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $id = (int)Request::input('id', 0);
    if ($id <= 0) {
        Response::error('图片ID无效', 400);
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$image) {
        Response::error('图片不存在', 404);
    }

    // 取消当前封面
    $pdo->prepare("UPDATE product_images SET is_cover = 0 WHERE product_id = ?")->execute([$image['product_id']]);
    // 设置新封面
    $pdo->prepare("UPDATE product_images SET is_cover = 1 WHERE id = ?")->execute([$id]);
    // 更新商品封面
    $pdo->prepare("UPDATE products SET cover_image = ? WHERE id = ?")->execute([$image['image_url'], $image['product_id']]);

    Response::success(null, '已设为封面图');
}

// ============ 商品评价处理函数 ============

/**
 * 获取商品评价列表 (公开)
 */
function handleReviewList(): void
{
    Request::allowMethods('GET');

    $productId = (int)($_GET['product_id'] ?? 0);
    if ($productId <= 0) {
        Response::error('商品ID无效', 400);
    }

    $pdo = getDB();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($_GET['page_size'] ?? 10)));
    $offset = ($page - 1) * $pageSize;

    // 总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_reviews WHERE product_id = ? AND status = 1");
    $stmt->execute([$productId]);
    $total = (int)$stmt->fetchColumn();
    $totalPages = $total > 0 ? ceil($total / $pageSize) : 0;

    // 列表
    $stmt = $pdo->prepare("SELECT r.id, r.rating, r.content, r.images, r.is_anonymous, r.reply_content, r.reply_time, r.created_at,
                                  u.username
                           FROM product_reviews r
                           LEFT JOIN users u ON r.user_id = u.id
                           WHERE r.product_id = ? AND r.status = 1
                           ORDER BY r.created_at DESC
                           LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $productId, PDO::PARAM_INT);
    $stmt->bindValue(2, $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reviews as &$review) {
        $review['id'] = (int)$review['id'];
        $review['rating'] = (int)$review['rating'];
        $review['is_anonymous'] = (int)$review['is_anonymous'];
        $review['images'] = $review['images'] ? json_decode($review['images'], true) : [];
        // 匿名处理
        if ($review['is_anonymous'] && $review['username']) {
            $review['username'] = mb_substr($review['username'], 0, 1) . '***';
        }
    }
    unset($review);

    // 评价统计
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, AVG(rating) as avg_rating,
                           SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as good_count,
                           SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as mid_count,
                           SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as bad_count
                           FROM product_reviews WHERE product_id = ? AND status = 1");
    $stmt->execute([$productId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    Response::success([
        'list' => $reviews,
        'stats' => [
            'total' => (int)$stats['total'],
            'avg_rating' => $stats['avg_rating'] ? round((float)$stats['avg_rating'], 1) : 5.0,
            'good_count' => (int)$stats['good_count'],
            'mid_count' => (int)$stats['mid_count'],
            'bad_count' => (int)$stats['bad_count'],
            'good_rate' => $stats['total'] > 0 ? round((int)$stats['good_count'] / (int)$stats['total'] * 100) : 100
        ],
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages
        ]
    ], '获取成功');
}

/**
 * 提交评价 (用户)
 */
function handleReviewAdd(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $orderItemId = (int)Request::input('order_item_id', 0);
    $rating = (int)Request::input('rating', 5);
    $content = trim(Request::input('content', ''));
    $images = Request::input('images', []);
    $isAnonymous = (int)Request::input('is_anonymous', 0);

    if ($orderItemId <= 0) {
        Response::error('订单商品ID无效', 400);
    }
    if ($rating < 1 || $rating > 5) {
        Response::error('评分必须在1-5之间', 400);
    }

    $pdo = getDB();

    // 检查订单项是否属于当前用户且已完成
    $stmt = $pdo->prepare("SELECT oi.id, oi.order_id, oi.product_id, o.status, o.user_id
                           FROM order_items oi
                           JOIN orders o ON oi.order_id = o.id
                           WHERE oi.id = ?");
    $stmt->execute([$orderItemId]);
    $orderItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orderItem) {
        Response::error('订单商品不存在', 404);
    }
    if ((int)$orderItem['user_id'] !== $userId) {
        Response::error('无权评价此商品', 403);
    }
    if ((int)$orderItem['status'] !== 3) {
        Response::error('只有已完成的订单才能评价', 400);
    }

    // 检查是否已评价
    $stmt = $pdo->prepare("SELECT id FROM product_reviews WHERE order_item_id = ?");
    $stmt->execute([$orderItemId]);
    if ($stmt->fetch()) {
        Response::error('该商品已评价', 409);
    }

    $imagesJson = is_array($images) && !empty($images) ? json_encode($images) : null;

    $stmt = $pdo->prepare("INSERT INTO product_reviews (user_id, order_id, order_item_id, product_id, rating, content, images, is_anonymous) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        (int)$orderItem['order_id'],
        $orderItemId,
        (int)$orderItem['product_id'],
        $rating,
        $content ?: null,
        $imagesJson,
        $isAnonymous
    ]);

    // 更新商品评分
    $stmt = $pdo->prepare("SELECT AVG(rating) as avg FROM product_reviews WHERE product_id = ? AND status = 1");
    $stmt->execute([(int)$orderItem['product_id']]);
    $avg = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($avg['avg']) {
        $pdo->prepare("UPDATE products SET rating = ? WHERE id = ?")
            ->execute([round((float)$avg['avg'], 1), (int)$orderItem['product_id']]);
    }

    Response::success(null, '评价提交成功', 201);
}

/**
 * 管理员评价列表
 */
function handleAdminReviewList(): void
{
    Request::allowMethods('GET');
    requireAdmin();

    $pdo = getDB();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($_GET['page_size'] ?? 20)));
    $productId = $_GET['product_id'] ?? '';
    $offset = ($page - 1) * $pageSize;

    $where = [];
    $params = [];
    if ($productId !== '' && (int)$productId > 0) {
        $where[] = "r.product_id = ?";
        $params[] = (int)$productId;
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_reviews r {$whereClause}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $totalPages = $total > 0 ? ceil($total / $pageSize) : 0;

    $sql = "SELECT r.*, u.username, p.name as product_name
            FROM product_reviews r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN products p ON r.product_id = p.id
            {$whereClause}
            ORDER BY r.created_at DESC
            LIMIT {$pageSize} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reviews as &$review) {
        $review['id'] = (int)$review['id'];
        $review['rating'] = (int)$review['rating'];
        $review['status'] = (int)$review['status'];
        $review['is_anonymous'] = (int)$review['is_anonymous'];
        $review['images'] = $review['images'] ? json_decode($review['images'], true) : [];
    }
    unset($review);

    Response::success([
        'list' => $reviews,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages
        ]
    ], '获取成功');
}

/**
 * 管理员回复评价
 */
function handleAdminReviewReply(): void
{
    Request::allowMethods('POST');
    requireAdmin();

    $id = (int)Request::input('id', 0);
    $replyContent = trim(Request::input('reply_content', ''));

    if ($id <= 0) {
        Response::error('评价ID无效', 400);
    }
    if (empty($replyContent)) {
        Response::error('回复内容不能为空', 400);
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT id FROM product_reviews WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        Response::error('评价不存在', 404);
    }

    $stmt = $pdo->prepare("UPDATE product_reviews SET reply_content = ?, reply_time = NOW() WHERE id = ?");
    $stmt->execute([$replyContent, $id]);

    Response::success(null, '回复成功');
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

    //返回相对路径 (需要包含/php前缀，因为uploads在php目录下)
    $relativePath = '/php/uploads/products/' . date('Ym') . '/' . $filename;

     Response::success([
        'url' => $relativePath,
        'filename' => $filename,
        'size' => $file['size'],
        'mime_type' => $mimeType
    ], '上传成功');
}