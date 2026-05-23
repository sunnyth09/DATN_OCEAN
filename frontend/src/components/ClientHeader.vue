<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../axios.js";
import { broadcastLogout } from "../sessionSync.js";
import Swal from "sweetalert2";
import SearchModal from "./SearchModal.vue";

const BASE_URL = import.meta.env.VITE_BASE_URL;
const route = useRoute();
const router = useRouter();

const isLoggedIn = ref(false);
const userName = ref("");
const userEmail = ref("");
const userAvatar = ref(null);
const isAdmin = ref(false);
const showDropdown = ref(false);
const categories = ref([]);
const cartCount = ref(0);
const unreadNotificationCount = ref(0);
const isSearchModalOpen = ref(false);
const isMobileMenuOpen = ref(false);

// Lấy 3 danh mục bán chạy nhất (ở đây giả sử là 3 root category đầu tiên trả về từ API)
const topCategories = computed(() => {
    return categories.value.slice(0, 4);
});

const getCategoryId = (category) => {
    return String(category.category_id ?? category.id ?? "");
};

const getCategoryRoute = (category) => {
    return {
        name: "product-list",
        query: { category: getCategoryId(category) },
    };
};

const isCategoryActive = (category) => {
    return (
        route.name === "product-list" &&
        String(route.query.category ?? "") === getCategoryId(category)
    );
};

const isRouteActive = (routeName) => {
    return route.name === routeName;
};

const closeAccountMenu = () => {
    showDropdown.value = false;
};

const closeMobileMenu = () => {
    isMobileMenuOpen.value = false;
};

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
    if (isMobileMenuOpen.value) {
        closeAccountMenu();
    }
};

const toggleAccountMenu = () => {
    showDropdown.value = !showDropdown.value;
    if (showDropdown.value) {
        closeMobileMenu();
    }
};

const openSearch = () => {
    isSearchModalOpen.value = true;
    closeMobileMenu();
};

const handleDocumentClick = (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    if (!target.closest(".account-dropdown")) {
        closeAccountMenu();
    }
};

const handleViewportResize = () => {
    if (window.innerWidth > 768) {
        closeMobileMenu();
    }
};

const fetchCategories = async () => {
    try {
        const response = await api.get("/categories");
        categories.value = response.data.data;
    } catch (error) {
        console.error("Error fetching categories:", error);
    }
};

const checkAuth = () => {
    const userData = sessionStorage.getItem("user");
    if (userData) {
        try {
            const user = JSON.parse(userData);
            isLoggedIn.value = true;
            userName.value = user.full_name || user.name || user.email;
            userEmail.value = user.email || "";
            isAdmin.value = ["admin", "staff", "seller"].includes(user.role);

            const path = user.avatar_url;
            if (path) {
                const API_URL = (
                    import.meta.env.VITE_API_URL || "http://localhost:8383/api"
                ).replace("/api", "");
                userAvatar.value = path.startsWith("http")
                    ? path
                    : `${API_URL}${path}`;
            } else {
                userAvatar.value = null;
            }
        } catch (e) {
            isLoggedIn.value = false;
        }
    } else {
        isLoggedIn.value = false;
        userName.value = "";
        userEmail.value = "";
        isAdmin.value = false;
    }
};

const fetchCartCount = async () => {
    const token = sessionStorage.getItem("auth_token");
    if (!token) {
        cartCount.value = 0;
        return;
    }
    try {
        const response = await api.get("/cart/count");
        cartCount.value = response.data.count || 0;
    } catch (e) {
        cartCount.value = 0;
    }
};

const fetchUnreadNotificationCount = async () => {
    const token = sessionStorage.getItem("auth_token");
    if (!token) {
        unreadNotificationCount.value = 0;
        return;
    }
    try {
        const response = await api.get("/profile/notifications");
        unreadNotificationCount.value = response.data.unread_count || 0;
    } catch (e) {
        unreadNotificationCount.value = 0;
    }
};

