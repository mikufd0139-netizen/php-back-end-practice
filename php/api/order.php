<?php
/**
 * 订单模块统一入口 - 包含购物车、收货地址、订单、支付
 * 
 * === 购物车接口 ===
 * GET    ?action=cart_list                  获取购物车列表
 * POST   ?action=cart_add                   添加商品到购物车
 * POST   ?action=cart_update                更新购物车商品数量
 * POST   ?action=cart_delete                删除购物车商品
 * POST   ?action=cart_clear                 清空购物车
 * POST   ?action=cart_select                选中/取消选中商品
 * 
 * === 收货地址接口 ===
 * GET    ?action=address_list               获取收货地址列表
 * GET    ?action=address_detail&id=1        获取地址详情
 * POST   ?action=address_add                添加收货地址
 * POST   ?action=address_update             更新收货地址
 * POST   ?action=address_delete             删除收货地址
 * POST   ?action=address_set_default        设为默认地址
 * 
 * === 订单接口 ===
 * GET    ?action=order_list                 获取订单列表
 * GET    ?action=order_detail&id=1          获取订单详情
 * POST   ?action=order_create               创建订单(从购物车)
 * POST   ?action=order_direct_create         立即购买(不经过购物车)
 * POST   ?action=order_cancel               取消订单
 * POST   ?action=order_confirm              确认收货
 * POST   ?action=order_delete               删除订单
 * 
 * === 订单管理接口 (管理员) ===
 * GET    ?action=admin_order_list           管理员获取所有订单
 * GET    ?action=admin_order_detail&id=1     管理员获取订单详情
 * POST   ?action=admin_order_ship           发货
 * POST   ?action=admin_order_update_status  更新订单状态
 * 
 * === 支付接口 ===
 * POST   ?action=pay_order                  支付订单(模拟)
 * GET    ?action=payment_detail&order_id=1  获取支付信息
 */

header('Content-Type: application/json; charset=utf-8');

// 引入核心文件
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/request.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    // 购物车接口
    case 'cart_list':
        handleCartList();
        break;
    case 'cart_add':
        handleCartAdd();
        break;
    case 'cart_update':
        handleCartUpdate();
        break;
    case 'cart_delete':
        handleCartDelete();
        break;
    case 'cart_clear':
        handleCartClear();
        break;
    case 'cart_select':
        handleCartSelect();
        break;
    // 收货地址接口
    case 'address_list':
        handleAddressList();
        break;
    case 'address_detail':
        handleAddressDetail();
        break;
    case 'address_add':
        handleAddressAdd();
        break;
    case 'address_update':
        handleAddressUpdate();
        break;
    case 'address_delete':
        handleAddressDelete();
        break;
    case 'address_set_default':
        handleAddressSetDefault();
        break;
    
        // 订单接口
    case 'order_list':
        handleOrderList();
        break;
    case 'order_detail':
        handleOrderDetail();
        break;
    case 'order_create':
        handleOrderCreate();
        break;
    case 'order_direct_create':
        handleOrderDirectCreate();
        break;
    case 'order_cancel':
        handleOrderCancel();
        break;
    case 'order_confirm':
        handleOrderConfirm();
        break;
    case 'order_delete':
        handleOrderDelete();
        break;
    
        // 管理员订单接口
    case 'admin_order_list':
        handleAdminOrderList();
        break;
    case 'admin_order_detail':
        handleAdminOrderDetail();
        break;
    case 'admin_order_ship':
        handleAdminOrderShip();
        break;
    case 'admin_order_update_status':
        handleAdminOrderUpdateStatus();
        break;
    
    // 支付接口
    case 'pay_order':
        handlePayOrder();
        break;
    case 'payment_detail':
        handlePaymentDetail();
        break;
    
    default:
        Response::error('无效的操作', 400);
}

//辅助函数

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
 * 生成订单号
 */
function generateOrderNO(): string
{
    return date('YmdHis') . str_pad((string)random_int(0, 9999), 6, '0', STR_PAD_LEFT);
}

/**
 * 订单状态常量
 * 0: 待付款, 1: 待发货, 2: 待收货, 3: 已完成, 4: 已取消, 5: 已退款
 */
function getOrderStatusText(int $status): string
{
    $statusMap = [
        0 => '待付款',
        1 => '待发货',
        2 => '待收货',
        3 => '已完成',
        4 => '已取消',
        5 => '已退款'
    ];
    return $statusMap[$status] ?? '未知状态';
}

/**
 * 支付方式映射
 * 1: 支付宝, 2: 微信支付, 3: 余额支付
 */
function getPaymentMethodText(int $method): string
{
    $map = [
        1 => '支付宝',
        2 => '微信支付',
        3 => '余额支付',
    ];
    return $map[$method] ?? '未知';
}

//购物车接口

/**
 * 获取购物车列表
 */
