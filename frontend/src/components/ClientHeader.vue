<script setup>
import { ref, onMounted, onUnmounted, onBeforeUnmount, watch, computed } from "vue";
import { storeToRefs } from "pinia";
import { useRoute, useRouter } from "vue-router";
import api from "../axios.js";
import { broadcastLogout } from "../sessionSync.js";
import Swal from "sweetalert2";
import AppIcon from "@/components/AppIcon.vue";
import { useCartStore } from "@/stores/cart";
import { useCatalogStore } from "@/stores/catalog";
import { useAuthStore } from "@/stores/auth";
import { catalogService, extractCollection } from "@/services/catalogService";
import { getAppBaseUrl } from "@/utils/url";
import { loyaltyService } from "@/services/loyaltyService";

const BASE_URL = getAppBaseUrl();
const route = useRoute();
const router = useRouter();
const cartStore = useCartStore();
const catalogStore = useCatalogStore();
const authStore = useAuthStore();
const { count: cartCount } = storeToRefs(cartStore);
const { categories } = storeToRefs(catalogStore);
const { unreadNotificationCount } = storeToRefs(authStore);

const isLoggedIn = ref(false);
const userName = ref("");
const userEmail = ref("");
const userAvatar = ref(null);
const isAdmin = ref(false);
const showDropdown = ref(false);
const showNotifDropdown = ref(false);
const notificationsList = ref([]);
const showNotificationPopup = ref(false);
const isMobileMenuOpen = ref(false);
const headerRewardPoints = ref(0);
const isScrolled = ref(false);

const handleScroll = () => {
    const scrollTop = window.scrollY;
    if (scrollTop > 50) {
        isScrolled.value = true;
    } else if (scrollTop < 30) {
        isScrolled.value = false;
    }
};

const notifPage = ref(1);
const notifTotalPages = ref(1);
const isFetchingNotif = ref(false);

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

const toggleNotifMenu = async () => {
    showNotifDropdown.value = !showNotifDropdown.value;
    if (showNotifDropdown.value) {
        closeMobileMenu();
        showDropdown.value = false;
        await fetchNotificationsList();
    }
};

const fetchNotificationsList = async (page = 1) => {
    const token = sessionStorage.getItem("auth_token");
    if (!token) return;
    isFetchingNotif.value = true;
    try {
        const response = await api.get(`/profile/notifications?limit=5&page=${page}`);
        if (response.data && response.data.data) {
            // Check if it's a paginator object (Laravel default)
            if (response.data.data.data && Array.isArray(response.data.data.data)) {
                if (page === 1) {
                    notificationsList.value = response.data.data.data;
                } else {
                    notificationsList.value = [...notificationsList.value, ...response.data.data.data];
                }
                notifPage.value = response.data.data.current_page || 1;
                notifTotalPages.value = response.data.data.last_page || 1;
            } else {
                notificationsList.value = response.data.data;
            }
        } else if (Array.isArray(response.data)) {
            notificationsList.value = response.data.slice(0, 5);
        }
    } catch (e) {
        console.error("Failed to fetch notifications list", e);
    } finally {
        isFetchingNotif.value = false;
    }
};

const loadMoreNotifications = async () => {
    if (notifPage.value < notifTotalPages.value && !isFetchingNotif.value) {
        await fetchNotificationsList(notifPage.value + 1);
    }
};

const markAsRead = async (notif) => {
    if (!notif.read_at) {
        try {
            notif.read_at = new Date().toISOString();
            authStore.decrementUnreadNotificationCount();
            await api.post(`/profile/notifications/${notif.id}/read`);
        } catch (e) {
            console.error(e);
        }
    }

    if (notif.data?.url_redirect) {
        router.push(notif.data.url_redirect);
        showNotifDropdown.value = false;
    }
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
        showNotifDropdown.value = false;
    }
};

// Inline Search State & Logic
const searchInputRef = ref(null);
const isSearchExpanded = ref(false);
const searchQuery = ref("");
const searchResults = ref([]);
const isSearching = ref(false);
const showDropdownResult = ref(false);
const searchHistory = ref([]);
const recentlyViewed = ref([]);
let searchTimeout = null;

const fetchSearchHistoryAndRecentlyViewed = async () => {
    try {
        const [historyRes, recentlyRes] = await Promise.all([
            catalogService.getSearchHistory(),
            catalogService.getRecentlyViewed()
        ]);
        searchHistory.value = historyRes.data?.data || [];
        recentlyViewed.value = recentlyRes.data?.data || [];
    } catch (e) {
        console.error('Lỗi lấy lịch sử tìm kiếm:', e);
    }
};

const toggleSearch = () => {
    isSearchExpanded.value = !isSearchExpanded.value;
    if (isSearchExpanded.value) {
        fetchSearchHistoryAndRecentlyViewed();
        setTimeout(() => {
            searchInputRef.value?.focus();
        }, 100);
    } else {
        searchQuery.value = "";
        searchResults.value = [];
        showDropdownResult.value = false;
    }
};

const handleSearchFocus = () => {
    showDropdownResult.value = true;
};

