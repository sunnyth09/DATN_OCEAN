import sys

path = "src/router/index.js"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

import_client = 'import ClientLayout from "../layouts/ClientLayout.vue";'
import_checkout = 'import CheckoutLayout from "../layouts/CheckoutLayout.vue";'

if import_checkout not in content:
    content = content.replace(import_client, import_client + "\n" + import_checkout)

cart_routes = """
            // Cart
            { path: "cart", name: "cart", component: () => import("../Pages/Client/Cart/Index.vue"), meta: { title: 'Giỏ hàng' } },
            { path: "checkout", name: "checkout", component: () => import("../Pages/Client/Cart/Checkout.vue"), meta: { title: 'Thanh toán' } },
            { path: "order-success/:order_code?", name: "order-success", component: () => import("../Pages/Client/Cart/OrderSuccess.vue"), meta: { title: 'Đặt hàng thành công' } },
"""
payment_routes = """
            // Payment
            { path: "payment/result", name: "payment-result", component: () => import("../Pages/Client/Payment/PaymentResult.vue"), meta: { title: 'Kết quả thanh toán' } },
"""

if cart_routes in content:
    content = content.replace(cart_routes, "")
if payment_routes in content:
    content = content.replace(payment_routes, "")

new_checkout_block = """
    {
        path: "/",
        component: CheckoutLayout,
        children: [
""" + cart_routes + payment_routes + """
        ]
    },
"""

content = content.replace(
    "    {\n        path: \"/\",\n        component: ClientLayout,",
    new_checkout_block + "    {\n        path: \"/\",\n        component: ClientLayout,"
)

with open(path, "w", encoding="utf-8") as f:
    f.write(content)

print("Patched router")