function handleCartList(): void
{
    Request::allowMethods('GET');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $pdo = getDB();

    $sql = "SELECT c.id, c.product_id, c.sku_id, c.quantity, c.selected, c.created_at,
                   p.name, p.cover_image, p.price, p.original_price, p.status as product_status,
                   COALESCE(i.stock, 0) - COALESCE(i.locked_stock, 0) as available_stock,
                   s.sku_no, s.attr_text as sku_attr_text, s.price as sku_price, 
                   s.original_price as sku_original_price, s.cover_image as sku_cover_image,
                   s.stock as sku_stock, s.locked_stock as sku_locked_stock, s.status as sku_status
            FROM cart_items c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN inventory i ON p.id = i.product_id
            LEFT JOIN product_skus s ON c.sku_id = s.id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalAmount = 0;
    $totalQuantity = 0;

    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['product_id'] = (int)$item['product_id'];
        $item['sku_id'] = $item['sku_id'] ? (int)$item['sku_id'] : null;
        $item['quantity'] = (int)$item['quantity'];
        $item['selected'] = (int)$item['selected'];
        $item['product_status'] = (int)$item['product_status'];

        // 如果有SKU，使用SKU的价格和库存
        if ($item['sku_id']) {
            $item['price'] = (float)$item['sku_price'];
            $item['original_price'] = $item['sku_original_price'] ? (float)$item['sku_original_price'] : null;
            $item['available_stock'] = (int)$item['sku_stock'] - (int)$item['sku_locked_stock'];
            $item['cover_image'] = $item['sku_cover_image'] ?: $item['cover_image'];
            $item['sku_attr_text'] = $item['sku_attr_text'] ?: '';
            $skuValid = (int)($item['sku_status'] ?? 1) === 1;
        } else {
            $item['price'] = (float)$item['price'];
            $item['original_price'] = $item['original_price'] ? (float)$item['original_price'] : null;
            $item['available_stock'] = (int)$item['available_stock'];
            $item['sku_attr_text'] = null;
            $skuValid = true;
        }

        $item['subtotal'] = $item['price'] * $item['quantity'];
        $item['is_valid'] = $item['product_status'] == 1 && $item['available_stock'] >= $item['quantity'] && $skuValid;
        
        if ($item['is_valid'] && $item['selected'] == 1) {
            $totalAmount += $item['subtotal'];
            $totalQuantity += $item['quantity'];
        }

        // 清理临时字段
        unset($item['sku_price'], $item['sku_original_price'], $item['sku_cover_image'], 
              $item['sku_stock'], $item['sku_locked_stock'], $item['sku_status'], $item['sku_no']);
    }
    unset($item); // 解除引用

    Response::success([
        'items' => $items,
        'total_amount' => round($totalAmount, 2),
        'total_quantity' => $totalQuantity,
        'item_count' => count($items)
    ], '获取购物车列表成功');
}

/**
 * 添加商品到购物车
 */
function handleCartAdd(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $productId = (int)Request::input('product_id', 0);
    $skuId = Request::input('sku_id');
    $skuId = ($skuId !== null && $skuId !== '' && $skuId !== 0) ? (int)$skuId : null;
    $quantity = (int)Request::input('quantity', 1);

    if ($productId <= 0) {
        Response::error('无效的商品ID', 400);
    }
    if ($quantity <= 0) {
        Response::error('数量必须大于0', 400);
    }

    $pdo = getDB();

    //检查商品是否存在且上架
    $stmt = $pdo->prepare("SELECT p.id, p.name, p.status,
                                  COALESCE(i.stock, 0) - COALESCE(i.locked_stock, 0) as available_stock
                           FROM products p
                           LEFT JOIN inventory i ON p.id = i.product_id
                           WHERE p.id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        Response::error('商品不存在', 404);
    }
    if ($product['status'] != 1) {
        Response::error('商品已下架', 400);
    }

    // 如果有SKU，校验SKU的库存
    $availableStock = (int)$product['available_stock'];
    if ($skuId) {
        $stmt = $pdo->prepare("SELECT id, stock, locked_stock, status FROM product_skus WHERE id = ? AND product_id = ?");
        $stmt->execute([$skuId, $productId]);
        $skuInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$skuInfo) {
            Response::error('SKU不存在', 404);
        }
        if ((int)$skuInfo['status'] !== 1) {
            Response::error('该规格已下架', 400);
        }
        $availableStock = (int)$skuInfo['stock'] - (int)$skuInfo['locked_stock'];
    }

    //检查购物车中是否已有该商品+SKU组合
    if ($skuId) {
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND sku_id = ?");
        $stmt->execute([$userId, $productId, $skuId]);
    } else {
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND sku_id IS NULL");
        $stmt->execute([$userId, $productId]);
    }
    $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);

    $newQuantity = $cartItem ? $cartItem['quantity'] + $quantity : $quantity;

    //检查库存
    if ($newQuantity > $availableStock) {
        Response::error("库存不足，当前可用数量为 {$availableStock}", 400);
    }

    if ($cartItem) {
        //更新数量
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
        $stmt->execute([$newQuantity, $cartItem['id']]);
    } else {
        //新增
        $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, sku_id, quantity) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $productId, $skuId, $quantity]);
    }

    Response::success(['quantity' => $newQuantity], '添加成功');
}

/**
 * 更新购物车商品数量
 */
function handleCartUpdate(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $cartId = (int)Request::input('id', 0);
    $quantity = (int)Request::input('quantity', 0);
    
    if ($cartId <= 0) {
        Response::error('购物车项ID无效', 400);
    }
    if ($quantity <= 0) {
        Response::error('数量必须大于0', 400);
    }
    
    $pdo = getDB();
    
    // 检查购物车项是否存在
    $stmt = $pdo->prepare("SELECT c.*, 
                                  COALESCE(i.stock, 0) - COALESCE(i.locked_stock, 0) as available_stock,
                                  s.stock as sku_stock, s.locked_stock as sku_locked_stock
                           FROM cart_items c
                           JOIN products p ON c.product_id = p.id
                           LEFT JOIN inventory i ON p.id = i.product_id
                           LEFT JOIN product_skus s ON c.sku_id = s.id
                           WHERE c.id = ? AND c.user_id = ?");
    $stmt->execute([$cartId, $userId]);
    $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cartItem) {
        Response::error('购物车项不存在', 404);
    }
    
    // 检查库存（优先使用SKU库存）
    $availableStock = $cartItem['sku_id'] 
        ? (int)$cartItem['sku_stock'] - (int)$cartItem['sku_locked_stock']
        : (int)$cartItem['available_stock'];
    if ($quantity > $availableStock) {
        Response::error("库存不足，当前可购买数量为 {$availableStock}", 400);
    }
    
    $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
    $stmt->execute([$quantity, $cartId]);
    
    Response::success(null, '更新成功');
}

/**
 * 删除购物车商品
 */
function handleCartDelete(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $cartId = Request::input('id');
    $productId = Request::input('product_id');
    
    $pdo = getDB();
    
    if ($cartId) {
        // 按购物车ID删除
        $ids = is_array($cartId) ? $cartId : [$cartId];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id IN ({$placeholders}) AND user_id = ?");
        $stmt->execute(array_merge($ids, [$userId]));
    } elseif ($productId) {
        // 按商品ID删除
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE product_id = ? AND user_id = ?");
        $stmt->execute([$productId, $userId]);
    } else {
        Response::error('请指定要删除的购物车项', 400);
    }
    
    Response::success(null, '删除成功');
}

