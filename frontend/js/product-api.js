/**
 * 商品模块 API 请求封装
 */
const ProductAPI = {
    baseURL: '/php/api/product.php',

    /**
     * 发送请求
     */
    async request(action, method = 'GET', data = null, params = {}) {
        let url = `${this.baseURL}?action=${action}`;
        
        // 添加 URL 参数
        Object.keys(params).forEach(key => {
            if (params[key] !== '' && params[key] !== null && params[key] !== undefined) {
                url += `&${key}=${encodeURIComponent(params[key])}`;
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
            const response = await fetch(url, options);
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('请求失败:', error);
            return { success: false, message: '网络请求失败' };
        }
    },

    // ========== 分类接口 ==========

    async getCategoryList(flat = false, showHidden = false) {
        return this.request('category_list', 'GET', null, {
            flat: flat ? '1' : '0',
            show_hidden: showHidden ? '1' : '0'
        });
    },

    async getCategoryDetail(id) {
        return this.request('category_detail', 'GET', null, { id });
    },

    // ========== 品牌接口 ==========

    async getBrandList(showHidden = false) {
        return this.request('brand_list', 'GET', null, {
            show_hidden: showHidden ? '1' : '0'
        });
    },

    async getBrandDetail(id) {
        return this.request('brand_detail', 'GET', null, { id });
    },

    // ========== 商品接口 ==========

    async getProductList(filters = {}) {
        return this.request('product_list', 'GET', null, filters);
    },

    async getProductDetail(id) {
        return this.request('product_detail', 'GET', null, { id });
    },

    // ========== 商品图片接口 ==========

    async getProductImages(productId) {
        return this.request('product_image_list', 'GET', null, { product_id: productId });
    },

    // ========== 库存接口 ==========

    async getInventory(productId) {
        return this.request('inventory_get', 'GET', null, { product_id: productId });
    },

    // ========== 评价接口 ==========

    async getReviewList(productId, page = 1, pageSize = 10) {
        return this.request('review_list', 'GET', null, { product_id: productId, page, page_size: pageSize });
    },

    async addReview(data) {
        return this.request('review_add', 'POST', data);
    },

    // ========== 属性接口 ==========

    async getAttributeList(categoryId) {
        return this.request('attribute_list', 'GET', null, { category_id: categoryId });
    },

    // ========== SKU接口 ==========

    async getSkuList(productId) {
        return this.request('sku_list', 'GET', null, { product_id: productId });
    }
};
