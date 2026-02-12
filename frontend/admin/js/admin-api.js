/**
 * 管理员后台 API 封装
 */
const AdminAPI = {
    // 用户模块 API
    userURL: '/php/api/user.php',
    // 管理员模块 API
    adminURL: '/php/api/admin.php',
    // 商品模块 API
    productURL: '/php/api/product.php',
    // 订单模块 API
    orderURL: '/php/api/order.php',

    /**
     * 发送请求
     */
    async request(url, action, method = 'GET', data = null, params = {}) {
        let fullURL = `${url}?action=${action}`;
        
        // 添加URL参数
        Object.keys(params).forEach(key => {
            if (params[key] !== '' && params[key] !== null && params[key] !== undefined) {
                fullURL += `&${key}=${encodeURIComponent(params[key])}`;
            }
        });
        
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        };

        if (data && method === 'POST') {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(fullURL, options);
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('请求失败:', error);
            return { success: false, message: '网络请求失败' };
        }
    },

    // ========== 用户认证相关 ==========

    async login(account, password) {
        return this.request(this.userURL, 'login', 'POST', { account, password });
    },

    async logout() {
        return this.request(this.userURL, 'logout', 'POST');
    },

    async getProfile() {
        return this.request(this.userURL, 'profile', 'GET');
    },

    // ========== 用户管理 ==========

    async getUserCount() {
        return this.request(this.adminURL, 'user_count', 'GET');
    },

    async getUsers(page = 1, limit = 10) {
        return this.request(this.adminURL, 'users', 'GET', null, { page, limit });
    },

    async disableUser(userId) {
        return this.request(this.adminURL, 'disable', 'POST', { user_id: userId });
    },

    async enableUser(userId) {
        return this.request(this.adminURL, 'enable', 'POST', { user_id: userId });
    },

    // ========== 分类管理 ==========

    async getCategoryList(flat = true, showHidden = true) {
        return this.request(this.productURL, 'category_list', 'GET', null, {
            flat: flat ? '1' : '0',
            show_hidden: showHidden ? '1' : '0'
        });
    },

    async getCategoryDetail(id) {
        return this.request(this.productURL, 'category_detail', 'GET', null, { id });
    },

    async addCategory(data) {
        return this.request(this.productURL, 'category_add', 'POST', data);
    },

    async updateCategory(data) {
        return this.request(this.productURL, 'category_update', 'POST', data);
    },

    async deleteCategory(id) {
        return this.request(this.productURL, 'category_delete', 'POST', { id });
    },

    // ========== 商品管理 ==========

    async getProductList(filters = {}) {
        return this.request(this.productURL, 'product_list', 'GET', null, filters);
    },

    async getProductDetail(id) {
        return this.request(this.productURL, 'product_detail', 'GET', null, { id });
    },

    async addProduct(data) {
        return this.request(this.productURL, 'product_add', 'POST', data);
    },

    async updateProduct(data) {
        return this.request(this.productURL, 'product_update', 'POST', data);
    },

    async deleteProduct(id) {
        return this.request(this.productURL, 'product_delete', 'POST', { id });
    },

    async toggleProductStatus(id) {
        return this.request(this.productURL, 'product_toggle_status', 'POST', { id });
    },

    // ========== 库存管理 ==========

    async getInventory(productId) {
        return this.request(this.productURL, 'inventory_get', 'GET', null, { product_id: productId });
    },

    async updateInventory(productId, quantity, type = 'set', reason = '') {
        return this.request(this.productURL, 'inventory_update', 'POST', {
            product_id: productId,
            quantity: quantity,
            type: type,
            reason: reason
        });
    },

    // ========== 图片上传 ==========

    async uploadImage(file) {
        const formData = new FormData();
        formData.append('image', file);

        try {
            const response = await fetch(`${this.productURL}?action=upload_image`, {
                method: 'POST',
                credentials: 'include',
                body: formData
            });
            return await response.json();
        } catch (error) {
            console.error('上传失败:', error);
            return { success: false, message: '上传失败' };
        }
    },

    // ========== 品牌管理 ==========

    async getBrandList(showHidden = true) {
        return this.request(this.productURL, 'brand_list', 'GET', null, {
            show_hidden: showHidden ? '1' : '0'
        });
    },

    async getBrandDetail(id) {
        return this.request(this.productURL, 'brand_detail', 'GET', null, { id });
    },

    async addBrand(data) {
        return this.request(this.productURL, 'brand_add', 'POST', data);
    },

    async updateBrand(data) {
        return this.request(this.productURL, 'brand_update', 'POST', data);
    },

    async deleteBrand(id) {
        return this.request(this.productURL, 'brand_delete', 'POST', { id });
    },

    // ========== 商品图片管理 ==========

    async getProductImages(productId) {
        return this.request(this.productURL, 'product_image_list', 'GET', null, { product_id: productId });
    },

    async addProductImage(data) {
        return this.request(this.productURL, 'product_image_add', 'POST', data);
    },

    async deleteProductImage(id) {
        return this.request(this.productURL, 'product_image_delete', 'POST', { id });
    },

    async setProductImageCover(id) {
        return this.request(this.productURL, 'product_image_set_cover', 'POST', { id });
    },

    // ========== 库存日志 ==========

    async getInventoryLogs(params = {}) {
        return this.request(this.productURL, 'inventory_log_list', 'GET', null, params);
    },

    // ========== 评价管理 ==========

    async getAdminReviewList(params = {}) {
        return this.request(this.productURL, 'admin_review_list', 'GET', null, params);
    },

    async replyReview(id, replyContent) {
        return this.request(this.productURL, 'admin_review_reply', 'POST', { id, reply_content: replyContent });
    },

    // ========== 订单管理 ==========

    async getAdminOrderList(params = {}) {
        return this.request(this.orderURL, 'admin_order_list', 'GET', null, params);
    },

    async getAdminOrderDetail(id) {
        return this.request(this.orderURL, 'admin_order_detail', 'GET', null, { id });
    },

    async shipOrder(orderId) {
        return this.request(this.orderURL, 'admin_order_ship', 'POST', { order_id: orderId });
    },

    async updateOrderStatus(orderId, status) {
        return this.request(this.orderURL, 'admin_order_update_status', 'POST', { order_id: orderId, status: status });
    },

    // ========== 属性管理 ==========

    async getAttributeList(categoryId) {
        return this.request(this.productURL, 'attribute_list', 'GET', null, { category_id: categoryId });
    },

    async addAttribute(data) {
        return this.request(this.productURL, 'attribute_add', 'POST', data);
    },

    async updateAttribute(data) {
        return this.request(this.productURL, 'attribute_update', 'POST', data);
    },

    async deleteAttribute(id) {
        return this.request(this.productURL, 'attribute_delete', 'POST', { id });
    },

    async addAttributeValue(data) {
        return this.request(this.productURL, 'attribute_value_add', 'POST', data);
    },

    async updateAttributeValue(data) {
        return this.request(this.productURL, 'attribute_value_update', 'POST', data);
    },

    async deleteAttributeValue(id) {
        return this.request(this.productURL, 'attribute_value_delete', 'POST', { id });
    },

    // ========== SKU管理 ==========

    async getSkuList(productId) {
        return this.request(this.productURL, 'sku_list', 'GET', null, { product_id: productId });
    },

    async addSku(data) {
        return this.request(this.productURL, 'sku_add', 'POST', data);
    },

    async updateSku(data) {
        return this.request(this.productURL, 'sku_update', 'POST', data);
    },

    async deleteSku(id) {
        return this.request(this.productURL, 'sku_delete', 'POST', { id });
    },

    async batchSaveSku(productId, skuList) {
        return this.request(this.productURL, 'sku_batch_save', 'POST', { product_id: productId, sku_list: skuList });
    }
};

/**
 * 管理员登录状态管理
 */
const AdminAuth = {
    setUser(user) {
        localStorage.setItem('admin_user', JSON.stringify(user));
    },

    getUser() {
        const user = localStorage.getItem('admin_user');
        return user ? JSON.parse(user) : null;
    },

    clearUser() {
        localStorage.removeItem('admin_user');
    },

    isLoggedIn() {
        return this.getUser() !== null;
    },

    requireLogin() {
        if (!this.isLoggedIn()) {
            window.location.href = 'login.html';
            return false;
        }
        return true;
    }
};
