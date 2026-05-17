import { createRouter, createWebHistory } from "vue-router";

// ==================== CORE LAYOUTS (eager load) ====================
import ClientLayout from "../layouts/ClientLayout.vue";

// ==================== HOME PAGES (eager load - trang chính) ====================
import Home from "../Pages/Client/Home/Home.vue";

// ==================== LAZY LOADED PAGES ====================

// Auth - chỉ giữ Login (cần để đăng nhập Admin)
const Login = () => import("../Pages/Client/Auth/login.vue");

// Admin (lazy load toàn bộ - chỉ tải khi admin truy cập)
const AdminLayout = () => import("../layouts/AdminLayout.vue");
const AdminHome = () => import("../Pages/admin/AdminHome.vue");
const AdminProduct = () => import("../Pages/admin/AdminProduct.vue");
const AdminCreateProduct = () => import("../Pages/admin/AdminCreateProduct.vue");
const AdminUsers = () => import("../Pages/admin/AdminUsers.vue");
const AdminCategory = () => import("../Pages/admin/AdminCategory.vue");
const AdminStaff = () => import("../Pages/admin/AdminStaff.vue");
const AdminContact = () => import("../Pages/admin/AdminContact.vue");
const AdminCoupon = () => import("../Pages/admin/AdminCoupon.vue");

