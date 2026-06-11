import api from "@/axios";

export const productService = {
    async getProducts() {
        const res = await api.get('/products');
        return res.data.data;
    },

    async getProductRelated() {
        const res = await api.get('/products?limit=8&sort=newest');
        return res.data.data;
    },
}