/**
 * 清空购物车
 */
function handleCartClear(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $pdo = getDB();
    
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    Response::success(null, '购物车已清空');
}

/**
 * 购物车选中/取消选中
 */
function handleCartSelect(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $cartId = Request::input('cart_id');     // 单个购物车项ID
    $selected = Request::input('selected');  // 1选中 0取消
    $selectAll = Request::input('select_all'); // 全选/全不选

    $pdo = getDB();

    if ($selectAll !== null) {
        // 全选/全不选
        $stmt = $pdo->prepare("UPDATE cart_items SET selected = ? WHERE user_id = ?");
        $stmt->execute([(int)$selectAll, $userId]);
        Response::success(null, (int)$selectAll ? '已全选' : '已取消全选');
    }

    if ($cartId === null || $selected === null) {
        Response::error('参数不完整', 400);
    }

    $stmt = $pdo->prepare("UPDATE cart_items SET selected = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$selected, (int)$cartId, $userId]);

    Response::success(null, '更新成功');
}

// 收货地址接口

/**
 * 获取收货地址列表
 */
function handleAddressList(): void
{
    Request::allowMethods('GET');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->execute([$userId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($addresses as &$addr) {
        $addr['id'] = (int)$addr['id'];
        $addr['user_id'] = (int)$addr['user_id'];
        $addr['is_default'] = (int)$addr['is_default'];
        $addr['full_address'] = $addr['province'] . $addr['city'] . $addr['region'] . $addr['detail_address'];
    }
    unset($addr);
    
    Response::success($addresses, '获取成功');
}

/**
 * 获取地址详情
 */
function handleAddressDetail(): void
{
    Request::allowMethods('GET');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        Response::error('地址ID无效', 400);
    }
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$address) {
        Response::error('地址不存在', 404);
    }
    
    $address['id'] = (int)$address['id'];
    $address['is_default'] = (int)$address['is_default'];
    $address['full_address'] = $address['province'] . $address['city'] . $address['region'] . $address['detail_address'];
    
    Response::success($address, '获取成功');
}

/**
 * 添加收货地址
 */
function handleAddressAdd(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    
    $name = trim(Request::input('name', ''));
    $phone = trim(Request::input('phone', ''));
    $province = trim(Request::input('province', ''));
    $city = trim(Request::input('city', ''));
    $region = trim(Request::input('region', ''));
    $detailAddress = trim(Request::input('detail_address', ''));
    $isDefault = (int)Request::input('is_default', 0);
    
    // 验证
    if (empty($name)) {
        Response::error('收货人姓名不能为空', 400);
    }
    if (empty($phone)) {
        Response::error('手机号不能为空', 400);
    }
    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        Response::error('手机号格式不正确', 400);
    }
    if (empty($province) || empty($city) || empty($region)) {
        Response::error('请选择完整的地区', 400);
    }
    if (empty($detailAddress)) {
        Response::error('详细地址不能为空', 400);
    }
    
    $pdo = getDB();
    
    // 如果设为默认，先取消其他默认地址
    if ($isDefault) {
        $stmt = $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
    
    // 检查是否是第一个地址，如果是则自动设为默认
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM addresses WHERE user_id = ?");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() == 0) {
        $isDefault = 1;
    }
    
    $stmt = $pdo->prepare("INSERT INTO addresses (user_id, name, phone, province, city, region, detail_address, is_default) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $name, $phone, $province, $city, $region, $detailAddress, $isDefault]);
    
    Response::success(['address_id' => (int)$pdo->lastInsertId()], '添加成功', 201);
}

/**
 * 更新收货地址
 */
function handleAddressUpdate(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $id = (int)Request::input('id', 0);
    
    if ($id <= 0) {
        Response::error('地址ID无效', 400);
    }
    
    $pdo = getDB();
    
    // 检查地址是否存在
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$address) {
        Response::error('地址不存在', 404);
    }
    
    $name = trim(Request::input('name', $address['name']));
    $phone = trim(Request::input('phone', $address['phone']));
    $province = trim(Request::input('province', $address['province']));
    $city = trim(Request::input('city', $address['city']));
    $region = trim(Request::input('region', $address['region']));
    $detailAddress = trim(Request::input('detail_address', $address['detail_address']));
    $isDefault = Request::input('is_default');
    
    // 验证
    if (empty($name)) {
        Response::error('收货人姓名不能为空', 400);
    }
    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        Response::error('手机号格式不正确', 400);
    }
    
    // 如果设为默认，先取消其他默认地址
    if ($isDefault !== null && $isDefault) {
        $stmt = $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ? AND id != ?");
        $stmt->execute([$userId, $id]);
    }
    
    $stmt = $pdo->prepare("UPDATE addresses SET name = ?, phone = ?, province = ?, city = ?, region = ?, 
                           detail_address = ?, is_default = ? WHERE id = ?");
    $stmt->execute([
        $name, $phone, $province, $city, $region, $detailAddress,
        $isDefault !== null ? (int)$isDefault : $address['is_default'],
        $id
    ]);
    
    Response::success(null, '更新成功');
}

/**
 * 删除收货地址
 */
function handleAddressDelete(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $id = (int)Request::input('id', 0);
    
    if ($id <= 0) {
        Response::error('地址ID无效', 400);
    }
    
    $pdo = getDB();
    
    // 检查地址是否存在
    $stmt = $pdo->prepare("SELECT is_default FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$address) {
        Response::error('地址不存在', 404);
    }
    
    $stmt = $pdo->prepare("DELETE FROM addresses WHERE id = ?");
    $stmt->execute([$id]);
    
    // 如果删除的是默认地址，将第一个地址设为默认
    if ($address['is_default']) {
        $stmt = $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE user_id = ? ORDER BY created_at ASC LIMIT 1");
        $stmt->execute([$userId]);
    }
    
    Response::success(null, '删除成功');
}

