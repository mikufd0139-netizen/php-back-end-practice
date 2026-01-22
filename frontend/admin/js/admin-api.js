/**
 * 管理员后台 API 封装
 */
const AdminAPI = {
    // 用户模块 API
    userURL: '/php/api/user.php',
    // 管理员模块 API
    adminURL: '/php/api/admin.php',

    /**
     * 发送请求
     */
    async request(url, action, method = 'GET', data = null) {
        const fullURL = `${url}?action=${action}`;
        
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

    /**
     * 管理员登录
     */
    async login(account, password) {
        return this.request(this.userURL, 'login', 'POST', { account, password });
    },

    /**
     * 登出
     */
    async logout() {
        return this.request(this.userURL, 'logout', 'POST');
    },

    /**
     * 获取当前用户信息
     */
    async getProfile() {
        return this.request(this.userURL, 'profile', 'GET');
    },

    // ========== 管理员功能 ==========

    /**
     * 获取用户统计
     */
    async getUserCount() {
        return this.request(this.adminURL, 'user_count', 'GET');
    },

    /**
     * 获取用户列表
     */
    async getUsers(page = 1, limit = 10) {
        const url = `${this.adminURL}?action=users&page=${page}&limit=${limit}`;
        const options = {
            method: 'GET',
            credentials: 'include'
        };
        
        try {
            const response = await fetch(url, options);
            return await response.json();
        } catch (error) {
            return { success: false, message: '网络请求失败' };
        }
    },

    /**
     * 禁用用户
     */
    async disableUser(userId) {
        return this.request(this.adminURL, 'disable', 'POST', { user_id: userId });
    },

    /**
     * 启用用户
     */
    async enableUser(userId) {
        return this.request(this.adminURL, 'enable', 'POST', { user_id: userId });
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
