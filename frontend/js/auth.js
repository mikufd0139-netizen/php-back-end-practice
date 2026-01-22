/**
 * 登录状态管理
 * 在浏览器端保存一些用户信息（用于显示，安全验证仍在后端）
 */
const Auth = {
    /**
     * 保存用户信息到本地
     */
    setUser(user) {
        localStorage.setItem('user', JSON.stringify(user));
    },

    /**
     * 获取本地保存的用户信息
     */
    getUser() {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user) : null;
    },

    /**
     * 清除本地用户信息
     */
    clearUser() {
        localStorage.removeItem('user');
    },

    /**
     * 检查是否有本地用户信息
     */
    isLoggedIn() {
        return this.getUser() !== null;
    },

    /**
     * 要求登录（未登录则跳转）
     */
    requireLogin() {
        if (!this.isLoggedIn()) {
            window.location.href = 'login.html';
            return false;
        }
        return true;
    },

    /**
     * 要求未登录（已登录则跳转）
     */
    requireGuest() {
        if (this.isLoggedIn()) {
            window.location.href = 'profile.html';
            return false;
        }
        return true;
    }
};