/**
 * 设为默认地址
 */function handleAddressSetDefault(): void
 {
    Request::allowMethods('POST');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $id = (int)Request::input('id', 0);

    if ($id <= 0) {
        Response::error('地址ID无效', 400);
    }

    $pdo = getDB();

    // 检查地址是否存在
    $stmt = $pdo->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    if (!$stmt->fetch()) {
        Response::error('地址不存在', 404);
    }

    //取消其他默认
    $stmt = $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
    $stmt->execute([$userId]);

    // 设为默认
    $stmt = $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE id = ?");
    $stmt->execute([$id]);

    Response::success(null, '设置成功');
 }

 //订单接口

 /**
 * 获取订单列表
 */
function handleOrderList(): void
{
    Request::allowMethods('GET');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $pdo = getDB();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($_GET['page_size'] ?? 10)));
    $status = $_GET['status'] ?? '';

    $where = ["o.user_id = ?"];
    $params = [$userId];

    if ($status !== '') {
        $where[] = "o.status = ?";
        $params[] = (int)$status;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    //获取总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o {$whereClause}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = $total > 0 ? ceil($total / $pageSize) : 0;
    $offset = ($page - 1) * $pageSize;

    //获取订单列表
    $sql = "SELECT o.* FROM orders o {$whereClause} ORDER BY o.created_at DESC LIMIT {$pageSize} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //获取订单商品
    foreach ($orders as &$order) {
        $order['id'] = (int)$order['id'];
        $order['user_id'] = (int)$order['user_id'];
        $order['total_amount'] = (float)$order['total_amount'];
        $order['status'] = (int)$order['status'];
        $order['status_text'] = getOrderStatusText($order['status']);
        $order['snap_address'] = $order['snap_address'] ? json_decode($order['snap_address'], true) : null;
        
        //获取订单商品
        $stmt = $pdo->prepare("SELECT oi.*, p.cover_image 
                               FROM order_items oi 
                               LEFT JOIN products p ON oi.product_id = p.id 
                               WHERE oi.order_id = ?");
        $stmt->execute([$order['id']]);
        $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($order['items'] as &$item) {
            $item['id'] = (int)$item['id'];
            $item['product_id'] = (int)$item['product_id'];
            $item['price'] = (float)$item['price'];
            $item['quantity'] = (int)$item['quantity'];
            $item['subtotal'] = $item['price'] * $item['quantity'];
        }
        unset($item);

        $order['items_count'] = count($order['items']);
    }
    unset($order);

    Response::success([
        'list' => $orders,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages
        ]
        ], '获取成功');
}

/**
 * 获取订单详情
 */
function handleOrderDetail(): void
{
    Request::allowMethods('GET');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $id = (int)($_GET['id'] ?? 0);
    $orderNo = $_GET['order_no'] ?? '';

    if ($id <= 0 && empty($orderNo)) {
        Response::error('请提供订单ID或订单号', 400);
    }

    $pdo = getDB();

     if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_no = ? AND user_id = ?");
        $stmt->execute([$orderNo, $userId]);
    }
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        Response::error('订单不存在', 404);
    }

    $order['id'] = (int)$order['id'];
    $order['user_id'] = (int)$order['user_id'];
    $order['total_amount'] = (float)$order['total_amount'];
    $order['status'] = (int)$order['status'];
    $order['status_text'] = getOrderStatusText($order['status']);
    $order['snap_address'] = $order['snap_address'] ? json_decode($order['snap_address'], true) : null;
    
    //获取订单商品
     $stmt = $pdo->prepare("SELECT oi.*, p.cover_image, p.status as product_status,
                                   (SELECT COUNT(*) FROM product_reviews pr WHERE pr.product_id = oi.product_id AND pr.order_item_id = oi.id AND pr.user_id = ?) as is_reviewed
                           FROM order_items oi 
                           LEFT JOIN products p ON oi.product_id = p.id 
                           WHERE oi.order_id = ?");
    $stmt->execute([$userId, $order['id']]);
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($order['items'] as &$item) {
        $item['id'] = (int)$item['id'];
        $item['product_id'] = (int)$item['product_id'];
        $item['sku_id'] = $item['sku_id'] ? (int)$item['sku_id'] : null;
        $item['sku_attr_text'] = $item['sku_attr_text'] ?? '';
        $item['price'] = (float)$item['price'];
        $item['quantity'] = (int)$item['quantity'];
        $item['subtotal'] = $item['price'] * $item['quantity'];
        $item['is_reviewed'] = (int)$item['is_reviewed'] > 0;
    }
    unset($item);
    
    // 获取支付信息
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$order['id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        $payment['id'] = (int)$payment['id'];
        $payment['amount'] = (float)$payment['amount'];
        $payment['status'] = (int)$payment['status'];
        $payment['payment_method'] = (int)$payment['payment_method'];
        $payment['payment_method_text'] = getPaymentMethodText((int)$payment['payment_method']);
        $order['payment'] = $payment;
    }
    
    Response::success($order, '获取成功');
}

/**
 * 创建订单
 */
