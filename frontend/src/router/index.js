import { createRouter, createWebHistory } from "vue-router";
import { pinia } from '@/stores';
import { useAuthStore } from '@/stores/auth';

// ==================== CORE LAYOUTS (eager load) ====================
import ClientLayout from "../layouts/ClientLayout.vue";

// ==================== HOME PAGES (eager load - trang chính) ====================
import Home from "../Pages/Client/Home/Home.vue";

// ==================== LAZY LOADED PAGES ====================

// Auth
const Login = () => import("../Pages/Client/Auth/login.vue");
const Register = () => import("../Pages/Client/Auth/Register.vue");
const Forgot = () => import("../Pages/Client/Auth/Forgot.vue");
const GoogleCallback = () => import("../Pages/Client/Auth/GoogleCallback.vue");
const FacebookCallback = () => import("../Pages/Client/Auth/FacebookCallback.vue");

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
            // Product pages
            { path: "product", name: "product-list", component: () => import("../Pages/Client/Home/Product.vue"), meta: { title: 'Sản phẩm' } },
            { path: "product/:id", name: "product-detail", component: () => import("../Pages/Client/Home/productDetail.vue"), meta: { title: 'Chi tiết sản phẩm' } },
            // Flash Sale & Coupon
            { path: "flash-sale", name: "flash-sale", component: () => import("../Pages/Client/Home/FlashSale.vue"), meta: { title: 'Flash Sale' } },
            { path: "coupon", name: "coupon", component: () => import("../Pages/Client/Home/Coupon.vue"), meta: { title: 'Mã giảm giá' } },
            // Cart
            { path: "cart", name: "cart", component: () => import("../Pages/Client/Cart/Index.vue"), meta: { requiresAuth: true, title: 'Giỏ hàng' } },
            { path: "checkout", name: "checkout", component: () => import("../Pages/Client/Cart/Checkout.vue"), meta: { requiresAuth: true, title: 'Thanh toán' } },
            { path: "order-success/:order_code?", name: "order-success", component: () => import("../Pages/Client/Cart/OrderSuccess.vue"), meta: { requiresAuth: true, title: 'Đặt hàng thành công' } },
            // Payment
            { path: "payment/result", name: "payment-result", component: () => import("../Pages/Client/Payment/PaymentResult.vue"), meta: { title: 'Kết quả thanh toán' } },
            // Profile
            {
                path: "profile",
                component: () => import("../Pages/Client/Profile/ProfileLayout.vue"),
                meta: { requiresAuth: true },
                children: [
                    { path: "", name: "profile-info", component: () => import("../Pages/Client/Profile/ProfileInfo.vue"), meta: { title: 'Thông tin cá nhân' } },
                    { path: "orders", name: "profile-orders", component: () => import("../Pages/Client/Profile/ProfileOrders.vue"), meta: { title: 'Đơn hàng' } },
                    { path: "orders/:id", name: "profile-order-detail", component: () => import("../Pages/Client/Profile/ProfileOrderDetail.vue"), meta: { title: 'Chi tiết đơn hàng' } },
                    { path: "return-requests", name: "profile-return-requests", component: () => import("../Pages/Client/Profile/ProfileReturnRequests.vue"), meta: { title: 'Yêu cầu hoàn hàng' } },
                    { path: "return-requests/:id", name: "profile-return-request-detail", component: () => import("../Pages/Client/Profile/ProfileReturnRequestDetail.vue"), meta: { title: 'Chi tiết yêu cầu hoàn hàng' } },
                    { path: "addresses", name: "profile-address", component: () => import("../Pages/Client/Profile/ProfileAddress.vue"), meta: { title: 'Địa chỉ' } },
                    { path: "change-password", name: "profile-change-password", component: () => import("../Pages/Client/Profile/ProfileChangePassword.vue"), meta: { title: 'Đổi mật khẩu' } },
                    { path: "wishlist", name: "profile-wishlist", component: () => import("../Pages/Client/Profile/ProfileWishlist.vue"), meta: { title: 'Yêu thích' } },
                    { path: "coupons", name: "profile-coupons", component: () => import("../Pages/Client/Profile/ProfileCoupon.vue"), meta: { title: 'Mã giảm giá của tôi' } },
                    { path: "affiliate", name: "profile-affiliate", component: () => import("../Pages/Client/Profile/ProfileAffiliate.vue"), meta: { title: 'Affiliate' } },
                    { path: "court-bookings", name: "profile-court-bookings", component: () => import("../Pages/Client/Courts/UserBookings.vue"), meta: { title: 'Lịch sử đặt sân' } },
                    { path: "notifications", name: "profile-notifications", component: () => import("../Pages/Client/Profile/ProfileNotifications.vue"), meta: { title: 'Thông báo' } },
                ],
            },
            // Static pages
            { path: "brand-story", name: "brand-story", component: () => import("../Pages/Client/Static/BrandStory.vue"), meta: { title: 'Câu chuyện thương hiệu' } },
            { path: "careers", name: "careers", component: () => import("../Pages/Client/Static/Careers.vue"), meta: { title: 'Tuyển dụng' } },
            { path: "contact", name: "contact", component: () => import("../Pages/Client/Static/Contact.vue"), meta: { title: 'Liên hệ' } },
            { path: "faq", name: "faq", component: () => import("../Pages/Client/Static/FAQ.vue"), meta: { title: 'Câu hỏi thường gặp' } },
            { path: "privacy", name: "privacy", component: () => import("../Pages/Client/Static/Privacy.vue"), meta: { title: 'Chính sách bảo mật' } },
            { path: "return-policy", name: "return-policy", component: () => import("../Pages/Client/Static/ReturnPolicy.vue"), meta: { title: 'Chính sách đổi trả' } },
            { path: "shopping-guide", name: "shopping-guide", component: () => import("../Pages/Client/Static/ShoppingGuide.vue"), meta: { title: 'Hướng dẫn mua hàng' } },
            { path: "terms", name: "terms", component: () => import("../Pages/Client/Static/Terms.vue"), meta: { title: 'Điều khoản dịch vụ' } },
            // Court Booking Pages
            { path: "courts", name: "courts-list", component: () => import("../Pages/Client/Courts/CourtsList.vue"), meta: { title: 'Đặt sân cầu lông' } },
            { path: "courts/:id", name: "court-detail", component: () => import("../Pages/Client/Courts/CourtDetail.vue"), meta: { title: 'Chi tiết sân' } },

        ],
    },
    // Auth routes
    {
        path: "/client/login",
        name: "login",
        component: Login,
        meta: { guest: true, title: 'Đăng nhập' },
    },
    {
        path: "/client/register",
        name: "register",
        component: Register,
        meta: { guest: true, title: 'Đăng ký' },
    },
    {
        path: "/client/forgot-password",
        name: "forgot-password",
        component: Forgot,
        meta: { guest: true, title: 'Quên mật khẩu' },
    },
    // OAuth Callback routes
    {
        path: "/api/auth/google/callback",
        name: "google-callback",
        component: GoogleCallback,
        meta: { title: 'Đăng nhập Google' },
    },
    {
        path: "/api/auth/facebook/callback",
        name: "facebook-callback",
        component: FacebookCallback,
        meta: { title: 'Đăng nhập Facebook' },
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
                path: "return-requests",
                name: "admin-return-requests",
                component: () => import("../Pages/admin/AdminReturnRequests.vue"),
                meta: { roles: ['admin'], title: 'Yêu cầu hoàn hàng' },
            },
            {
                path: "return-requests/:id",
                name: "admin-return-request-detail",
                component: () => import("../Pages/admin/AdminReturnRequestDetail.vue"),
                meta: { roles: ['admin'], title: 'Chi tiết yêu cầu hoàn hàng' },
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
                path: "tickets",
                name: "admin-tickets",
                component: () => import("../Pages/admin/AdminTicketList.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Quản lý khiếu nại' },
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
            {
                path: "work-locations",
                name: "admin-work-locations",
                component: () => import("../Pages/admin/AdminWorkLocations.vue"),
                meta: { roles: ['admin'], title: 'Quản lý vị trí làm việc' },
            },
            {
                path: "work-shifts",
                name: "admin-work-shifts",
                component: () => import("../Pages/admin/AdminWorkShifts.vue"),
                meta: { roles: ['admin'], title: 'Ca làm việc & Phân ca' },
            },
            // Court Booking Admin Pages
            {
                path: "courts",
                name: "admin-courts",
                component: () => import("../Pages/admin/AdminCourtManagement.vue"),
                meta: { roles: ['admin', 'staff'], title: 'Quản lý Hệ thống Sân' },
            },
            {
                path: "court-bookings",
                name: "admin-court-bookings",
                component: () => import("../Pages/admin/AdminBookingManagement.vue"),
                meta: { roles: ['admin', 'staff', 'seller'], title: 'Quản lý Đặt Sân' },
            },
            {
                path: "court-dashboard",
                name: "admin-court-dashboard",
                component: () => import("../Pages/admin/AdminCourtDashboard.vue"),
                meta: { roles: ['admin', 'staff', 'seller'], title: 'Dashboard Lễ Tân' },
            },
            {
                path: "court-reports",
                name: "admin-court-reports",
                component: () => import("../Pages/admin/AdminCourtReports.vue"),
                meta: { roles: ['admin'], title: 'Thống Kê Sân' },
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
        
        // Không cuộn lên đầu trang nếu chỉ thay đổi query param trên cùng một route (ví dụ: lọc, phân trang)
        if (to.path === from.path) {
            return false;
        }
        
        return { top: 0 };
    },
});

// ==================== Navigation Guard ====================

router.beforeEach((to, from) => {
    const authStore = useAuthStore(pinia);
    if (!authStore.isHydrated) {
        authStore.hydrate();
    }

    const token = authStore.token;
    const user = authStore.user;

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
            return authStore.preferredRoute;
        }
    }
});

// ==================== Dynamic Page Title ====================
router.afterEach((to) => {
    const title = to.meta.title;
    const isAdmin = to.matched.some(record => record.path === '/admin');

    if (title) {
        document.title = isAdmin ? `${title} | QS Admin` : `${title} | Quyền Sport`;
    } else {
        document.title = 'Quyền Sport';
    }
});

export default router;