watch(isLoggedIn, (val) => {
    if (val) {
        fetchUnreadNotificationCount();
        const userData = JSON.parse(sessionStorage.getItem("user") || "{}");
        if (window.Echo && userData && userData.user_id) {
            window.Echo.private('user.' + userData.user_id)
                .listen('.UserNotificationEvent', (e) => { // . means it ignores Broadcast namespace
                    unreadNotificationCount.value++;
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'info',
                        title: e.notification?.title || 'Thông báo mới',
                        text: e.notification?.message || 'Bạn có thông báo mới',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer)
                            toast.addEventListener('mouseleave', Swal.resumeTimer)
                        }
                    });
                });
        }
    } else {
        unreadNotificationCount.value = 0;
        const userData = JSON.parse(sessionStorage.getItem("user") || "{}");
        if (window.Echo && userData && userData.user_id) {
            window.Echo.leave('user.' + userData.user_id);
        }
    }
}, { immediate: true });

const handleLogout = async () => {
    try {
        await api.post("/logout");
    } catch (e) {
        /* ignore */
    }
    broadcastLogout();
    localStorage.removeItem("auth_token");
    localStorage.removeItem("user");
    localStorage.removeItem("ocean_live_chat_token");
    sessionStorage.removeItem("auth_token");
    sessionStorage.removeItem("user");
    sessionStorage.removeItem("ocean_chatbot_messages");
    sessionStorage.removeItem("ocean_chatbot_history");
    isLoggedIn.value = false;
    closeAccountMenu();
    closeMobileMenu();

    window.location.reload();
};

/* DRAGGABLE FLASH SALE LOGIC */
const flashSalePos = ref({
    x: window.innerWidth - 100,
    y: window.innerHeight - 150,
});
let isDragging = false;
let hasMoved = false;
let startX, startY, initialX, initialY;

const startDrag = (e) => {
    if (e.button !== 0 && e.type.includes("mouse")) return; // left click only
    isDragging = true;
    hasMoved = false;
    startX = e.clientX || (e.touches && e.touches[0].clientX);
    startY = e.clientY || (e.touches && e.touches[0].clientY);
    initialX = flashSalePos.value.x;
    initialY = flashSalePos.value.y;

    document.addEventListener("mousemove", onDrag);
    document.addEventListener("mouseup", stopDrag);
    document.addEventListener("touchmove", onDrag, { passive: false });
    document.addEventListener("touchend", stopDrag);
};

const onDrag = (e) => {
    if (!isDragging) return;
    const clientX = e.clientX || (e.touches && e.touches[0].clientX);
    const clientY = e.clientY || (e.touches && e.touches[0].clientY);
    const dx = clientX - startX;
    const dy = clientY - startY;

    if (Math.abs(dx) > 5 || Math.abs(dy) > 5) {
        hasMoved = true;
    }

    if (e.type.includes("touch")) {
        e.preventDefault(); // prevent scrolling while dragging
    }

    flashSalePos.value.x = initialX + dx;
    flashSalePos.value.y = initialY + dy;
};

const stopDrag = () => {
    isDragging = false;
    document.removeEventListener("mousemove", onDrag);
    document.removeEventListener("mouseup", stopDrag);
    document.removeEventListener("touchmove", onDrag);
    document.removeEventListener("touchend", stopDrag);
};

const handleFlashSaleClick = (e) => {
    if (hasMoved) {
        e.preventDefault();
        return;
    }
    router.push("/flash-sale");
};

onMounted(() => {
    checkAuth();
    fetchCategories();
    fetchCartCount();

    window.addEventListener("user-updated", checkAuth);
    window.addEventListener("cart-updated", fetchCartCount);
    window.addEventListener("resize", handleViewportResize);
    document.addEventListener("click", handleDocumentClick);

    // adjust initial position for small screens
    if (window.innerWidth < 768) {
        flashSalePos.value.x = window.innerWidth - 80;
        flashSalePos.value.y = window.innerHeight - 100;
    }
});
onUnmounted(() => {
    window.removeEventListener("user-updated", checkAuth);
    window.removeEventListener("cart-updated", fetchCartCount);
    window.removeEventListener("resize", handleViewportResize);
    document.removeEventListener("click", handleDocumentClick);
});
watch(
    () => route.fullPath,
    () => {
        checkAuth();
        fetchCartCount();
        closeAccountMenu();
        closeMobileMenu();
    },
);
</script>

