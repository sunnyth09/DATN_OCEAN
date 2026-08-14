/**
 * SessionSync — Đồng bộ sessionStorage giữa các tab
 *
 * Cách hoạt động:
 *  1. Tab mới mở lên → kiểm tra sessionStorage trống → gửi "yêu cầu share"
 *  2. Tab cũ đang mở nhận được yêu cầu → ghi dữ liệu vào localStorage trong 50ms
 *  3. Tab mới nhận dữ liệu từ storage event → copy vào sessionStorage → xóa ngay
 *
 * Kết quả:
 *  - Token vẫn CHỈ sống trong sessionStorage (không lưu lâu dài)
 *  - Mở link sang tab mới vẫn giữ được session
 *  - Đóng hết browser → session mất hoàn toàn (an toàn hơn localStorage)
 */

const KEYS = {
    TOKEN: 'auth_token',
    USER: 'user',
    REQUEST: '__session_sync_request__',
    SHARE: '__session_sync_share__',
};

/**
 * Lắng nghe sự kiện storage để nhận/gửi session data
 */
function setupListener() {
    window.addEventListener('storage', (event) => {
        // Tab khác đang yêu cầu nhận session
        if (event.key === KEYS.REQUEST && event.newValue) {
            const token = sessionStorage.getItem(KEYS.TOKEN);
            const user  = sessionStorage.getItem(KEYS.USER);

            if (token && user) {
                // Ghi vào localStorage để tab kia đọc — xóa ngay sau
                localStorage.setItem(KEYS.SHARE, JSON.stringify({ token, user }));
                // Xóa ngay lập tức (chỉ để trigger storage event, không lưu lâu)
                localStorage.removeItem(KEYS.SHARE);
            }
            return;
        }

        // Tab này nhận được dữ liệu được share
        if (event.key === KEYS.SHARE && event.newValue) {
            // Chỉ nhận nếu tab này chưa có session
            if (!sessionStorage.getItem(KEYS.TOKEN)) {
                try {
                    const { token, user } = JSON.parse(event.newValue);
                    if (token && user) {
                        sessionStorage.setItem(KEYS.TOKEN, token);
                        sessionStorage.setItem(KEYS.USER, user);
                    }
                } catch {
                    // Bỏ qua dữ liệu lỗi
                }
            }
            return;
        }

        // Tab khác đã logout → tab này cũng cần logout
        if (event.key === KEYS.TOKEN && !event.newValue && !event.oldValue) {
            // Không có gì để làm
        }
        if (event.key === '__session_logout__' && event.newValue) {
            sessionStorage.removeItem(KEYS.TOKEN);
            sessionStorage.removeItem(KEYS.USER);
            localStorage.removeItem('__session_logout__');

            // Redirect về login nếu cần
            if (window.location.pathname !== '/client/login') {
                window.dispatchEvent(new CustomEvent('auth-logout'));
            }
        }
    });
}

/**
 * Yêu cầu nhận session từ tab khác đang mở
 * Gọi khi app khởi động và sessionStorage trống
 */
function requestSessionFromOtherTabs() {
    return new Promise((resolve) => {
        if (sessionStorage.getItem(KEYS.TOKEN)) {
            resolve(true); // Đã có session, không cần làm gì
            return;
        }

        // Gửi yêu cầu sang tab khác
        localStorage.setItem(KEYS.REQUEST, Date.now().toString());
        localStorage.removeItem(KEYS.REQUEST);

        // Đợi tối đa 150ms để tab khác phản hồi
        const timeout = setTimeout(() => {
            resolve(!!sessionStorage.getItem(KEYS.TOKEN));
        }, 150);

        // Nếu nhận được phản hồi sớm hơn → resolve luôn
        const onShare = (event) => {
            if (event.key === KEYS.SHARE && event.newValue) {
                clearTimeout(timeout);
                window.removeEventListener('storage', onShare);
                // Chờ thêm 20ms để setupListener() xử lý trước
                setTimeout(() => resolve(!!sessionStorage.getItem(KEYS.TOKEN)), 20);
            }
        };
        window.addEventListener('storage', onShare);
    });
}

/**
 * Broadcast logout sang tất cả các tab khác
 */
function broadcastLogout() {
    localStorage.setItem('__session_logout__', Date.now().toString());
    localStorage.removeItem('__session_logout__');
}

/**
 * Khởi tạo SessionSync — gọi một lần duy nhất trong main.js
 */
export async function initSessionSync() {
    setupListener();
    await requestSessionFromOtherTabs();
}

export { broadcastLogout };
