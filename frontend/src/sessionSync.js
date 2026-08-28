/**
 * SessionSync — Quản lý đồng bộ sự kiện đăng xuất giữa các Tab
 */

const KEYS = {
    TOKEN: 'auth_token',
    USER: 'user',
    LOGOUT: '__session_logout__',
};

/**
 * Lắng nghe sự kiện storage để nhận thông báo Đăng xuất từ các tab khác
 */
function setupListener() {
    window.addEventListener('storage', (event) => {
        // Tab khác đã bấm Đăng xuất tường minh -> tab này cũng đồng bộ đăng xuất
        if (event.key === KEYS.LOGOUT && event.newValue) {
            localStorage.removeItem(KEYS.TOKEN);
            localStorage.removeItem(KEYS.USER);
            sessionStorage.removeItem(KEYS.TOKEN);
            sessionStorage.removeItem(KEYS.USER);

            window.dispatchEvent(new CustomEvent('auth-logout'));

            if (window.location.pathname.startsWith('/profile') || window.location.pathname.startsWith('/admin')) {
                const currentPath = window.location.pathname + window.location.search;
                window.location.href = `/client/login?redirect=${encodeURIComponent(currentPath)}`;
            }
        }
    });
}

/**
 * Broadcast sự kiện đăng xuất sang tất cả các tab khác khi người dùng chủ động Logout
 */
export function broadcastLogout() {
    localStorage.setItem(KEYS.LOGOUT, Date.now().toString());
    // Giữ một khoảng ngắn rồi xóa để đảm bảo event được trigger qua các tab
    setTimeout(() => {
        localStorage.removeItem(KEYS.LOGOUT);
    }, 100);
}

/**
 * Khởi tạo SessionSync — gọi một lần duy nhất trong main.js
 */
export async function initSessionSync() {
    setupListener();
}