<template>
    <header class="site-header">
        <div class="header-inner">
            <!-- Wrapper Logo and Nav to stick them together -->
            <div class="header-left">
                <!-- Logo -->
                <router-link to="/" class="logo">
                    <img :src="BASE_URL + '/storage/logo/logo.png'" alt="Logo" class="logo-img"
                        style="width: 120px; height: auto" />
                </router-link>

                <!-- Navigation Links -->
                <nav class="main-nav">
                    <router-link
                        v-for="cat in topCategories"
                        :key="getCategoryId(cat)"
                        :to="getCategoryRoute(cat)"
                        class="nav-link"
                        :class="{ active: isCategoryActive(cat) }"
                    >
                        {{ cat.name }}
                    </router-link>
                    <router-link
                        to="/contact"
                        class="nav-link"
                        :class="{ active: isRouteActive('contact') }"
                    >
                        Liên hệ
                    </router-link>
                </nav>
            </div>

            <div class="header-actions">
                <button
                    type="button"
                    class="icon-btn mobile-nav-toggle"
                    :aria-expanded="isMobileMenuOpen"
                    aria-label="Mở menu điều hướng"
                    @click.stop="toggleMobileMenu"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <!-- Search -->
                <div class="search-wrapper">
                    <button type="button" class="icon-btn search-icon-btn" @click="openSearch">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                </div>

                <!-- Thông báo -->
                <router-link to="/profile/notifications" class="icon-btn notif-icon-btn" v-if="isLoggedIn">
                    <div class="cart-icon-wrapper">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span v-if="unreadNotificationCount > 0" class="cart-badge">{{
                            unreadNotificationCount > 99 ? "99+" : unreadNotificationCount
                        }}</span>
                    </div>
                </router-link>

                <!-- Giỏ hàng -->
                <router-link to="/cart" class="icon-btn cart-icon-btn">
                    <div class="cart-icon-wrapper">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                        <span v-if="cartCount > 0" class="cart-badge">{{
                            cartCount > 99 ? "99+" : cartCount
                        }}</span>
                    </div>
                </router-link>

                <!-- Tài khoản -->
                <div class="account-dropdown">
                    <button class="icon-btn user-icon-btn equip-small-link-custom" @click.stop="toggleAccountMenu">
                        <template v-if="isLoggedIn">
                            <img v-if="userAvatar" :src="userAvatar" class="header-user-avatar" />
                            <div v-else class="header-user-avatar-fallback">
                                {{ (userName || "?")[0].toUpperCase() }}
                            </div>
                        </template>
                        <template v-else>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </template>
                    </button>

                    <!-- User Dropdown Menu -->
                    <div class="account-menu" v-show="showDropdown">
                        <div class="account-menu-inner">
                            <template v-if="isLoggedIn">
                                <div class="dropdown-user">
                                    <img v-if="userAvatar" :src="userAvatar" class="dropdown-avatar-img" />
                                    <div v-else class="dropdown-avatar">
                                        {{ (userName || "?")[0].toUpperCase() }}
                                    </div>
                                    <div class="dropdown-user-text">
                                        <div class="dropdown-name">
                                            {{ userName }}
                                        </div>
                                        <div class="dropdown-email">
                                            {{ userEmail }}
                                        </div>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <router-link to="/profile" class="account-menu-item">Tài khoản của tôi</router-link>
                                <router-link v-if="isAdmin" to="/admin" class="account-menu-item">Quản trị</router-link>
                                <button @click="handleLogout" class="account-menu-item account-logout">
                                    Đăng xuất
                                </button>
                            </template>
                            <template v-else>
                                <router-link to="/client/login" class="account-menu-item">Đăng nhập</router-link>
                                <router-link to="/client/register" class="account-menu-item">Đăng ký</router-link>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <Transition name="mobile-menu-fade">
        <div v-if="isMobileMenuOpen" class="mobile-nav-overlay" @click="closeMobileMenu">
            <div class="mobile-nav-panel" @click.stop>
                <div class="mobile-nav-header">
                    <div>
                        <p class="mobile-nav-eyebrow">Quyền Sport</p>
                        <h2 class="mobile-nav-title">Khám phá nhanh</h2>
                    </div>
                    <button type="button" class="icon-btn mobile-nav-close" @click="closeMobileMenu">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>

                <button type="button" class="mobile-search-btn" @click="openSearch">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <span>Tìm kiếm sản phẩm</span>
                </button>

                <nav class="mobile-nav-links">
                    <router-link
                        v-for="cat in topCategories"
                        :key="`mobile-${getCategoryId(cat)}`"
                        :to="getCategoryRoute(cat)"
                        class="mobile-nav-link"
                        :class="{ active: isCategoryActive(cat) }"
                        @click="closeMobileMenu"
                    >
                        {{ cat.name }}
                    </router-link>
                    <router-link
                        to="/contact"
                        class="mobile-nav-link"
                        :class="{ active: isRouteActive('contact') }"
                        @click="closeMobileMenu"
                    >
                        Liên hệ
                    </router-link>
                </nav>

                <div class="mobile-nav-account">
                    <template v-if="isLoggedIn">
                        <div class="mobile-account-summary">
                            <img v-if="userAvatar" :src="userAvatar" class="header-user-avatar" />
                            <div v-else class="header-user-avatar-fallback">
                                {{ (userName || "?")[0].toUpperCase() }}
                            </div>
                            <div class="mobile-account-text">
                                <strong>{{ userName }}</strong>
                                <span>{{ userEmail }}</span>
                            </div>
                        </div>
                        <router-link to="/profile" class="mobile-account-link" @click="closeMobileMenu">Tài khoản của tôi</router-link>
                        <router-link v-if="isAdmin" to="/admin" class="mobile-account-link" @click="closeMobileMenu">Quản trị</router-link>
                        <router-link to="/profile/notifications" class="mobile-account-link" @click="closeMobileMenu">Thông báo</router-link>
                        <button type="button" class="mobile-account-link mobile-account-link--danger" @click="handleLogout">
                            Đăng xuất
                        </button>
                    </template>
                    <template v-else>
                        <router-link to="/client/login" class="mobile-account-link" @click="closeMobileMenu">Đăng nhập</router-link>
                        <router-link to="/client/register" class="mobile-account-link" @click="closeMobileMenu">Đăng ký</router-link>
                    </template>
                </div>
            </div>
        </div>
    </Transition>

    <!-- Draggable Flash Sale Widget -->
    <div class="floating-flash-sale" :style="{ left: flashSalePos.x + 'px', top: flashSalePos.y + 'px' }"
        @mousedown="startDrag" @touchstart="startDrag" @click="handleFlashSaleClick">
        <div class="flash-sale-badge">
            <svg class="flash-sale-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
            </svg>
            <span>FLASH SALE</span>
        </div>
    </div>

    <SearchModal v-model="isSearchModalOpen" />
