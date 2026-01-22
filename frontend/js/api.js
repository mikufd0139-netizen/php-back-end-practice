/**
 * API 请求封装
 * 统一处理所有与后端的通信
 */
const API = {
    // API 基础路径（后端在 php 文件夹里）
    baseURL: '/php/api/user.php',

    /**
     * 发送请求
     * @param {string} action - 操作类型 (register, login, logout, profile)
     * @param {string} method - 请求方法 (GET, POST)
     * @param {object} data - 请求数据
     * @returns {Promise}
     */
    async request(action, method = 'GET', data = null) {
        const url = `${this.baseURL}?action=${action}`;
        
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include' // 重要：携带 Cookie（Session）
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

    /**
     * 用户注册
     */
    async register(username, password, email = '', phone = '') {
        return this.request('register', 'POST', { username, password, email, phone });
    },

    /**
     * 用户登录
     */
    async login(account, password) {
        return this.request('login', 'POST', { account, password });
    },

    /**
     * 用户登出
     */
    async logout() {
        return this.request('logout', 'POST');
    },

    /**
     * 获取用户信息
     */
    async getProfile() {
        return this.request('profile', 'GET');
    }
};
