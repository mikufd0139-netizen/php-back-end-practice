/**
 * API 请求封装
 * 统一处理所有与后端的通信
 */
const API = {
    // API 基础路径（后端在 php 文件夹里）
    baseURL: '/php/api/user.php',

    /**
     * 发送请求
     */
    async request(action, method = 'GET', data = null, params = {}) {
        let url = `${this.baseURL}?action=${action}`;

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
            return { success: false, message: '网络请求失败，请检查网络连接' };
        }
    },

    // ========== 用户认证 ==========

    async register(username, password, email = '', phone = '') {
        return this.request('register', 'POST', { username, password, email, phone });
    },

    async login(account, password) {
        return this.request('login', 'POST', { account, password });
    },

    async logout() {
        return this.request('logout', 'POST');
    },

    async getProfile() {
        return this.request('profile', 'GET');
    },

    // ========== 收藏接口 ==========

    async getFavoriteList(page = 1, pageSize = 20) {
        return this.request('favorite_list', 'GET', null, { page, page_size: pageSize });
    },

    async addFavorite(productId) {
        return this.request('favorite_add', 'POST', { product_id: productId });
    },

    async deleteFavorite(productId) {
        return this.request('favorite_delete', 'POST', { product_id: productId });
    },

    async checkFavorite(productId) {
        return this.request('favorite_check', 'GET', null, { product_id: productId });
    },

    // ========== 足迹接口 ==========

    async getFootprintList(page = 1, pageSize = 20) {
        return this.request('footprint_list', 'GET', null, { page, page_size: pageSize });
    },

    async clearFootprints() {
        return this.request('footprint_clear', 'POST');
    },

    async deleteFootprint(productId) {
        return this.request('footprint_delete', 'POST', { product_id: productId });
    }
};
