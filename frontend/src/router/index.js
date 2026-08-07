import { createRouter, createWebHistory } from "vue-router";
import { pinia } from '@/stores';
import { useAuthStore } from '@/stores/auth';

// ==================== CORE LAYOUTS (eager load) ====================
import ClientLayout from "../layouts/ClientLayout.vue";
import CheckoutLayout from "../layouts/CheckoutLayout.vue";

// ==================== HOME PAGES (eager load - trang chính) ====================
import Home from "@/features/shop/pages/Home/Home.vue";

// ==================== LAZY LOADED PAGES ====================

// Auth
const Login = () => import("@/features/auth/pages/login.vue");
const Register = () => import("@/features/auth/pages/Register.vue");
const Forgot = () => import("@/features/auth/pages/Forgot.vue");
const GoogleCallback = () => import("@/features/auth/pages/GoogleCallback.vue");
const FacebookCallback = () => import("@/features/auth/pages/FacebookCallback.vue");

// Admin (lazy load toàn bộ - chỉ tải khi admin truy cập)
const AdminLayout = () => import("../layouts/AdminLayout.vue");
const AdminHome = () => import("@/features/admin/pages/AdminHome.vue");
const AdminProduct = () => import("@/features/shop/pages/admin/AdminProduct.vue");
const AdminCreateProduct = () => import("@/features/shop/pages/admin/AdminCreateProduct.vue");
const AdminUsers = () => import("@/features/admin/pages/AdminUsers.vue");
const AdminCategory = () => import("@/features/shop/pages/admin/AdminCategory.vue");
const AdminStaff = () => import("@/features/hr/pages/AdminStaff.vue");
const AdminContact = () => import("@/features/support/pages/AdminContact.vue");
const AdminCoupon = () => import("@/features/shop/pages/admin/AdminCoupon.vue");
const AdminRewards = () => import("@/features/shop/pages/admin/AdminRewards.vue");
const AdminUserRewards = () => import("@/features/shop/pages/admin/AdminUserRewards.vue");
const AdminAffiliate = () => import("@/features/admin/pages/AdminAffiliate.vue");