</template>

<style scoped>
.site-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid #F8F9FA;
    position: sticky;
    top: 0;
    z-index: 100;
}

.header-inner {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 40px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* LAYOUT */
.header-left {
    display: flex;
    align-items: center;
    gap: 40px;
    /* distance between logo and nav */
    height: 100%;
}

/* LOGO */
.logo {
    text-decoration: none;
}

/* NAVIGATION */
.main-nav {
    display: flex;
    gap: 32px;
    height: 100%;
}

.nav-link {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    color: #555;
    font-weight: 600;
    font-size: 0.95rem;
    position: relative;
    transition: color 0.2s;
    text-transform: capitalize;
}

.nav-link:hover {
    color: #E63B6F;
}

.nav-link.active {
    color: #E63B6F;
}

.nav-link.active::after {
    content: "";
    position: absolute;
    bottom: 20px;
    left: 0;
    right: 0;
    height: 2px;
    background-color: #E63B6F;
    border-radius: 2px;
}

/* HEADER ACTIONS */
.header-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

.mobile-nav-toggle {
    display: none;
}

.icon-btn {
    background: none;
    border: none;
    color: #111;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px;
    border-radius: 50%;
    transition: background 0.2s;
}

.icon-btn:hover {
    background: #FFF0F3;
}

/* INLINE EXPANDABLE SEARCH */
.search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

/* CART BADGE */
.cart-icon-wrapper {
    position: relative;
    display: inline-flex;
}

.cart-badge {
    position: absolute;
    top: -4px;
    right: -6px;
    background: #E63B6F;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    min-width: 16px;
    height: 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    line-height: 1;
    border: 2px solid #fff;
}

/* USER DROPDOWN */
.account-dropdown {
    position: relative;
}

.header-user-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
}

.header-user-avatar-fallback {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #E63B6F;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
}

.account-menu {
    position: absolute;
    top: 100%;
    right: 0;
    padding-top: 12px;
    min-width: 220px;
    z-index: 200;
}

.account-menu-inner {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
}

.dropdown-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
}

