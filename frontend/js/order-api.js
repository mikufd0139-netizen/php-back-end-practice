/**
 * 订单模块 API 请求封装
 * 包含购物车、收货地址、订单、支付
 */
const OrderAPI = {
    baseURL: '/php/api/order.php',

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
            headers: { 'Content-Type': 'application/json' },
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

    // ========== 购物车接口 ==========

    async getCartList() {
        return this.request('cart_list', 'GET');
    },

    async addToCart(productId, quantity = 1) {
        return this.request('cart_add', 'POST', { product_id: productId, quantity });
    },

    async updateCartItem(id, quantity) {
        return this.request('cart_update', 'POST', { id, quantity });
    },

    async deleteCartItem(id) {
        return this.request('cart_delete', 'POST', { id });
    },

    async clearCart() {
        return this.request('cart_clear', 'POST');
    },

    async selectCartItem(cartId, selected) {
        return this.request('cart_select', 'POST', { cart_id: cartId, selected });
    },

    async selectAllCart(selectAll) {
        return this.request('cart_select', 'POST', { select_all: selectAll });
    },

    // ========== 收货地址接口 ==========

    async getAddressList() {
        return this.request('address_list', 'GET');
    },

    async getAddressDetail(id) {
        return this.request('address_detail', 'GET', null, { id });
    },

    async addAddress(data) {
        return this.request('address_add', 'POST', data);
    },

    async updateAddress(data) {
        return this.request('address_update', 'POST', data);
    },

    async deleteAddress(id) {
        return this.request('address_delete', 'POST', { id });
    },

    async setDefaultAddress(id) {
        return this.request('address_set_default', 'POST', { id });
    },

    // ========== 订单接口 ==========

    async getOrderList(params = {}) {
        return this.request('order_list', 'GET', null, params);
    },

    async getOrderDetail(id) {
        return this.request('order_detail', 'GET', null, { id });
    },

    async createOrder(addressId, cartIds = null, remark = '') {
        const data = { address_id: addressId, remark };
        if (cartIds) data.cart_ids = cartIds;
        return this.request('order_create', 'POST', data);
    },

    async cancelOrder(orderId) {
        return this.request('order_cancel', 'POST', { order_id: orderId });
    },

    async confirmOrder(orderId) {
        return this.request('order_confirm', 'POST', { order_id: orderId });
    },

    async deleteOrder(orderId) {
        return this.request('order_delete', 'POST', { order_id: orderId });
    },

    // ========== 支付接口 ==========

    async payOrder(orderId, paymentMethod = 'alipay') {
        return this.request('pay_order', 'POST', { order_id: orderId, payment_method: paymentMethod });
    },

    async getPaymentDetail(orderId) {
        return this.request('payment_detail', 'GET', null, { order_id: orderId });
    }
};