const routes = [

    {
        path: "/checkout",
        component: CheckoutLayout,
        children: [{ path: "", name: "checkout", component: () => import("@/features/shop/pages/Cart/Checkout.vue"), meta: { title: 'Thanh toán' } }]
    },
    {
        path: "/",
        component: ClientLayout,
        children: [
            { path: "", name: "home", component: Home, meta: { title: 'Trang chủ' } },
            { path: "cart", name: "cart", component: () => import("@/features/shop/pages/Cart/Index.vue"), meta: { title: 'Giỏ hàng' } },
            { path: "order-success/:order_code?", name: "order-success", component: () => import("@/features/shop/pages/Cart/OrderSuccess.vue"), meta: { title: 'Đặt hàng thành công' } },
            { path: "payment/result", name: "payment-result", component: () => import("@/features/shop/pages/Payment/PaymentResult.vue"), meta: { title: 'Kết quả thanh toán' } },
            // Product pages
            { path: "product", name: "product-list", component: () => import("@/features/shop/pages/Home/Product.vue"), meta: { title: 'Sản phẩm' } },
            { path: "product/:id", name: "product-detail", component: () => import("@/features/shop/pages/Home/productDetail.vue"), meta: { title: 'Chi tiết sản phẩm' } },
            // Flash Sale & Coupon
            { path: "flash-sale", name: "flash-sale", component: () => import("@/features/shop/pages/Home/FlashSale.vue"), meta: { title: 'Flash Sale' } },
            { path: "coupon", name: "coupon", component: () => import("@/features/shop/pages/Home/Coupon.vue"), meta: { title: 'Mã giảm giá' } },            { path: "tracking", name: "guest-tracking", component: () => import("@/features/shop/pages/client/GuestTracking.vue"), meta: { title: 'Tra cứu đơn hàng' } },
            { path: "tracking/:token", name: "guest-tracking-token", component: () => import("@/features/shop/pages/client/GuestTracking.vue"), meta: { title: 'Theo dõi đơn hàng' } },            // Profile
            {
                path: "profile",
                component: () => import("@/features/profile/pages/ProfileLayout.vue"),
                meta: { requiresAuth: true },
                children: [
                    { path: "", name: "profile-info", component: () => import("@/features/profile/pages/ProfileInfo.vue"), meta: { title: 'Thông tin cá nhân' } },
                    { path: "orders", name: "profile-orders", component: () => import("@/features/profile/pages/ProfileOrders.vue"), meta: { title: 'Đơn hàng' } },
                    { path: "orders/:id", name: "profile-order-detail", component: () => import("@/features/profile/pages/ProfileOrderDetail.vue"), meta: { title: 'Chi tiết đơn hàng' } },
                    { path: "return-requests", name: "profile-return-requests", component: () => import("@/features/profile/pages/ProfileReturnRequests.vue"), meta: { title: 'Yêu cầu hoàn hàng' } },
                    { path: "return-requests/:id", name: "profile-return-request-detail", component: () => import("@/features/profile/pages/ProfileReturnRequestDetail.vue"), meta: { title: 'Chi tiết yêu cầu hoàn hàng' } },
                    { path: "addresses", name: "profile-address", component: () => import("@/features/profile/pages/ProfileAddress.vue"), meta: { title: 'Địa chỉ' } },
                    { path: "change-password", name: "profile-change-password", component: () => import("@/features/profile/pages/ProfileChangePassword.vue"), meta: { title: 'Đổi mật khẩu' } },
                    { path: "wishlist", name: "profile-wishlist", component: () => import("@/features/profile/pages/ProfileWishlist.vue"), meta: { title: 'Yêu thích' } },
                    { path: "coupons", name: "profile-coupons", component: () => import("@/features/profile/pages/ProfileCoupon.vue"), meta: { title: 'Mã giảm giá của tôi' } },
                    { path: "affiliate", name: "profile-affiliate", component: () => import("@/features/profile/pages/ProfileAffiliate.vue"), meta: { title: 'Affiliate' } },
                    { path: "wallet", name: "profile-wallet", component: () => import("@/features/profile/pages/ProfileWallet.vue"), meta: { title: 'Ví của tôi' } },
                    { path: "loyalty", name: "profile-loyalty", component: () => import("@/features/profile/pages/ProfileLoyalty.vue"), meta: { title: 'Điểm thưởng' } },
                    { path: "court-bookings", name: "profile-court-bookings", component: () => import("@/features/courts/pages/client/UserBookings.vue"), meta: { title: 'Lịch sử đặt sân' } },
                    { path: "notifications", name: "profile-notifications", component: () => import("@/features/profile/pages/ProfileNotifications.vue"), meta: { title: 'Thông báo' } },
                ],
            },
            // Blog/Posts pages
            { path: "posts", name: "post-list", component: () => import("../features/content/pages/client/PostList.vue"), meta: { title: 'Tin tức' } },
            { path: "posts/:id", name: "post-detail", component: () => import("../features/content/pages/client/PostDetail.vue"), meta: { title: 'Chi tiết bài viết' } },
            // Static pages
            { path: "about", name: "about", component: () => import("@/features/content/pages/static/BrandStory.vue"), meta: { title: 'Về chúng tôi' } },
            { path: "brand-story", name: "brand-story", component: () => import("@/features/content/pages/static/BrandStory.vue"), meta: { title: 'Câu chuyện thương hiệu' } },
            { path: "careers", name: "careers", component: () => import("@/features/content/pages/static/Careers.vue"), meta: { title: 'Tuyển dụng' } },
            { path: "contact", name: "contact", component: () => import("@/features/content/pages/static/Contact.vue"), meta: { title: 'Liên hệ' } },
            { path: "faq", name: "faq", component: () => import("@/features/content/pages/static/FAQ.vue"), meta: { title: 'Câu hỏi thường gặp' } },
            { path: "privacy", name: "privacy", component: () => import("@/features/content/pages/static/Privacy.vue"), meta: { title: 'Chính sách bảo mật' } },
            { path: "return-policy", name: "return-policy", component: () => import("@/features/content/pages/static/ReturnPolicy.vue"), meta: { title: 'Chính sách đổi trả' } },
            { path: "shopping-guide", name: "shopping-guide", component: () => import("@/features/content/pages/static/ShoppingGuide.vue"), meta: { title: 'Hướng dẫn mua hàng' } },
            { path: "terms", name: "terms", component: () => import("@/features/content/pages/static/Terms.vue"), meta: { title: 'Điều khoản dịch vụ' } },
            // Court Booking Pages
            { path: "courts", name: "courts-list", component: () => import("@/features/courts/pages/client/CourtsList.vue"), meta: { title: 'Đặt sân cầu lông' } },
            { path: "courts/:id", name: "court-detail", component: () => import("@/features/courts/pages/client/CourtDetail.vue"), meta: { title: 'Chi tiết sân' } },

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
                component: () => import("@/features/shop/pages/admin/AdminPOS.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Bán Hàng Trực Tiếp (POS)' },
            },
            {
                path: "order",
                name: "admin-order",
                component: () => import("@/features/shop/pages/admin/AdminOrder.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Quản lý Đơn hàng' },
            },
            {
                path: "return-requests",
                name: "admin-return-requests",
                component: () => import("@/features/shop/pages/admin/AdminReturnRequests.vue"),
                meta: { roles: ['admin'], title: 'Yêu cầu hoàn hàng' },
            },
            {
                path: "return-requests/:id",
                name: "admin-return-request-detail",
                component: () => import("@/features/shop/pages/admin/AdminReturnRequestDetail.vue"),
                meta: { roles: ['admin'], title: 'Chi tiết yêu cầu hoàn hàng' },
            },
            {
                path: "order/:id",
                name: "admin-order-detail",
                component: () => import("@/features/shop/pages/admin/AdminOrderDetail.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Chi tiết Đơn hàng' },
            },
            {
                path: "product/edit/:id",
                name: "admin-product-edit",
                component: () => import("@/features/shop/pages/admin/AdminEditProduct.vue"),
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
                component: () => import("@/features/support/pages/AdminChat.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Tin nhắn khách hàng' },
            },
            {
                path: "coupon",
                name: "admin-coupon",
                component: AdminCoupon,
                meta: { roles: ['admin', 'staff'], title: 'Quản lý Mã giảm giá' },
            },
            {
                path: "affiliate",
                name: "admin-affiliate",
                component: AdminAffiliate,
                meta: { roles: ['admin'], title: 'Quản lý Affiliate' },
            },
            {
                path: "rewards",
                name: "admin-rewards",
                component: AdminRewards,
                meta: { roles: ['admin', 'staff'], title: 'Quản lý Quà Tặng' },
            },
            {
                path: "user-rewards",
                name: "admin-user-rewards",
                component: AdminUserRewards,
                meta: { roles: ['admin', 'staff'], title: 'Lịch sử đổi quà' },
            },
            {
                path: "flash-sale",
                name: "admin-flash-sale",
                component: () => import("@/features/shop/pages/admin/AdminFlashSale.vue"),
                meta: { roles: ['admin'], title: 'Quản lý Flash Sale' },
            },
            {
                path: "wallet-deposits",
                name: "admin-wallet-deposits",
                component: () => import("@/features/admin/pages/AdminWalletDeposits.vue"),
                meta: { roles: ['admin'], title: 'Ví & Nạp tiền' },
            },
            {
                path: "wallet-withdrawals",
                name: "admin-wallet-withdrawals",
                component: () => import("@/features/admin/pages/AdminWalletWithdrawals.vue"),
                meta: { roles: ['admin'], title: 'Duyệt rút tiền' },
            },
            {
                path: "post",
                name: "admin-post",
                component: () => import("@/features/content/pages/admin/AdminPost.vue"),
                meta: { roles: ['admin'], title: 'Quản lý bài viết' },
            },
            {
                path: "post/create",
                name: "admin-post-create",
                component: () => import("@/features/content/pages/admin/AdminCreatePost.vue"),
                meta: { roles: ['admin'], title: 'Thêm bài viết' },
            },
            {
                path: "post/edit/:id",
                name: "admin-post-edit",
                component: () => import("@/features/content/pages/admin/AdminEditPost.vue"),
                meta: { roles: ['admin'], title: 'Sửa bài viết' },
            },
            {
                path: "post-comments",
                name: "admin-post-comments",
                component: () => import("@/features/shop/pages/admin/AdminPostComments.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Duyệt bình luận bài viết' },
            },
            {
                path: "post-category",
                name: "admin-post-category",
                component: () => import("@/features/content/pages/admin/AdminPostCategory.vue"),
                meta: { roles: ['admin'], title: 'Danh mục bài viết' },
            },
            {
                path: "post-category/create",
                name: "admin-post-category-create",
                component: () => import("@/features/content/pages/admin/AdminCreatePostCategory.vue"),
                meta: { roles: ['admin'], title: 'Thêm danh mục bài viết' },
            },
            {
                path: "review",
                name: "admin-review",
                component: () => import("@/features/shop/pages/admin/AdminReview.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Quản lý đánh giá' },
            },

            {
                path: "tickets",
                name: "admin-tickets",
                component: () => import("@/features/support/pages/AdminTicketList.vue"),
                meta: { roles: ['admin', 'seller'], title: 'Quản lý khiếu nại' },
            },
            {
                path: "attendance",
                name: "admin-attendance",
                component: () => import("../features/hr/pages/AdminAttendance.vue"),
                meta: { roles: ['admin', 'seller', 'staff'], title: 'Chấm Công' },
            },
            {
                path: "face-register",
                name: "admin-face-register",
                component: () => import("../features/hr/pages/FaceRegister.vue"),
                meta: { roles: ['admin', 'seller', 'staff'], title: 'Đăng ký Khuôn mặt' },
            },
            {
                path: "face-management",
                name: "admin-face-management",
                component: () => import("../features/hr/pages/AdminFaceManagement.vue"),
                meta: { roles: ['admin'], title: 'Quản lý Khuôn mặt' },
            },
            {
                path: "stats",
                name: "admin-stats",
                component: () => import("@/features/admin/pages/AdminStats.vue"),
                meta: { roles: ['admin', 'seller', 'staff'], title: 'Thống kê' },
            },
            {
                path: "attendance-list",
                name: "admin-attendance-list",
                component: () => import("../features/hr/pages/AdminAttendanceList.vue"),
                meta: { roles: ['admin'], title: 'Danh sách Chấm công' },
            },
            {
                path: "work-locations",
                name: "admin-work-locations",
                component: () => import("../features/hr/pages/AdminWorkLocations.vue"),
                meta: { roles: ['admin'], title: 'Quản lý chi nhánh' },
            },
            {
                path: "work-shifts",
                name: "admin-work-shifts",
                component: () => import("../features/hr/pages/AdminWorkShifts.vue"),
                meta: { roles: ['admin'], title: 'Ca làm việc & Phân ca' },
            },
            // Court Booking Admin Pages
            {
                path: "courts",
                name: "admin-courts",
                component: () => import("@/features/courts/pages/admin/AdminCourtManagement.vue"),
                meta: { roles: ['admin', 'seller', 'staff'], title: 'Quản lý Hệ thống Sân' },
            },
            {
                path: "court-bookings",
                name: "admin-court-bookings",
                component: () => import("@/features/courts/pages/admin/AdminBookingManagement.vue"),
                meta: { roles: ['admin', 'staff', 'seller'], title: 'Quản lý Đặt Sân' },
            },
            {
                path: "court-dashboard",
                name: "admin-court-dashboard",
                component: () => import("@/features/courts/pages/admin/AdminCourtDashboard.vue"),
                meta: { roles: ['admin', 'staff', 'seller'], title: 'Dashboard Lễ Tân' },
            },
            {
                path: "court-reports",
                name: "admin-court-reports",
                component: () => import("@/features/courts/pages/admin/AdminCourtReports.vue"),
                meta: { roles: ['admin'], title: 'Thống Kê Sân' },
            },
            {
                path: "notifications",
                name: "admin-notifications",
                component: () => import("@/features/admin/pages/AdminNotifications.vue"),
                meta: { roles: ['admin', 'seller', 'staff'], title: 'Thông báo hệ thống' },
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

// ==================== Dynamic Page Title & Global States ====================
router.afterEach((to, from) => {
    const title = to.meta.title;
    const isAdmin = to.matched.some(record => record.path === '/admin');

    if (title) {
        document.title = isAdmin ? `${title} | QS Admin` : `${title} | Ocean Sport`;
    } else {
        document.title = 'Ocean Sport';
    }
});

export default router;