.dropdown-avatar-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.dropdown-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #E63B6F;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.dropdown-name {
    font-size: 0.9rem;
    font-weight: 600;
}

.dropdown-email {
    font-size: 0.75rem;
    color: #888;
}

.dropdown-divider {
    height: 1px;
    background: #f0f0f0;
    margin: 4px 0;
}

.account-menu-item {
    display: block;
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    color: #333;
    text-decoration: none;
    cursor: pointer;
    background: none;
    border: none;
    width: 100%;
    text-align: left;
    transition: background 0.15s;
}

.account-menu-item:hover {
    background: #f1f5f9;
}

.account-logout {
    color: #ef4444;
}

.account-logout:hover {
    background: #fef2f2;
}

.mobile-nav-overlay {
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    padding: 12px;
    display: flex;
    justify-content: flex-end;
}

.mobile-nav-panel {
    width: min(88vw, 360px);
    height: calc(100vh - 24px);
    background: #ffffff;
    border-radius: 24px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    box-shadow: 0 20px 60px rgba(15, 23, 42, 0.24);
}

.mobile-nav-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.mobile-nav-eyebrow {
    margin: 0 0 4px;
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
}

.mobile-nav-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 800;
    color: #0f172a;
}

.mobile-nav-close {
    flex-shrink: 0;
}

.mobile-search-btn,
.mobile-account-link {
    width: 100%;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #0f172a;
    border-radius: 14px;
    padding: 14px 16px;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
}

.mobile-search-btn:hover,
.mobile-account-link:hover {
    border-color: rgba(230, 59, 111, 0.28);
    background: #fff7f9;
}

.mobile-nav-links,
.mobile-nav-account {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.mobile-nav-links {
    padding-bottom: 18px;
    border-bottom: 1px solid #f1f5f9;
}

.mobile-nav-link {
    display: block;
    padding: 12px 14px;
    border-radius: 14px;
    text-decoration: none;
    color: #334155;
    font-weight: 600;
    background: #f8fafc;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

.mobile-nav-link:hover,
.mobile-nav-link.active {
    color: #E63B6F;
    background: #fff1f4;
    border-color: rgba(230, 59, 111, 0.18);
}

.mobile-account-summary {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 4px 2px 10px;
}

.mobile-account-text {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.mobile-account-text strong {
    color: #0f172a;
    font-size: 0.96rem;
}

.mobile-account-text span {
    color: #64748b;
    font-size: 0.82rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mobile-account-link--danger {
    color: #dc2626;
}

.mobile-menu-fade-enter-active,
.mobile-menu-fade-leave-active {
    transition: opacity 0.2s ease;
}

.mobile-menu-fade-enter-from,
.mobile-menu-fade-leave-to {
    opacity: 0;
}

/* DRAGGABLE FLOATING FLASH SALE */
.floating-flash-sale {
    position: fixed;
    z-index: 9999;
    cursor: grab;
    user-select: none;
    touch-action: none;
}

.floating-flash-sale:active {
    cursor: grabbing;
}

.flash-sale-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #E63B6F, #d82f65);
    color: #fff;
    padding: 10px 16px;
    border-radius: 30px;
    box-shadow: 0 6px 16px rgba(230, 59, 111, 0.3);
    font-weight: 800;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    animation: flash-pulse 2s infinite;
}

.flash-sale-badge svg {
    color: #fff;
    fill: rgba(255, 255, 255, 0.2);
}

@keyframes flash-pulse {
    0% {
        transform: scale(1);
        box-shadow: 0 6px 16px rgba(230, 59, 111, 0.3);
    }

    50% {
        transform: scale(1.05);
        box-shadow: 0 8px 20px rgba(230, 59, 111, 0.5);
    }

    100% {
        transform: scale(1);
        box-shadow: 0 6px 16px rgba(230, 59, 111, 0.3);
    }
}

@media (max-width: 768px) {
    .header-inner {
        padding: 0 20px;
    }

    .header-left {
        gap: 16px;
    }

    .main-nav {
        display: none;
    }

    .mobile-nav-toggle {
        display: inline-flex;
    }

    .account-dropdown {
        display: none;
    }

    .header-actions {
        gap: 10px;
    }

    .flash-sale-badge {
        padding: 8px 12px;
        font-size: 0.76rem;
    }

    .flash-sale-icon {
        width: 18px;
        height: 18px;
    }
}
</style>