const handleSearchBlur = () => {
    setTimeout(() => {
        showDropdownResult.value = false;
    }, 200);
};

const performSearch = async (query) => {
    if (!query.trim()) {
        searchResults.value = [];
        showDropdownResult.value = false;
        return;
    }
    isSearching.value = true;
    showDropdownResult.value = true;
    try {
        const res = await catalogService.searchProducts(query, { limit: 5 });
        searchResults.value = extractCollection(res) || [];
    } catch (e) {
        console.error("Lỗi tìm kiếm:", e);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

watch(searchQuery, (newVal) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        performSearch(newVal);
    }, 300);
});

const executeSearch = (query = null) => {
    const finalQuery = typeof query === 'string' ? query : searchQuery.value;
    if (finalQuery.trim()) {
        router.push({ name: 'product-list', query: { search: finalQuery.trim() } });
        isSearchExpanded.value = false;
        showDropdownResult.value = false;
        searchQuery.value = finalQuery.trim();
    }
};

const getImageUrl = (item) => {
    const path = item.thumbnail_url || item.image || item.main_image?.image_url || item.mainImage?.image_url;
    if (!path || path === '0') return '';
    if (path.startsWith('http') || path.startsWith('data:')) return path;
    const normalizedPath = String(path).replace(/^\/+/, '').replace(/^storage\/+/, '');
    const url = BASE_URL.endsWith('/') ? BASE_URL : BASE_URL + '/';
    return `${url}storage/${normalizedPath}`;
};

const formatPrice = (value) => {
    const num = Number(value);
    if (!isNaN(num)) {
        return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(num);
    }
    return value || 'Liên hệ';
};

const goToProduct = (slug) => {
    router.push(`/product/${slug}`);
    isSearchExpanded.value = false;
    showDropdownResult.value = false;
};

const handleDocumentClick = (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    if (!target.closest(".account-dropdown")) {
        closeAccountMenu();
    }

    if (!target.closest(".notif-dropdown")) {
        showNotifDropdown.value = false;
    }

    if (!target.closest(".search-wrapper")) {
        showDropdownResult.value = false;
        if (!searchQuery.value) {
            isSearchExpanded.value = false;
        }
    }
};

const handleViewportResize = () => {
    if (window.innerWidth > 768) {
        closeMobileMenu();
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
                userAvatar.value = path.startsWith("http")
                    ? path
                    : `${BASE_URL}${path}`;
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

const fetchUnreadNotificationCount = () => authStore.fetchUnreadNotificationCount();

const fetchHeaderRewardPoints = async () => {
    const token = sessionStorage.getItem("auth_token");
    if (!token) { headerRewardPoints.value = 0; return; }
    try {
        const res = await loyaltyService.getSummary();
        headerRewardPoints.value = res.data?.data?.current_balance ?? 0;
    } catch (e) {
        headerRewardPoints.value = 0;
    }
};

let notificationUserId = null;

const leaveNotificationChannel = () => {
    if (window.Echo && notificationUserId) {
        window.Echo.leave('user.' + notificationUserId);
    }
    notificationUserId = null;
};

let globalUserAudioCtx = null;
const initUserAudioContext = () => {
  if (!globalUserAudioCtx) {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (AudioCtx) {
      globalUserAudioCtx = new AudioCtx();
    }
  }
  if (globalUserAudioCtx && globalUserAudioCtx.state === 'suspended') {
    globalUserAudioCtx.resume();
  }
};

if (typeof window !== 'undefined') {
  window.addEventListener('click', initUserAudioContext, { once: false });
  window.addEventListener('keydown', initUserAudioContext, { once: false });
}

const playNotificationSound = () => {
    try {
        initUserAudioContext();
        if (!globalUserAudioCtx) return;

        const ctx = globalUserAudioCtx;
        const now = ctx.currentTime;

        // Tiếng Yahoo Messenger Chime huyền thoại (D5 -> A5 -> D6)
        const osc1 = ctx.createOscillator();
        const osc2 = ctx.createOscillator();
        const osc3 = ctx.createOscillator();
        const gain = ctx.createGain();

        osc1.type = 'sine';
        osc2.type = 'sine';
        osc3.type = 'sine';

        osc1.frequency.setValueAtTime(587.33, now);        // D5
        osc2.frequency.setValueAtTime(880.00, now + 0.08); // A5
        osc3.frequency.setValueAtTime(1174.66, now + 0.16); // D6

        gain.gain.setValueAtTime(0.7, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.55);

        osc1.connect(gain);
        osc2.connect(gain);
        osc3.connect(gain);
        gain.connect(ctx.destination);

        osc1.start(now);
        osc1.stop(now + 0.12);

        osc2.start(now + 0.08);
        osc2.stop(now + 0.20);

        osc3.start(now + 0.16);
        osc3.stop(now + 0.55);
    } catch (e) {
        console.warn('Không thể phát âm thanh thông báo:', e);
    }
};

const setupUserWebSocket = () => {
    if (!window.Echo) return;
    const userData = authStore.user || JSON.parse(sessionStorage.getItem("user") || "{}");
    const userId = userData?.user_id || userData?.id;

    if (userId) {
        if (notificationUserId === userId) return;
        leaveNotificationChannel();
        notificationUserId = userId;

        const handleUserNotif = (e) => {
            playNotificationSound();
            authStore.incrementUnreadNotificationCount();
            if (showNotifDropdown.value) {
                fetchNotificationsList(); // Refresh list if open
            }
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: e.title || e.notification?.title || 'Thông báo mới',
                text: e.message || e.notification?.message || 'Bạn có thông báo mới',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
            showNotificationPopup.value = true;
            setTimeout(() => {
                showNotificationPopup.value = false;
            }, 4000);
        };

        window.Echo.private('user.' + userId)
            .listen('.UserNotificationEvent', handleUserNotif)
            .notification(handleUserNotif);
    }
};

watch(isLoggedIn, (val) => {
    if (val) {
        fetchUnreadNotificationCount();
        fetchHeaderRewardPoints();
        setupUserWebSocket();
    } else {
        authStore.resetUnreadNotificationCount();
        headerRewardPoints.value = 0;
        leaveNotificationChannel();
    }
}, { immediate: true });

onMounted(() => {
    window.addEventListener('has-new-unread-notifications', playNotificationSound);
    window.addEventListener('play-notif-sound', playNotificationSound);
});

onBeforeUnmount(() => {
    window.removeEventListener('has-new-unread-notifications', playNotificationSound);
    window.removeEventListener('play-notif-sound', playNotificationSound);
});

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
    x: window.innerWidth - 120, // Move a bit left to avoid edge
    y: window.innerHeight - 180 // Move higher to avoid overlapping with Chatbot
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
    catalogStore.fetchCategories();
    cartStore.fetchCount();

    window.addEventListener("user-updated", checkAuth);
    window.addEventListener("resize", handleViewportResize);
    document.addEventListener("click", handleDocumentClick);
    window.addEventListener("scroll", handleScroll);

    // adjust initial position for small screens
    if (window.innerWidth < 768) {
        flashSalePos.value.x = window.innerWidth - 120;
        flashSalePos.value.y = window.innerHeight - 180;
    }
});
onUnmounted(() => {
    window.removeEventListener("user-updated", checkAuth);
    window.removeEventListener("resize", handleViewportResize);
    document.removeEventListener("click", handleDocumentClick);
    window.removeEventListener("scroll", handleScroll);
    leaveNotificationChannel();
});
watch(
    () => route.fullPath,
    () => {
        checkAuth();
        closeAccountMenu();
        closeMobileMenu();
    },
);
</script>

