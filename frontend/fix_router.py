import sys

path = "src/router/index.js"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# We need to replace the CheckoutLayout block
# which currently looks like:
#     {
#         path: "/",
#         component: CheckoutLayout,
#         children: [
# ... cart routes ...
#         ]
#     },

old_block = """    {
        path: "/",
        component: CheckoutLayout,
        children: [

            // Cart
            { path: "cart", name: "cart", component: () => import("../Pages/Client/Cart/Index.vue"), meta: { title: 'Giỏ hàng' } },
            { path: "checkout", name: "checkout", component: () => import("../Pages/Client/Cart/Checkout.vue"), meta: { title: 'Thanh toán' } },
            { path: "order-success/:order_code?", name: "order-success", component: () => import("../Pages/Client/Cart/OrderSuccess.vue"), meta: { title: 'Đặt hàng thành công' } },

            // Payment
            { path: "payment/result", name: "payment-result", component: () => import("../Pages/Client/Payment/PaymentResult.vue"), meta: { title: 'Kết quả thanh toán' } },

        ]
    },"""

new_block = """    {
        path: "/cart",
        component: CheckoutLayout,
        children: [{ path: "", name: "cart", component: () => import("../Pages/Client/Cart/Index.vue"), meta: { title: 'Giỏ hàng' } }]
    },
    {
        path: "/checkout",
        component: CheckoutLayout,
        children: [{ path: "", name: "checkout", component: () => import("../Pages/Client/Cart/Checkout.vue"), meta: { title: 'Thanh toán' } }]
    },
    {
        path: "/order-success/:order_code?",
        component: CheckoutLayout,
        children: [{ path: "", name: "order-success", component: () => import("../Pages/Client/Cart/OrderSuccess.vue"), meta: { title: 'Đặt hàng thành công' } }]
    },
    {
        path: "/payment/result",
        component: CheckoutLayout,
        children: [{ path: "", name: "payment-result", component: () => import("../Pages/Client/Payment/PaymentResult.vue"), meta: { title: 'Kết quả thanh toán' } }]
    },"""

content = content.replace(old_block, new_block)

with open(path, "w", encoding="utf-8") as f:
    f.write(content)

print("Fixed router layouts")