function handleOrderCreate(): void
{
     Request::allowMethods('POST');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $addressId = (int)Request::input('address_id', 0);
    $cartIds = Request::input('cart_ids'); // 可选，指定购物车项；不传则使用全部
    $remark = trim(Request::input('remark', ''));
    
    if ($addressId <= 0) {
        Response::error('请选择收货地址', 400);
    }
    
    $pdo = getDB();
    
    //验证收获地址
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$addressId, $userId]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$address) {
        Response::error('收货地址不存在', 400);
    }
    
    //获取购物车商品（JOIN SKU表获取SKU价格/库存/图片）
     $sql = "SELECT c.id as cart_id, c.product_id, c.sku_id, c.quantity,
                   p.name, p.price, p.cover_image, p.status as product_status,
                   COALESCE(i.stock, 0) - COALESCE(i.locked_stock, 0) as inv_available_stock,
                   s.price as sku_price, s.stock as sku_stock, s.locked_stock as sku_locked_stock,
                   s.cover_image as sku_cover, s.attr_text as sku_attr_text, s.status as sku_status
            FROM cart_items c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN inventory i ON p.id = i.product_id
            LEFT JOIN product_skus s ON c.sku_id = s.id
            WHERE c.user_id = ?";
    
    $params = [$userId];
    
    if ($cartIds && is_array($cartIds) && count($cartIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
        $sql .= " AND c.id IN ({$placeholders})";
        $params = array_merge($params, $cartIds);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cartItems)) {
        Response::error('购物车为空', 400);
    }
    
    //验证商品状态和库存
    $totalAmount = 0;
    $orderItems = [];
    $cartIdsToDelete = [];
    
    foreach ($cartItems as $item) {
        if ($item['product_status'] != 1) {
            Response::error("商品「{$item['name']}」已下架", 400);
        }
        
        // SKU存在时验证SKU状态
        if ($item['sku_id']) {
            if ($item['sku_status'] != 1) {
                Response::error("商品「{$item['name']}」规格已下架", 400);
            }
            $availableStock = (int)$item['sku_stock'] - (int)$item['sku_locked_stock'];
        } else {
            $availableStock = (int)$item['inv_available_stock'];
        }
        
        if ($item['quantity'] > $availableStock) {
            Response::error("商品「{$item['name']}」库存不足", 400);
        }
        
        // 使用SKU价格/图片（如有）
        $actualPrice = $item['sku_id'] ? (float)$item['sku_price'] : (float)$item['price'];
        $actualImage = ($item['sku_id'] && $item['sku_cover']) ? $item['sku_cover'] : $item['cover_image'];
        
        $subtotal = $actualPrice * $item['quantity'];
        $totalAmount += $subtotal;
        
        $orderItems[] = [
            'product_id' => $item['product_id'],
            'sku_id' => $item['sku_id'] ? (int)$item['sku_id'] : null,
            'product_name' => $item['name'],
            'product_image' => $actualImage,
            'price' => $actualPrice,
            'quantity' => $item['quantity'],
            'sku_attr_text' => $item['sku_attr_text'] ?: ''
        ];
        
        $cartIdsToDelete[] = $item['cart_id'];
    }
    
    //开始事务
    $pdo->beginTransaction();

    try {
        // 创建订单
        $orderNo = generateOrderNo();
        $snapAddress = json_encode([
            'name' => $address['name'],
            'phone' => $address['phone'],
            'province' => $address['province'],
            'city' => $address['city'],
            'region' => $address['region'],
            'detail_address' => $address['detail_address'],
            'full_address' => $address['province'] . $address['city'] . $address['region'] . $address['detail_address']
        ], JSON_UNESCAPED_UNICODE);
        
        $stmt = $pdo->prepare("INSERT INTO orders (order_no, user_id, total_amount, status, snap_address, remark) VALUES (?, ?, ?, 0, ?, ?)");
        $stmt->execute([$orderNo, $userId, $totalAmount, $snapAddress, $remark ?: null]);
        $orderId = (int)$pdo->lastInsertId();
        
        // 创建订单商品（含sku_id和sku_attr_text）
         $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, sku_id, product_name, product_image, price, quantity, sku_attr_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($orderItems as $item) {
            $stmt->execute([$orderId, $item['product_id'], $item['sku_id'], $item['product_name'], $item['product_image'], $item['price'], $item['quantity'], $item['sku_attr_text']]);
        }
        
        // 锁定库存（优先锁定SKU库存，同时锁定inventory库存）
        $logStmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, type, quantity, before_stock, after_stock, order_no, reason) VALUES (?, 4, ?, ?, ?, ?, '订单锁定库存')");
        foreach ($cartItems as $item) {
            if ($item['sku_id']) {
                // 锁定SKU库存
                $stk = $pdo->prepare("SELECT stock FROM product_skus WHERE id = ?");
                $stk->execute([$item['sku_id']]);
                $beforeStock = (int)($stk->fetchColumn() ?: 0);

                $stmt = $pdo->prepare("UPDATE product_skus SET locked_stock = locked_stock + ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['sku_id']]);

                // 同时记录库存日志
                $logStmt->execute([$item['product_id'], $item['quantity'], $beforeStock, $beforeStock, $orderNo]);
            } else {
                // 无SKU时锁定inventory库存
                $stk = $pdo->prepare("SELECT stock FROM inventory WHERE product_id = ?");
                $stk->execute([$item['product_id']]);
                $beforeStock = (int)($stk->fetchColumn() ?: 0);

                $stmt = $pdo->prepare("UPDATE inventory SET locked_stock = locked_stock + ? WHERE product_id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);

                // 记录库存日志
                $logStmt->execute([$item['product_id'], $item['quantity'], $beforeStock, $beforeStock, $orderNo]);
            }
        }

        // 删除购物车项
         $placeholders = implode(',', array_fill(0, count($cartIdsToDelete), '?'));
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id IN ({$placeholders})");
        $stmt->execute($cartIdsToDelete);
        
        $pdo->commit();
        
        Response::success([
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'total_amount' => $totalAmount
        ], '订单创建成功', 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        Response::error('订单创建失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 立即购买 - 直接创建订单（不经过购物车）
 * 参数：product_id, quantity, sku_id(可选), address_id, remark(可选)
 */
function handleOrderDirectCreate(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();

    $userId = Auth::getUserId();
    $productId = (int)Request::input('product_id', 0);
    $skuId = Request::input('sku_id');
    $skuId = $skuId ? (int)$skuId : null;
    $quantity = (int)Request::input('quantity', 1);
    $addressId = (int)Request::input('address_id', 0);
    $remark = trim(Request::input('remark', ''));

    if ($productId <= 0) {
        Response::error('商品ID无效', 400);
    }
    if ($quantity <= 0) {
        Response::error('购买数量必须大于0', 400);
    }
    if ($addressId <= 0) {
        Response::error('请选择收货地址', 400);
    }

    $pdo = getDB();

    // 验证收货地址
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$addressId, $userId]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$address) {
        Response::error('收货地址不存在', 400);
    }

    // 查询商品信息
    $stmt = $pdo->prepare("SELECT p.*, COALESCE(i.stock, 0) as inv_stock, COALESCE(i.locked_stock, 0) as inv_locked_stock
                           FROM products p
                           LEFT JOIN inventory i ON p.id = i.product_id
                           WHERE p.id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        Response::error('商品不存在', 404);
    }
    if ($product['status'] != 1) {
        Response::error('商品已下架', 400);
    }

    // 确定价格、库存、封面图、规格文字
    $actualPrice = (float)$product['price'];
    $actualImage = $product['cover_image'];
    $skuAttrText = '';

    if ($skuId) {
        $stmt = $pdo->prepare("SELECT * FROM product_skus WHERE id = ? AND product_id = ?");
        $stmt->execute([$skuId, $productId]);
        $sku = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sku) {
            Response::error('商品规格不存在', 404);
        }
        if ($sku['status'] != 1) {
            Response::error('商品规格已下架', 400);
        }

        $availableStock = (int)$sku['stock'] - (int)($sku['locked_stock'] ?? 0);
        if ($quantity > $availableStock) {
            Response::error("库存不足，当前可购买 {$availableStock} 件", 400);
        }

        $actualPrice = (float)$sku['price'];
        $skuAttrText = $sku['attr_text'] ?? '';
        if (!empty($sku['cover_image'])) {
            $actualImage = $sku['cover_image'];
        }
    } else {
        $availableStock = (int)$product['inv_stock'] - (int)$product['inv_locked_stock'];
        if ($quantity > $availableStock) {
            Response::error("库存不足，当前可购买 {$availableStock} 件", 400);
        }
    }

    $totalAmount = round($actualPrice * $quantity, 2);

    $pdo->beginTransaction();

    try {
        // 创建订单
        $orderNo = generateOrderNO();
        $snapAddress = json_encode([
            'name' => $address['name'],
            'phone' => $address['phone'],
            'province' => $address['province'],
            'city' => $address['city'],
            'region' => $address['region'],
            'detail_address' => $address['detail_address'],
            'full_address' => $address['province'] . $address['city'] . $address['region'] . $address['detail_address']
        ], JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("INSERT INTO orders (order_no, user_id, total_amount, status, snap_address, remark) VALUES (?, ?, ?, 0, ?, ?)");
        $stmt->execute([$orderNo, $userId, $totalAmount, $snapAddress, $remark ?: null]);
        $orderId = (int)$pdo->lastInsertId();

        // 创建订单商品
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, sku_id, product_name, product_image, price, quantity, sku_attr_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$orderId, $productId, $skuId, $product['name'], $actualImage, $actualPrice, $quantity, $skuAttrText]);

        // 锁定库存
        $logStmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, type, quantity, before_stock, after_stock, order_no, reason) VALUES (?, 4, ?, ?, ?, ?, '订单锁定库存')");

        if ($skuId) {
            $stk = $pdo->prepare("SELECT stock FROM product_skus WHERE id = ?");
            $stk->execute([$skuId]);
            $beforeStock = (int)($stk->fetchColumn() ?: 0);

            $stmt = $pdo->prepare("UPDATE product_skus SET locked_stock = locked_stock + ? WHERE id = ?");
            $stmt->execute([$quantity, $skuId]);

            $logStmt->execute([$productId, $quantity, $beforeStock, $beforeStock, $orderNo]);
        } else {
            $stk = $pdo->prepare("SELECT stock FROM inventory WHERE product_id = ?");
            $stk->execute([$productId]);
            $beforeStock = (int)($stk->fetchColumn() ?: 0);

            $stmt = $pdo->prepare("UPDATE inventory SET locked_stock = locked_stock + ? WHERE product_id = ?");
            $stmt->execute([$quantity, $productId]);

            $logStmt->execute([$productId, $quantity, $beforeStock, $beforeStock, $orderNo]);
        }

        $pdo->commit();

        Response::success([
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'total_amount' => $totalAmount
        ], '订单创建成功', 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        Response::error('订单创建失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 取消订单
 */
function handleOrderCancel(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $orderId = (int)Request::input('order_id', 0);
    
    if ($orderId <= 0) {
        Response::error('订单ID无效', 400);
    }
    
    $pdo = getDB();
    
    //检查订单
     $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        Response::error('订单不存在', 404);
    }
    
    if ($order['status'] != 0) {
        Response::error('只能取消待付款的订单', 400);
    }
    
    $pdo->beginTransaction();
    
    try {
        // 更新订单状态
        $stmt = $pdo->prepare("UPDATE orders SET status = 4 WHERE id = ?");
        $stmt->execute([$orderId]);
        
        // 释放库存（区分SKU和普通库存）
        $stmt = $pdo->prepare("SELECT product_id, sku_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $logStmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, type, quantity, before_stock, after_stock, order_no, reason) VALUES (?, 5, ?, ?, ?, ?, '订单取消释放库存')");
        foreach ($items as $item) {
            if ($item['sku_id']) {
                // 释放SKU库存
                $stk = $pdo->prepare("SELECT stock FROM product_skus WHERE id = ?");
                $stk->execute([$item['sku_id']]);
                $currentStock = (int)($stk->fetchColumn() ?: 0);

                $stmt = $pdo->prepare("UPDATE product_skus SET locked_stock = GREATEST(0, locked_stock - ?) WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['sku_id']]);

                $logStmt->execute([$item['product_id'], $item['quantity'], $currentStock, $currentStock, $order['order_no']]);
            } else {
                // 释放inventory库存
                $stk = $pdo->prepare("SELECT stock FROM inventory WHERE product_id = ?");
                $stk->execute([$item['product_id']]);
                $currentStock = (int)($stk->fetchColumn() ?: 0);

                $stmt = $pdo->prepare("UPDATE inventory SET locked_stock = GREATEST(0, locked_stock - ?) WHERE product_id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);

                $logStmt->execute([$item['product_id'], $item['quantity'], $currentStock, $currentStock, $order['order_no']]);
            }
        }
        
        $pdo->commit();
        
        Response::success(null, '订单已取消');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        Response::error('取消失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 确认收货
 */
function handleOrderConfirm(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $orderId = (int)Request::input('order_id', 0);
    
    if ($orderId <= 0) {
        Response::error('订单ID无效', 400);
    }
    
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        Response::error('订单不存在', 404);
    }
    
    if ($order['status'] != 2) {
        Response::error('只能确认待收货的订单', 400);
    }
    
    $stmt = $pdo->prepare("UPDATE orders SET status = 3, complete_time = NOW() WHERE id = ?");
    $stmt->execute([$orderId]);

    // 更新商品销量
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as $item) {
        $pdo->prepare("UPDATE products SET sales_count = sales_count + ? WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
    }
    
    Response::success(null, '确认收货成功');
}

/**
 * 删除订单
 */
function handleOrderDelete(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $orderId = (int)Request::input('order_id', 0);
    
    if ($orderId <= 0) {
        Response::error('订单ID无效', 400);
    }
    
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        Response::error('订单不存在', 404);
    }
    
    // 只能删除已完成或已取消的订单
    if (!in_array($order['status'], [3, 4, 5])) {
        Response::error('只能删除已完成、已取消或已退款的订单', 400);
    }
    
    $pdo->beginTransaction();
    
    try {
        // 删除订单商品
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        // 删除支付记录
        $stmt = $pdo->prepare("DELETE FROM payments WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        // 删除订单
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        
        $pdo->commit();
        
        Response::success(null, '订单已删除');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        Response::error('删除失败: ' . $e->getMessage(), 500);
    }
}

//管理员订单接口

/**
 * 管理员获取订单列表
 */
/**
 * 管理员获取订单详情（不限制user_id）
 */
function handleAdminOrderDetail(): void
{
    Request::allowMethods('GET');
    requireAdmin();

    $id = (int)($_GET['id'] ?? 0);
    $orderNo = $_GET['order_no'] ?? '';

    if ($id <= 0 && empty($orderNo)) {
        Response::error('请提供订单ID或订单号', 400);
    }

    $pdo = getDB();

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.order_no = ?");
        $stmt->execute([$orderNo]);
    }

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        Response::error('订单不存在', 404);
    }

    $order['id'] = (int)$order['id'];
    $order['user_id'] = (int)$order['user_id'];
    $order['total_amount'] = (float)$order['total_amount'];
    $order['status'] = (int)$order['status'];
    $order['status_text'] = getOrderStatusText($order['status']);
    $order['snap_address'] = $order['snap_address'] ? json_decode($order['snap_address'], true) : null;

    // 获取订单商品
    $stmt = $pdo->prepare("SELECT oi.*, p.cover_image, p.status as product_status
                           FROM order_items oi
                           LEFT JOIN products p ON oi.product_id = p.id
                           WHERE oi.order_id = ?");
    $stmt->execute([$order['id']]);
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($order['items'] as &$item) {
        $item['id'] = (int)$item['id'];
        $item['product_id'] = (int)$item['product_id'];
        $item['sku_id'] = $item['sku_id'] ? (int)$item['sku_id'] : null;
        $item['sku_attr_text'] = $item['sku_attr_text'] ?? '';
        $item['price'] = (float)$item['price'];
        $item['quantity'] = (int)$item['quantity'];
        $item['subtotal'] = $item['price'] * $item['quantity'];
    }
    unset($item);

    // 获取支付信息
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$order['id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payment) {
        $payment['id'] = (int)$payment['id'];
        $payment['amount'] = (float)$payment['amount'];
        $payment['status'] = (int)$payment['status'];
        $payment['payment_method'] = (int)$payment['payment_method'];
        $payment['payment_method_text'] = getPaymentMethodText((int)$payment['payment_method']);
        $order['payment'] = $payment;
    }

    Response::success($order, '获取成功');
}

function handleAdminOrderList(): void
{
    Request::allowMethods('GET');
    requireAdmin();

    $pdo = getDB();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int)($_GET['page_size'] ?? 20)));
    $status = $_GET['status'] ?? '';
    $keyword = trim($_GET['keyword'] ?? '');
    $userId = $_GET['user_id'] ?? '';
    
    $where = [];
    $params = [];

    if ($status !== '') {
        $where[] = "o.status = ?";
        $params[] = (int)$status;
    }

    if ($keyword !== '') {
        $where[] = "(o.order_no LIKE ? OR o.snap_address LIKE ?)";
        $params[] = "%{$keyword}%";
        $params[] = "%{$keyword}%";
    }

    if ($userId !== '') {
        $where[] = "o.user_id = ?";
        $params[] = (int)$userId;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    //获取总数
     $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id {$whereClause}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = $total > 0 ? ceil($total / $pageSize) : 0;
    $offset = ($page - 1) * $pageSize;

    //获取订单列表
    $sql = "SELECT o.*, u.username 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            {$whereClause} 
            ORDER BY o.created_at DESC 
            LIMIT {$pageSize} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($orders as &$order) {
        $order['id'] = (int)$order['id'];
        $order['user_id'] = (int)$order['user_id'];
        $order['total_amount'] = (float)$order['total_amount'];
        $order['status'] = (int)$order['status'];
        $order['status_text'] = getOrderStatusText($order['status']);
        $order['snap_address'] = $order['snap_address'] ? json_decode($order['snap_address'], true) : null;
        
        //获取订单商品数量
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $order['item_count'] = (int)$stmt->fetchColumn();
    }
    unset($order);
    
    //获取各状态订单数量
     $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $statusCounts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statusCounts[$row['status']] = (int)$row['count'];
    }
    
    Response::success([
        'list' => $orders,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages
        ],
        'status_counts' => $statusCounts
    ], '获取成功');
}

/**
 * 管理员发货
 */
function handleAdminOrderShip(): void
{
     Request::allowMethods('POST');
    requireAdmin();
    
    $orderId = (int)Request::input('order_id', 0);
    
    if ($orderId <= 0) {
        Response::error('订单ID无效', 400);
    }
    
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        Response::error('订单不存在', 404);
    }
    
    if ($order['status'] != 1) {
        Response::error('只能对待发货的订单进行发货操作', 400);
    }
    
    $stmt = $pdo->prepare("UPDATE orders SET status = 2, ship_time = NOW() WHERE id = ?");
    $stmt->execute([$orderId]);
    
    Response::success(null, '发货成功');
}