const routes = [
    {
        path: "/",
        component: ClientLayout,
        children: [
            { path: "", name: "home", component: Home, meta: { title: 'Trang chủ' } },
        ],
    },
    // Auth routes - chỉ giữ Login cho Admin
    {
        path: "/client/login",
        name: "login",
        component: Login,
        meta: { guest: true, title: 'Đăng nhập' },
    },
    // Admin routes
    {
        path: "/admin",
        component: AdminLayout,
        meta: { requiresAuth: true, roles: ['admin', 'seller', 'staff'] },
        children: [
            {
                path: "",
                name: "admin",
                component: AdminHome,
                meta: { roles: ['admin'], title: 'Tổng quan' },
            },
            {
                path: "product",
                name: "admin-product",
                component: AdminProduct,
                meta: { roles: ['admin', 'staff'], title: 'Quản lý sản phẩm' },
            },
            {
                path: "product/create",
                name: "admin-product-create",
                component: AdminCreateProduct,
                meta: { roles: ['admin', 'staff'], title: 'Thêm sản phẩm' },
            },
            {
                path: "pos",
                name: "admin-pos",
                component: () => import("../Pages/admin/AdminPOS.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Bán Hàng Trực Tiếp (POS)' },
            },
            {
                path: "order",
                name: "admin-order",
                component: () => import("../Pages/admin/AdminOrder.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Quản lý Đơn hàng' },
            },
            {
                path: "order/:id",
                name: "admin-order-detail",
                component: () => import("../Pages/admin/AdminOrderDetail.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Chi tiết Đơn hàng' },
            },
            {
                path: "product/edit/:id",
                name: "admin-product-edit",
                component: () => import("../Pages/admin/AdminEditProduct.vue"),
                meta: { roles: ['admin', 'staff'], title: 'Sửa sản phẩm' },
            },
            {
                path: "users",
                name: "admin-users",
                component: AdminUsers,
                meta: { roles: ['admin', 'seller'], title: 'Quản lý khách hàng' },
            },
            {
                path: "category",
                name: "admin-category",
                component: AdminCategory,
                meta: { roles: ['admin', 'staff'], title: 'Quản lý danh mục' },
            },
            {
                path: "staff",
                name: "admin-staff",
                component: AdminStaff,
                meta: { roles: ['admin'], title: 'Quản lý nhân sự' },
            },
            {
                path: "contact",
                name: "admin-contact",
                component: AdminContact,
                meta: { roles: ['admin', 'seller'], title: 'Quản lý liên hệ' },
            },
            {
                path: "chat",
                name: "admin-chat",
                component: () => import("../Pages/admin/AdminChat.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Tin nhắn khách hàng' },
            },
            {
                path: "coupon",
                name: "admin-coupon",
                component: AdminCoupon,
                meta: { roles: ['admin'], title: 'Quản lý mã giảm giá' },
            },
            {
                path: "flash-sale",
                name: "admin-flash-sale",
                component: () => import("../Pages/admin/AdminFlashSale.vue"),
                meta: { roles: ['admin'], title: 'Quản lý Flash Sale' },
            },
            {
                path: "post",
                name: "admin-post",
                component: () => import("../Pages/admin/AdminPost.vue"),
                meta: { roles: ['admin'], title: 'Quản lý bài viết' },
            },
            {
                path: "post/create",
                name: "admin-post-create",
                component: () => import("../Pages/admin/AdminCreatePost.vue"),
                meta: { roles: ['admin'], title: 'Thêm bài viết' },
            },
            {
                path: "post/edit/:id",
                name: "admin-post-edit",
                component: () => import("../Pages/admin/AdminEditPost.vue"),
                meta: { roles: ['admin'], title: 'Sửa bài viết' },
            },
            {
                path: "post-category",
                name: "admin-post-category",
                component: () => import("../Pages/admin/AdminPostCategory.vue"),
                meta: { roles: ['admin'], title: 'Danh mục bài viết' },
            },
            {
                path: "post-category/create",
                name: "admin-post-category-create",
                component: () => import("../Pages/admin/AdminCreatePostCategory.vue"),
                meta: { roles: ['admin'], title: 'Thêm danh mục bài viết' },
            },
            {
                path: "review",
                name: "admin-review",
                component: () => import("../Pages/admin/AdminReview.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Quản lý đánh giá' },
            },
            {
                path: "attendance",
                name: "admin-attendance",
                component: () => import("../Pages/admin/AdminAttendance.vue"),
                meta: { roles: ['admin', 'seller', 'staff'], title: 'Chấm Công' },
            },
            {
                path: "stats",
                name: "admin-stats",
                component: () => import("../Pages/admin/AdminStats.vue"),
                meta: { roles: ['admin', 'staff'], title: 'Thống kê' },
            },
            {
                path: "attendance-list",
                name: "admin-attendance-list",
                component: () => import("../Pages/admin/AdminAttendanceList.vue"),
                meta: { roles: ['admin'], title: 'Danh sách Chấm công' },
            },
        ],
    },

];

const router = createRouter({
    history: createWebHistory(),
    routes,
    // Scroll to top khi navigate
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition;
        return { top: 0 };
    },
});

// ==================== Navigation Guard ====================

router.beforeEach((to, from) => {
    const token = sessionStorage.getItem('auth_token');
    const userData = sessionStorage.getItem('user');
    const user = userData ? JSON.parse(userData) : null;

    // Route yêu cầu đăng nhập
    if (to.matched.some(record => record.meta.requiresAuth)) {
        if (!token || !user) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }

        // Tự động điều hướng nếu vào trang tổng quan /admin nhưng không phải admin
        if (to.name === 'admin' && user.role !== 'admin') {
            if (user.role === 'seller') return { name: 'admin-pos' };
            if (user.role === 'staff') return { name: 'admin-product' };
        }

        // Kiểm tra role nếu route yêu cầu
        const requiredRoles = to.meta.roles || to.matched.find(r => r.meta.roles)?.meta.roles;
        if (requiredRoles && !requiredRoles.includes(user.role)) {
            return { name: 'home' };
        }
    }

    // Route dành cho guest (login) — nếu đã login thì redirect
    if (to.matched.some(record => record.meta.guest)) {
        if (token && user) {
            if (user.role === 'admin') {
                return { name: 'admin' };
            } else if (user.role === 'seller') {
                return { name: 'admin-pos' };
            } else if (user.role === 'staff') {
                return { name: 'admin-product' };
            }
            return { name: 'home' };
        }
    }
});

// ==================== Dynamic Page Title ====================
router.afterEach((to) => {
    const title = to.meta.title;
    const isAdmin = to.matched.some(record => record.path === '/admin');

    if (title) {
        document.title = isAdmin ? `${title} | Ocean Admin` : `${title} | Ocean`;
    } else {
        document.title = 'Ocean';
    }
});

export default router;