<template>
    <header class="site-header" :class="{ 'is-scrolled': isScrolled }">
        <div class="header-inner">
            <!-- Wrapper Logo and Nav to stick them together -->
            <div class="header-left">
                <!-- Logo -->
                <router-link to="/" class="logo">
                    <div class="logo-container">
                        <img :src="BASE_URL + '/storage/logo/OCEAN_SPORT_LOGO_v0_tranperant.png?v=2'" alt="Logo"
                            class="logo-img" />
                    </div>
                </router-link>

                <!-- Navigation Links -->
                <nav class="main-nav">
                    <template v-if="catalogStore.isFetchingCategories && topCategories.length === 0">
                        <div v-for="i in 4" :key="i" class="nav-link" style="pointer-events: none;">
                            <div class="skeleton-nav-text"></div>
                        </div>
                    </template>
                    <template v-else>
                        <router-link v-for="cat in topCategories" :key="getCategoryId(cat)" :to="getCategoryRoute(cat)"
                            class="nav-link" :class="{ active: isCategoryActive(cat) }">
                            {{ cat.name }}
                        </router-link>
                    </template>
                    <router-link
                        to="/courts"
                        class="nav-link"
                        :class="{ active: isRouteActive('courts-list') || isRouteActive('court-detail') }"
                    >
                        Sân thể thao
                    </router-link>
                    <router-link
                        to="/posts"
                        class="nav-link"
                        :class="{ active: isRouteActive('post-list') || isRouteActive('post-detail') }"
                    >
                        Tin tức
                    </router-link>
                    <router-link
                        to="/coupon"
                        class="nav-link"
                        :class="{ active: isRouteActive('coupon') }"
                    >
                        Voucher
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
                <button type="button" class="icon-btn mobile-nav-toggle" :aria-expanded="isMobileMenuOpen"
                    aria-label="Mở menu điều hướng" @click.stop="toggleMobileMenu">
                    <AppIcon name="menu" stroke-width="2.2" />
                </button>

                <!-- Search -->
                <!-- Inline Expandable Search -->
                <div class="search-wrapper">
                    <div
                        class="search-container"
                        :class="{ 'is-expanded': isSearchExpanded }"
                    >
                        <input
                            id="site-search"
                            name="search"
                            type="text"
                            class="search-input"
                            v-model="searchQuery"
                            ref="searchInputRef"
                            @keyup.enter="executeSearch"
                            @blur="handleSearchBlur"
                            @focus="handleSearchFocus"
                            placeholder="Tìm kiếm sản phẩm..."
                            aria-label="Tìm kiếm sản phẩm"
                        />
                        <button
                            class="icon-btn search-icon-btn"
                            @click="toggleSearch"
                        >
                            <AppIcon name="search" />
                        </button>
                    </div>

                    <!-- Search dropdown results -->
                    <div class="search-dropdown-box" v-if="isSearchExpanded && showDropdownResult">
                        <div v-if="isSearching" class="search-msg">Đang tìm kiếm...</div>

                        <!-- Lịch sử tìm kiếm & Gợi ý (khi chưa gõ) -->
                        <div v-else-if="!searchQuery" class="search-suggestions">
                            <div class="search-history-section" v-if="searchHistory.length">
                                <div class="suggestion-header"
                                    style="padding: 10px 16px; font-weight: 600; font-size: 0.9rem; color: #2D3436; border-bottom: 1px solid #E9ECEF; display:flex; justify-content: space-between">
                                    <span>Lịch sử tìm kiếm</span>
                                </div>
                                <ul class="search-list">
                                    <li v-for="(history, i) in searchHistory" :key="'h-' + i" class="search-item"
                                        @click.stop="executeSearch(history.keyword)">
                                        <AppIcon name="search" size="14" style="margin-right: 8px; color: #636E72" />
                                        <div class="search-item-info">
                                            <div class="search-item-name" style="font-weight: 500;">{{ history.keyword
                                                }}</div>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <div class="recently-viewed-section" v-if="recentlyViewed.length">
                                <div class="suggestion-header"
                                    style="padding: 10px 16px; font-weight: 600; font-size: 0.9rem; color: #2D3436; border-bottom: 1px solid #E9ECEF;">
                                    <span>Sản phẩm vừa xem</span>
                                </div>
                                <ul class="search-list">
                                    <li v-for="item in recentlyViewed" :key="'r-' + item.product_id" class="search-item"
                                        @click.stop="goToProduct(item.product.slug)">
                                        <img :src="getImageUrl(item.product)" class="search-item-img" />
                                        <div class="search-item-info">
                                            <div class="search-item-name">{{ item.product.name }}</div>
                                            <div class="search-item-price">{{ formatPrice(item.product.min_price) }}
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="!searchHistory.length && !recentlyViewed.length" class="search-msg">
                                Nhập từ khóa để tìm kiếm...
                            </div>
                        </div>

                        <!-- Kết quả tìm kiếm -->
                        <div v-else-if="searchResults.length === 0 && searchQuery" class="search-msg">Không tìm thấy sản
                            phẩm phù hợp.</div>
                        <template v-else>
                            <ul class="search-list">
                                <li v-for="item in searchResults" :key="item.product_id" class="search-item"
                                    @click.stop="goToProduct(item.slug)">
                                    <img :src="getImageUrl(item)" class="search-item-img" />
                                    <div class="search-item-info">
                                        <div class="search-item-name">{{ item.name }}</div>
                                        <div class="search-item-price">{{ formatPrice(item.min_price) }}</div>
                                    </div>
                                </li>
                            </ul>
                            <div v-if="searchResults.length > 0" class="search-view-all" @click.stop="executeSearch()">
                                Xem tất cả kết quả
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Thông báo -->
                <div class="header-notif-container notif-dropdown" v-if="isLoggedIn">
                    <button class="icon-btn notif-icon-btn" @click.stop="toggleNotifMenu">
                        <div class="cart-icon-wrapper">
                            <AppIcon name="bell" />
                            <span v-if="unreadNotificationCount > 0" class="cart-badge">{{
                                unreadNotificationCount > 99 ? "99+" : unreadNotificationCount
                                }}</span>
                        </div>
                    </button>

                    <!-- Notif Dropdown Menu -->
                    <div class="notif-menu" v-show="showNotifDropdown">
                        <div class="notif-menu-inner">
                            <div class="notif-header">
                                <h3>Thông báo mới</h3>
                                <router-link to="/profile/notifications" @click="showNotifDropdown = false"
                                    class="notif-view-all">Xem tất cả</router-link>
                            </div>
                            <div class="notif-list" v-if="isFetchingNotif && notificationsList.length === 0">
                                <div class="notif-item" v-for="i in 3" :key="'skeleton-' + i">
                                    <div class="notif-icon-circle skeleton-pulse" style="width:36px;height:36px;background:#e2e8f0;flex-shrink:0;"></div>
                                    <div class="notif-content" style="width:100%">
                                        <div class="skeleton-pulse" style="height:14px;width:70%;margin-bottom:6px;border-radius:4px;background:#e2e8f0;"></div>
                                        <div class="skeleton-pulse" style="height:12px;width:90%;margin-bottom:6px;border-radius:4px;background:#e2e8f0;"></div>
                                        <div class="skeleton-pulse" style="height:10px;width:40%;border-radius:4px;background:#e2e8f0;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="notif-list" v-else-if="notificationsList.length > 0">
                                <div v-for="notif in notificationsList" :key="notif.id" class="notif-item"
                                    :class="{ unread: !notif.read_at }" @click="markAsRead(notif)">
                                    <div class="notif-icon-circle">
                                        <AppIcon name="bell" size="18" />
                                    </div>
                                    <div class="notif-content">
                                        <div class="notif-title">{{ notif.data?.title || 'Thông báo mới' }}</div>
                                        <div class="notif-desc">{{ notif.data?.message }}</div>
                                        <div class="notif-time">{{ new Date(notif.created_at).toLocaleString('vi-VN') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="notif-empty" v-else>
                                Không có thông báo nào.
                            </div>
                            <div class="notif-footer" v-if="notifPage < notifTotalPages">
                                <button class="btn-notif-loadmore" @click.stop="loadMoreNotifications"
                                    :disabled="isFetchingNotif">
                                    {{ isFetchingNotif ? 'Đang tải...' : 'Tải thêm' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <transition name="notif-popup-slide">
                        <div v-if="showNotificationPopup" class="new-notif-popup"
                            @click="router.push('/profile/notifications'); showNotificationPopup = false">
                            Bạn có thông báo mới
                        </div>
                    </transition>
                </div>

                <!-- Giỏ hàng -->
                <router-link to="/cart" id="cart-icon" class="icon-btn cart-icon-btn">
                    <div class="cart-icon-wrapper">
                        <AppIcon name="cart" />
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
                            <AppIcon name="user" />
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
                                <!-- Điểm thưởng mini trong header dropdown -->
                                <router-link v-if="headerRewardPoints >= 0" to="/profile/loyalty"
                                    class="header-loyalty-row" @click="closeAccountMenu">
                                    <span class="header-loyalty-icon">
                                        <AppIcon name="trophy" size="16" style="color: #f59e0b;" />
                                    </span>
                                    <span class="header-loyalty-label">Số điểm:</span>
                                    <span class="header-loyalty-pts">{{ new
                                        Intl.NumberFormat('vi-VN').format(headerRewardPoints) }} điểm</span>
                                    <span class="header-loyalty-arrow">›</span>
                                </router-link>
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
                        <p class="mobile-nav-eyebrow">Ocean Sport</p>
                        <h2 class="mobile-nav-title">Khám phá nhanh</h2>
                    </div>
                    <button type="button" class="icon-btn mobile-nav-close" @click="closeMobileMenu">
                        <AppIcon name="x" size="18" stroke-width="2.5" />
                    </button>
                </div>

                <div class="mobile-search-wrapper" style="position: relative; margin-bottom: 24px;">
                    <form class="mobile-search-box" @submit.prevent="executeSearch(); closeMobileMenu()">
                        <AppIcon name="search" size="18" class="mobile-search-icon" />
                        <input type="search" class="mobile-search-input" placeholder="Tìm kiếm sản phẩm..."
                            v-model="searchQuery" @focus="handleSearchFocus" @blur="handleSearchBlur" />
                    </form>

                    <!-- Search dropdown results for Mobile -->
                    <div class="search-dropdown-box mobile-dropdown" v-if="showDropdownResult"
                        style="position: absolute; top: calc(100% + 8px); right: auto; left: 0; width: 100%; z-index: 1000;">
                        <div v-if="isSearching" class="search-msg">Đang tìm kiếm...</div>

                        <!-- Lịch sử tìm kiếm & Gợi ý (khi chưa gõ) -->
                        <div v-else-if="!searchQuery" class="search-suggestions">
                            <div class="search-history-section" v-if="searchHistory.length">
                                <div class="suggestion-header"
                                    style="padding: 10px 16px; font-weight: 600; font-size: 0.9rem; color: #2D3436; border-bottom: 1px solid #E9ECEF; display:flex; justify-content: space-between">
                                    <span>Lịch sử tìm kiếm</span>
                                </div>
                                <ul class="search-list">
                                    <li v-for="(history, i) in searchHistory" :key="'hm-' + i" class="search-item"
                                        @click.stop="executeSearch(history.keyword); closeMobileMenu()">
                                        <AppIcon name="search" size="14" style="margin-right: 8px; color: #636E72" />
                                        <div class="search-item-info">
                                            <div class="search-item-name" style="font-weight: 500;">{{ history.keyword
                                                }}
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <div class="recently-viewed-section" v-if="recentlyViewed.length">
                                <div class="suggestion-header"
                                    style="padding: 10px 16px; font-weight: 600; font-size: 0.9rem; color: #2D3436; border-bottom: 1px solid #E9ECEF;">
                                    <span>Sản phẩm vừa xem</span>
                                </div>
                                <ul class="search-list">
                                    <li v-for="item in recentlyViewed" :key="'rm-' + item.product_id" class="search-item"
                                        @click.stop="goToProduct(item.product.slug); closeMobileMenu()">
                                        <img :src="getImageUrl(item.product)" class="search-item-img" />
                                        <div class="search-item-info">
                                            <div class="search-item-name">{{ item.product.name }}</div>
                                            <div class="search-item-price">{{ formatPrice(item.product.min_price) }}
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="!searchHistory.length && !recentlyViewed.length" class="search-msg">
                                Nhập từ khóa để tìm kiếm...
                            </div>
                        </div>

                        <!-- Kết quả tìm kiếm -->
                        <div v-else-if="searchResults.length === 0 && searchQuery" class="search-msg">Không tìm thấy sản
                            phẩm phù hợp.</div>
                        <template v-else>
                            <ul class="search-list">
                                <li v-for="item in searchResults" :key="'sm-' + item.product_id" class="search-item"
                                    @click.stop="goToProduct(item.slug); closeMobileMenu()">
                                    <img :src="getImageUrl(item)" class="search-item-img" />
                                    <div class="search-item-info">
                                        <div class="search-item-name">{{ item.name }}</div>
                                        <div class="search-item-price">{{ formatPrice(item.min_price) }}</div>
                                    </div>
                                </li>
                            </ul>
                            <div v-if="searchResults.length > 0" class="search-view-all"
                                @click.stop="executeSearch(); closeMobileMenu()">
                                Xem tất cả kết quả
                            </div>
                        </template>
                    </div>
                </div>

                <nav class="mobile-nav-links">
                    <router-link v-for="cat in topCategories" :key="`mobile-${getCategoryId(cat)}`"
                        :to="getCategoryRoute(cat)" class="mobile-nav-link" :class="{ active: isCategoryActive(cat) }"
                        @click="closeMobileMenu">
                        {{ cat.name }}
                    </router-link>
                    <router-link
                        to="/courts"
                        class="mobile-nav-link"
                        :class="{ active: isRouteActive('courts-list') || isRouteActive('court-detail') }"
                        @click="closeMobileMenu"
                    >
                        Sân thể thao
                    </router-link>
                    <router-link
                        to="/posts"
                        class="mobile-nav-link"
                        :class="{ active: isRouteActive('post-list') || isRouteActive('post-detail') }"
                        @click="closeMobileMenu"
                    >
                        Tin tức
                    </router-link>
                    <router-link
                        to="/contact"
                        class="mobile-nav-link"
                        :class="{ active: isRouteActive('contact') }"
                        @click="closeMobileMenu"
                    >
                        Liên hệ
                    </router-link>
                    <router-link to="/coupon" class="mobile-nav-link" :class="{ active: isRouteActive('coupon') }"
                        @click="closeMobileMenu">
                        Săn voucher
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
                        <router-link to="/profile" class="mobile-account-link" @click="closeMobileMenu">Tài khoản của
                            tôi</router-link>
                        <router-link v-if="isAdmin" to="/admin" class="mobile-account-link"
                            @click="closeMobileMenu">Quản trị</router-link>
                        <router-link to="/profile/notifications" class="mobile-account-link"
                            @click="closeMobileMenu">Thông báo</router-link>
                        <button type="button" class="mobile-account-link mobile-account-link--danger"
                            @click="handleLogout">
                            Đăng xuất
                        </button>
                    </template>
                    <template v-else>
                        <router-link to="/client/login" class="mobile-account-link" @click="closeMobileMenu">Đăng
                            nhập</router-link>
                        <router-link to="/client/register" class="mobile-account-link" @click="closeMobileMenu">Đăng
                            ký</router-link>
                    </template>
                </div>
            </div>
        </div>
    </Transition>

    <div class="floating-flash-sale" @click="handleFlashSaleClick">
        <div class="flash-sale-badge">
            <AppIcon name="zap" class="flash-sale-icon" size="24" />
            <span class="flash-sale-text">FLASH SALE</span>
        </div>
    </div>
</template>

<style scoped>
.site-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid #F8F9FA;
    position: sticky;
    top: 0;
    z-index: 1030;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.header-inner {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 40px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Scrolled Header Changes */
.site-header.is-scrolled {
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    background: rgba(255, 255, 255, 0.98);
}

.site-header.is-scrolled .header-inner {
    height: 54px;
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
    display: flex;
    align-items: center;
}

.logo-container {
    width: 65px;
    height: 65px;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.logo-img {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.site-header.is-scrolled .logo-container {
    width: 50px;
    height: 50px;
}

/* NAVIGATION */
.main-nav {
    display: flex;
    align-items: stretch;
    gap: 32px;
    height: 100%;
}

.nav-link {
    display: inline-flex;
    align-items: center;
    height: 100%;
    padding: 0 2px;
    text-decoration: none;
    color: #555;
    font-weight: 600;
    font-size: 0.95rem;
    line-height: 1;
    position: relative;
    transition: color 0.2s;
    text-transform: capitalize;
}

.nav-link:hover {
    color: var(--primary);
}

.nav-link.active {
    color: var(--primary);
}

.nav-link.active::after {
    content: "";
    position: absolute;
    left: 2px;
    right: 2px;
    bottom: calc(50% - 18px);
    height: 2px;
    background-color: var(--primary);
    border-radius: 2px;
    opacity: 1;
    transform: scaleX(1);
    transform-origin: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.site-header.is-scrolled .nav-link.active::after {
    bottom: 12px;
    opacity: 0;
    transform: scaleX(0);
}

/* HEADER ACTIONS */
.header-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

.mobile-nav-toggle {
    display: none !important;
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
    transition: background 0.2s, transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.site-header.is-scrolled .icon-btn {
    transform: scale(0.9);
}

.site-header.is-scrolled .search-icon-btn {
    transform: translateY(-50%) scale(0.9);
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

.search-container {
    display: flex;
    align-items: center;
    position: relative;
    width: 40px;
    /* matched to new height */
    height: 40px;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.3s, box-shadow 0.3s;
    overflow: hidden;
    border-radius: 20px;
    background: transparent;
}

.search-container.is-expanded {
    width: 300px;
    max-width: calc(100vw - 120px);
    background: #f1f5f9;
    padding-left: 16px;
}

.search-container.is-expanded:focus-within {
    background: var(--card-bg);
    box-shadow: 0 0 0 1.5px var(--primary);
    /* focus ring */
}

.search-input {
    border: none;
    background: transparent;
    outline: none;
    width: 0;
    opacity: 0;
    transition:
        opacity 0.3s,
        width 0.3s;
    font-size: 0.95rem;
    color: var(--text-main);
}

.search-container.is-expanded .search-input {
    width: 100%;
    flex: 1;
    opacity: 1;
    padding-right: 40px;
    /* prevent overlap with absolute icon */
}

.search-icon-btn {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #111;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: color 0.2s;
}

.search-icon-btn:hover {
    color: var(--primary);
}

/* SEARCH DROPDOWN */
.search-dropdown-box {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: 380px;
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    z-index: 300;
}

.search-msg {
    padding: 24px;
    text-align: center;
    color: #64748b;
    font-size: 0.95rem;
}

.search-list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 400px;
    overflow-y: auto;
}

.search-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.2s;
}

.search-item:last-child {
    border-bottom: none;
}

.search-item:hover {
    background: #f8fafc;
}

.search-item-img {
    width: 54px;
    height: 54px;
    border-radius: 8px;
    object-fit: cover;
    background: #e2e8f0;
    flex-shrink: 0;
}

.search-item-info {
    flex: 1;
    overflow: hidden;
}

.search-item-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-main);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 4px;
    line-height: 1.3;
}

.search-item-price {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--primary);
    /* Ocean blue theme */
}

.search-view-all {
    padding: 14px;
    text-align: center;
    background: #f8fafc;
    color: var(--primary);
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background 0.2s;
    border-top: 1px solid #e2e8f0;
}

.search-view-all:hover {
    background: #e2e8f0;
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
    background: var(--primary);
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
    background: var(--primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
}

/* SKELETON LINK IN NAV */
.skeleton-nav-text {
    width: 70px;
    height: 16px;
    background: #e2e8f0;
    border-radius: 4px;
    animation: shimmer 1.5s infinite linear;
    background-image: linear-gradient(
        90deg,
        #e2e8f0 0px,
        #f8fafc 40px,
        #e2e8f0 80px
    );
    background-size: 200px 100%;
}
@keyframes shimmer {
    0% { background-position: -200px 0; }
    100% { background-position: 200px 0; }
}

/* ============================================
   HEADER ACTIONS (RIGHT SIDE)
============================================ */
/* NOTIFICATION DROPDOWN */
.notif-dropdown {
    position: relative;
}

.notif-menu {
    position: absolute;
    top: 100%;
    right: 0;
    padding-top: 12px;
    width: 340px;
    z-index: 200;
}

.notif-menu-inner {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
}

.notif-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.notif-view-all {
    font-size: 0.85rem;
    color: #E63B6F;
    text-decoration: none;
    font-weight: 600;
}

.notif-list {
    max-height: 380px;
    overflow-y: auto;
}

.notif-item {
    display: flex;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.2s;
}

.notif-item:last-child {
    border-bottom: none;
}

.notif-item:hover {
    background: #f8fafc;
}

.notif-item.unread {
    background: #fff0f3;
}

.notif-icon-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #E63B6F;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notif-content {
    flex: 1;
}

.notif-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 4px;
}

.notif-desc {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 6px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.notif-time {
    font-size: 0.75rem;
    color: #94a3b8;
}

.notif-empty {
    padding: 30px 20px;
    text-align: center;
    color: #64748b;
    font-size: 0.9rem;
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
    background: var(--card-bg);
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
    background: var(--primary);
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

/* Loyalty points row in header dropdown */
.header-loyalty-container {
    background: linear-gradient(135deg, #fff7ed, #fef3f2);
    border-radius: 10px;
    margin: 4px 0;
    border: 1px solid #fed7aa;
    padding: 10px 12px;
}

.header-loyalty-row {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none !important;
    padding: 10px 12px;
}

.header-loyalty-icon {
    font-size: 1rem;
    flex-shrink: 0;
}

.header-loyalty-label {
    font-size: 0.78rem;
    color: #92400e;
    font-weight: 500;
}

.header-loyalty-pts {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--primary);
    flex: 1;
}

.header-loyalty-arrow {
    color: var(--primary);
    font-size: 1.1rem;
    font-weight: 700;
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
    background: var(--card-bg);
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
    color: var(--text-main);
}

.mobile-nav-close {
    flex-shrink: 0;
}

.mobile-search-box,
.mobile-account-link {
    width: 100%;
    border: 1px solid #e2e8f0;
    background: var(--card-bg);
    color: var(--text-main);
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

.mobile-search-box:focus-within,
.mobile-account-link:hover {
    border-color: rgba(230, 59, 111, 0.28);
    background: #fff7f9;
}

.mobile-search-input {
    width: 100%;
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.95rem;
    font-weight: 500;
    font-family: inherit;
    color: inherit;
    padding: 0;
}

.mobile-search-input::placeholder {
    color: #94a3b8;
    font-weight: 500;
}

.mobile-search-icon {
    color: #64748b;
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
    color: var(--primary);
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
    color: var(--text-main);
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

/* FLOATING FLASH SALE (FIXED) */
.floating-flash-sale {
    position: fixed;
    z-index: 9999;
    bottom: 120px;
    right: 20px;
    cursor: pointer;
    user-select: none;
    /* transition: transform 0.2s ease, opacity 0.2s ease; */
}

.floating-flash-sale:hover {
    transform: translateY(-3px);
}

.floating-flash-sale:active {
    transform: translateY(0) scale(0.95);
}

.flash-sale-badge {
    display: flex;
    align-items: center;
    background: var(--primary);
    color: #fff;
    height: 60px;
    width: 60px;
    border-radius: 30px;
    padding: 0 18px;
    /* 18px + 18px + 24px icon = 60px */
    overflow: hidden;
    white-space: nowrap;
    box-shadow: 0 4px 15px rgba(230, 59, 111, 0.4);
    position: relative;
    z-index: 1;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    /* Bouncy effect */
}

.floating-flash-sale:hover .flash-sale-badge {
    width: 170px;
    box-shadow: 0 8px 25px rgba(230, 59, 111, 0.5);
}

.flash-sale-icon {
    flex-shrink: 0;
}

.flash-sale-text {
    font-weight: 800;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
    margin-left: 12px;
    opacity: 0;
    transform: translateX(-15px);
    transition: all 0.3s ease;
}

.floating-flash-sale:hover .flash-sale-text {
    opacity: 1;
    transform: translateX(0);
    transition-delay: 0.05s;
}

/* Vòng tròn sóng lan tỏa (Sonar Ping) */
.flash-sale-badge::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 30px;
    background: var(--primary);
    opacity: 0.6;
    z-index: -1;
    animation: sonar-ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
}

.flash-sale-badge svg {
    color: #fff;
    fill: #fff;
}

@keyframes sonar-ping {
    0% {
        transform: scale(1);
        opacity: 1;
    }

    100% {
        transform: scale(1.35, 1.6);
        opacity: 0;
    }
}

.header-notif-container {
    position: relative;
    display: flex;
    align-items: center;
}

.new-notif-popup {
    position: absolute;
    top: calc(100% + 12px);
    right: -10px;
    /* Căn phải hoặc tùy chỉnh */
    background: var(--card-bg);
    color: #1a2b4a;
    padding: 10px 16px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    font-size: 0.9rem;
    font-weight: 500;
    white-space: nowrap;
    z-index: 1000;
    cursor: pointer;
    border: 1px solid #e2e8f0;
}

.new-notif-popup::before {
    content: '';
    position: absolute;
    top: -6px;
    right: 20px;
    width: 12px;
    height: 12px;
    background: var(--card-bg);
    transform: rotate(45deg);
    border-top: 1px solid #e2e8f0;
    border-left: 1px solid #e2e8f0;
}

.notif-popup-slide-enter-active,
.notif-popup-slide-leave-active {
    transition: all 0.3s ease;
}

.notif-popup-slide-enter-from,
.notif-popup-slide-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

@media (max-width: 1024px) {
    .main-nav {
        gap: 20px;
    }
}

@media (max-width: 991px) {
    .header-inner {
        padding: 0 16px;
    }

    .header-left {
        gap: 12px;
    }

    .main-nav {
        display: none;
    }

    .search-wrapper {
        display: none !important;
    }

    .mobile-nav-toggle {
        display: inline-flex !important;
        order: 99;
        /* Đưa nút menu sang góc phải tận cùng */
    }

    .account-dropdown {
        display: none;
    }

    .header-actions {
        gap: 8px;
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

.notif-footer {
    padding: 10px;
    text-align: center;
    border-top: 1px solid #f1f5f9;
}

.btn-notif-loadmore {
    background: transparent;
    border: none;
    color: var(--primary);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: color 0.2s;
}

.btn-notif-loadmore:hover:not(:disabled) {
    color: #c4305d;
    text-decoration: underline;
}

.btn-notif-loadmore:disabled {
    color: #94a3b8;
    cursor: not-allowed;
}
</style>