/**
 * 管理员更新订单状态
 */
function handleAdminOrderUpdateStatus(): void
{
    Request::allowMethods('POST');
    requireAdmin();
    
    $orderId = (int)Request::input('order_id', 0);
    $status = Request::input('status');
    
    if ($orderId <= 0) {
        Response::error('订单ID无效', 400);
    }
    if ($status === null || !in_array((int)$status, [0, 1, 2, 3, 4, 5])) {
        Response::error('无效的订单状态', 400);
    }
    
    $status = (int)$status;
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        Response::error('订单不存在', 404);
    }
    
    $oldStatus = (int)$order['status'];
    
    $pdo->beginTransaction();
    
    try {
        // 更新订单状态
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $orderId]);
        
        // 如果取消或退款，释放库存
        if (in_array($status, [4, 5]) && !in_array($oldStatus, [4, 5])) {
            $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmt->execute([$orderId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                $stmt = $pdo->prepare("UPDATE inventory SET locked_stock = GREATEST(0, locked_stock - ?) WHERE product_id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
        }
        
        $pdo->commit();

         Response::success([
            'status' => $status,
            'status_text' => getOrderStatusText($status)
        ], '状态更新成功');
    } catch (Exception $e) {
        $pdo->rollBack();
        Response::error('状态更新失败: ' . $e->getMessage(), 500);
    }
}

//支付接口

/**
 * 支付订单（模拟支付）
 */
function handlePayOrder(): void
{
    Request::allowMethods('POST');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $orderId = (int)Request::input('order_id', 0);
    $paymentMethodInput = trim(Request::input('payment_method', 'alipay'));
    
    if ($orderId <= 0) {
        Response::error('订单ID无效', 400);
    }
    
    // 支付方式映射：字符串 -> 整数
    $methodMap = [
        'alipay'  => 1,  // 支付宝
        'wechat'  => 2,  // 微信支付
        'balance' => 3,  // 余额支付
    ];
    $methodNameMap = [
        1 => '支付宝',
        2 => '微信支付',
        3 => '余额支付',
    ];
    
    // 支持传入字符串或整数
    if (is_numeric($paymentMethodInput)) {
        $paymentMethod = (int)$paymentMethodInput;
    } else {
        $paymentMethod = $methodMap[$paymentMethodInput] ?? 0;
    }
    
    if (!isset($methodNameMap[$paymentMethod])) {
        Response::error('不支持的支付方式', 400);
    }
    
    $pdo = getDB();
    
    //检查订单
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        Response::error('订单不存在', 404);
    }
    
    if ($order['status'] != 0) {
        Response::error('订单状态不允许支付', 400);
    }
    
    $pdo->beginTransaction();
    
    try {
        // 生成交易号
        $transactionId = 'PAY' . date('YmdHis') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        
        // 创建支付记录
        $stmt = $pdo->prepare("INSERT INTO payments (order_id, transaction_id, payment_method, amount, status, pay_time) 
                               VALUES (?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$orderId, $transactionId, $paymentMethod, $order['total_amount']]);
        
        // 更新订单状态为待发货
        $stmt = $pdo->prepare("UPDATE orders SET status = 1, pay_time = NOW() WHERE id = ?");
        $stmt->execute([$orderId]);
        
        // 扣减库存（将锁定库存转为实际扣减，区分SKU和普通库存）
        $stmt = $pdo->prepare("SELECT product_id, sku_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $logStmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, type, quantity, before_stock, after_stock, order_no, reason) VALUES (?, 2, ?, ?, ?, ?, '支付扣减库存')");
        foreach ($items as $item) {
            if ($item['sku_id']) {
                // 扣减SKU库存
                $stk = $pdo->prepare("SELECT stock FROM product_skus WHERE id = ?");
                $stk->execute([$item['sku_id']]);
                $beforeStock = (int)($stk->fetchColumn() ?: 0);

                $stmt = $pdo->prepare("UPDATE product_skus SET stock = stock - ?, locked_stock = GREATEST(0, locked_stock - ?) WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['quantity'], $item['sku_id']]);

                $logStmt->execute([$item['product_id'], $item['quantity'], $beforeStock, $beforeStock - (int)$item['quantity'], $order['order_no']]);
            } else {
                // 扣减inventory库存
                $stk = $pdo->prepare("SELECT stock FROM inventory WHERE product_id = ?");
                $stk->execute([$item['product_id']]);
                $beforeStock = (int)($stk->fetchColumn() ?: 0);

                $stmt = $pdo->prepare("UPDATE inventory SET stock = stock - ?, locked_stock = GREATEST(0, locked_stock - ?) WHERE product_id = ?");
                $stmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);

                $logStmt->execute([$item['product_id'], $item['quantity'], $beforeStock, $beforeStock - (int)$item['quantity'], $order['order_no']]);
            }
        }
        
        $pdo->commit();
        
        Response::success([
            'transaction_id' => $transactionId,
            'payment_method' => $paymentMethod,
            'payment_method_text' => $methodNameMap[$paymentMethod],
            'amount' => (float)$order['total_amount'],
            'status' => 1,
            'status_text' => '支付成功'
        ], '支付成功');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        Response::error('支付失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取支付信息
 */
function handlePaymentDetail(): void
{
     Request::allowMethods('GET');
    Auth::requireLogin();
    
    $userId = Auth::getUserId();
    $orderId = (int)($_GET['order_id'] ?? 0);
    
    if ($orderId <= 0) {
        Response::error('订单ID无效', 400);
    }
    
    $pdo = getDB();
    
    // 验证订单归属
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    if (!$stmt->fetch()) {
        Response::error('订单不存在', 404);
    }
    
    // 获取支付记录
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC");
    $stmt->execute([$orderId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($payments as &$payment) {
        $payment['id'] = (int)$payment['id'];
        $payment['order_id'] = (int)$payment['order_id'];
        $payment['amount'] = (float)$payment['amount'];
        $payment['status'] = (int)$payment['status'];
        $payment['payment_method'] = (int)$payment['payment_method'];
        $payment['payment_method_text'] = getPaymentMethodText((int)$payment['payment_method']);
        $payment['status_text'] = $payment['status'] == 1 ? '支付成功' : '未支付';
    }
    unset($payment);
    
    Response::success($payments, '获取成功');
}