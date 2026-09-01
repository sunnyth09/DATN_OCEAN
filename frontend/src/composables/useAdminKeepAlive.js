import { ref, onActivated } from 'vue';

// Global registry for tracking mutated resources across admin pages
const dirtyResources = ref(new Set());

/**
 * Đánh dấu một resource đã bị thay đổi (Thêm/Sửa/Xóa) để trang danh sách tự động làm mới khi kích hoạt
 * @param {string} resourceKey - Tên resource (ví dụ: 'products', 'orders', 'categories', 'courts', 'stats')
 */
export const markAdminResourceDirty = (resourceKey) => {
  if (resourceKey) {
    dirtyResources.value.add(resourceKey);
  }
};

/**
 * Xóa cờ dirty của resource sau khi đã làm mới thành công
 * @param {string} resourceKey
 */
export const clearAdminResourceDirty = (resourceKey) => {
  if (resourceKey) {
    dirtyResources.value.delete(resourceKey);
  }
};

/**
 * Composable quản lý vòng đời Keep-Alive và tự động SWR Revalidation cho các trang Admin
 * @param {Object} options
 * @param {string} [options.resourceKey] - Tên resource đại diện
 * @param {Function} options.fetchFn - Hàm fetch dữ liệu của trang
 * @param {number} [options.ttl=180000] - Thời gian sống của cache (mặc định 3 phút = 180,000ms)
 */
export function useAdminKeepAlive({ resourceKey, fetchFn, ttl = 180000 }) {
  const lastFetchedAt = ref(Date.now());

  onActivated(async () => {
    const isDirty = resourceKey && dirtyResources.value.has(resourceKey);
    const isExpired = Date.now() - lastFetchedAt.value > ttl;

    if (isDirty || isExpired) {
      if (resourceKey) {
        clearAdminResourceDirty(resourceKey);
      }
      lastFetchedAt.value = Date.now();
      if (typeof fetchFn === 'function') {
        try {
          await fetchFn({ background: true });
        } catch (e) {
          console.warn(`[useAdminKeepAlive] Silent revalidation error for ${resourceKey}:`, e);
        }
      }
    }
  });

  const recordFetched = () => {
    lastFetchedAt.value = Date.now();
    if (resourceKey) {
      clearAdminResourceDirty(resourceKey);
    }
  };

  return {
    lastFetchedAt,
    recordFetched,
    markDirty: () => resourceKey && markAdminResourceDirty(resourceKey),
  };
}
