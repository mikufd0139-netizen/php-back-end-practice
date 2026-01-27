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

    /**
     * 获取分类列表
     * @param {boolean} flat - 是否扁平结构
     * @param {boolean} showHidden - 是否显示隐藏分类
     */
    async getCategoryList(flat = false, showHidden = false) {
        return this.request('category_list', 'GET', null, {
            flat: flat ? '1' : '0',
            show_hidden: showHidden ? '1' : '0'
        });
    },

    /**
     * 获取分类详情
     */
    async getCategoryDetail(id) {
        return this.request('category_detail', 'GET', null, { id });
    },

    // ========== 商品接口 ==========

    /**
     * 获取商品列表
     * @param {object} filters - 筛选参数
     */
    async getProductList(filters = {}) {
        return this.request('product_list', 'GET', null, filters);
    },

    /**
     * 获取商品详情
     */
    async getProductDetail(id) {
        return this.request('product_detail', 'GET', null, { id });
    },

    // ========== 库存接口 ==========

    /**
     * 获取库存信息
     */
    async getInventory(productId) {
        return this.request('inventory_get', 'GET', null, { product_id: productId });
    }
};
