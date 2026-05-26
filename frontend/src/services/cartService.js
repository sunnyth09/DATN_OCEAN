import api from "@/axios";

export const cartService = {
  getCartItems() {
    return api.get("/cart");
  },

  addToCart(data) {
    return api.post("/cart/items", data);
  },

  update(data) {
    return api.put("/cart/items", data);
  },

  delete(id) {
    return api.delete(`/cart/items/${id}`);
  },

  clear() {
    return api.delete("/cart/items");
  },
};