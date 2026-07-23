<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/axios';
import { getAbsoluteUrl } from '@/utils/url';

const getFullUrl = (path) => {
    if (!path) return '';
    return getAbsoluteUrl(path);
};

const attendances = ref([]);
const loading = ref(true);
const filters = ref({ from_date: '', to_date: '', status: '', is_flagged: '' });
const pagination = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, prev_page_url: null, next_page_url: null });

const visiblePages = computed(() => {
    const pages = [];
    const t = pagination.value.last_page, c = pagination.value.current_page;
    for (let i = Math.max(1, c - 2); i <= Math.min(t, c + 2); i++) pages.push(i);
    return pages;
});

// Toast
const toastVisible = ref(false);
let toastTimer = null;
const toast = ref({ message: '', type: 'success' });
const showToast = (msg, type = 'success') => {
    toast.value = { message: msg, type };
    toastVisible.value = true;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastVisible.value = false; }, 3000);
};

const formatTime = (d) => d ? new Date(d).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) : '';
const formatDate = (d) => d ? new Date(d).toLocaleDateString('vi-VN') : '';
const getStatusLabel = (s) => ({ 'checked_in': 'Đang làm', 'checked_out': 'Hoàn tất', 'late': 'Đi trễ', 'missing_checkout': 'Thiếu CO' }[s] || s || 'N/A');
const getStatusClass = (s) => ({ 'checked_in': 'st-working', 'checked_out': 'st-done', 'late': 'st-late', 'missing_checkout': 'st-missing' }[s] || 'st-missing');

const resetFilters = () => { filters.value = { from_date: '', to_date: '', status: '', is_flagged: '' }; fetchAttendances(1); };

const fetchAttendances = async (page = 1) => {
    loading.value = true;
    try {
        const p = new URLSearchParams();
        p.append('page', page);
        if (filters.value.from_date) p.append('from_date', filters.value.from_date);
        if (filters.value.to_date) p.append('to_date', filters.value.to_date);
        if (filters.value.status) p.append('status', filters.value.status);
        if (filters.value.is_flagged !== '') p.append('is_flagged', filters.value.is_flagged);

        const res = await api.get(`/admin/attendance?${p.toString()}`);
        if (res.data.status === 'success') {
            attendances.value = res.data.data.data;
            pagination.value = {
                current_page: res.data.data.current_page, last_page: res.data.data.last_page,
                total: res.data.data.total, from: res.data.data.from, to: res.data.data.to,
                prev_page_url: res.data.data.prev_page_url, next_page_url: res.data.data.next_page_url,
            };
        }
    } catch { attendances.value = []; }
    finally { loading.value = false; }
};

// === COMPARE MODAL ===
const showCompareModal = ref(false);
const compareItem = ref(null);
const compareType = ref('checkin');
const compareSelfie = computed(() => {
    if (!compareItem.value) return null;
    return compareType.value === 'checkout' ? compareItem.value.check_out_image_path : compareItem.value.image_path;
});

const openCompare = (item, type = 'checkin') => {
    compareItem.value = item;
    compareType.value = type;
    showCompareModal.value = true;
};

const flagFromCompare = async (isFlagged) => {
    try {
        await api.put(`/admin/attendance/${compareItem.value.id}/flag`, { is_flagged: isFlagged, flag_note: isFlagged ? 'Gắn cờ từ so sánh ảnh' : null });
        showToast(isFlagged ? 'Đã gắn cờ!' : 'Đã bỏ cờ!');
        showCompareModal.value = false;
        fetchAttendances(pagination.value.current_page);
    } catch { showToast('Lỗi.', 'error'); }
};

// === FLAG MODAL ===
const showFlagModal = ref(false);
const flagItem = ref(null);
const flagNote = ref('');
const flagging = ref(false);

const openFlagModal = (item) => {
    flagItem.value = item;
    flagNote.value = item.flag_note || '';
    showFlagModal.value = true;
};

const submitFlag = async () => {
    flagging.value = true;
    try {
        const isFlagged = !flagItem.value.is_flagged;
        await api.put(`/admin/attendance/${flagItem.value.id}/flag`, { is_flagged: isFlagged, flag_note: isFlagged ? flagNote.value : null });
        showToast(isFlagged ? 'Đã gắn cờ!' : 'Đã bỏ cờ!');
        showFlagModal.value = false;
        fetchAttendances(pagination.value.current_page);
    } catch { showToast('Lỗi.', 'error'); }
    finally { flagging.value = false; }
};

onMounted(() => { fetchAttendances(); });
</script>
<template>
  <div class="admin-al animate-in">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h2 class="section-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>Quản lý lịch sử Chấm Công
        </h2>
        <p class="section-desc">Theo dõi check-in/check-out, gắn cờ bất thường và so sánh ảnh nhận diện.</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="ocean-card filter-bar">
      <div class="filter-grid">
        <div class="filter-item">
          <label>Từ ngày</label>
          <input type="date" class="al-input" v-model="filters.from_date" @change="fetchAttendances(1)" />
        </div>
        <div class="filter-item">
          <label>Đến ngày</label>
          <input type="date" class="al-input" v-model="filters.to_date" @change="fetchAttendances(1)" />
        </div>
        <div class="filter-item">
          <label>Trạng thái</label>
          <select class="al-input al-select" v-model="filters.status" @change="fetchAttendances(1)">
            <option value="">Tất cả</option>
            <option value="checked_in">Đang làm</option>
            <option value="checked_out">Hoàn tất</option>
          </select>
        </div>
        <div class="filter-item">
          <label>Gắn cờ</label>
          <select class="al-input al-select" v-model="filters.is_flagged" @change="fetchAttendances(1)">
            <option value="">Tất cả</option>
            <option value="1">Có cờ</option>
            <option value="0">Bình thường</option>
          </select>
        </div>
        <div class="filter-item filter-actions">
          <button class="btn-reset" @click="resetFilters">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
            Xóa lọc
          </button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="ocean-card table-wrapper">
      <table class="al-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nhân sự</th>
            <th>Ca</th>
            <th>Vị trí</th>
            <th>Ngày</th>
            <th>Check-in</th>
            <th>Check-out</th>
            <th>K/cách</th>
            <th>Ảnh</th>
            <th class="status-th">Trạng thái</th>
            <th class="actions-th">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="11" class="empty-cell"><div class="al-spinner"></div></td>
          </tr>
          <tr v-else-if="attendances.length === 0">
            <td colspan="11" class="empty-cell">Chưa có dữ liệu chấm công.</td>
          </tr>
          <tr v-else v-for="item in attendances" :key="item.id" class="al-row" :class="{ 'row-flagged': item.is_flagged }">
            <td class="id-cell">#{{ item.id }}</td>
            <td>
              <div class="user-info-cell">
                <img v-if="item.user_avatar" :src="getFullUrl(item.user_avatar)" class="user-avatar" />
                <div v-else class="avatar-circle">{{ (item.user_name || '?')[0].toUpperCase() }}</div>
                <div>
                  <div class="user-name">{{ item.user_name || 'N/A' }}</div>
                  <span class="user-role">{{ item.user_role }}</span>
                </div>
              </div>
            </td>
            <td><span class="shift-badge">{{ item.shift_name || 'N/A' }}</span></td>
            <td class="location-cell">{{ item.location_name || 'N/A' }}</td>
            <td class="date-cell">{{ formatDate(item.work_date) }}</td>
            <td><span class="time-in">{{ formatTime(item.check_in_at) }}</span></td>
            <td>
              <span v-if="item.check_out_at" class="time-out">{{ formatTime(item.check_out_at) }}</span>
              <span v-else class="pending-badge">Chưa</span>
            </td>
            <td>
              <span v-if="item.check_in_distance_meters != null" class="distance-badge"
                    :class="item.check_in_distance_meters > 200 ? 'dist-far' : 'dist-ok'">
                {{ Math.round(item.check_in_distance_meters) }}m
              </span>
              <span v-else class="text-muted">—</span>
            </td>
            <td>
              <div class="selfie-group">
                <img v-if="item.image_path" :src="getFullUrl(item.image_path)" class="selfie-thumb" @click="openCompare(item)" title="Xem ảnh check-in" />
                <img v-if="item.check_out_image_path" :src="getFullUrl(item.check_out_image_path)" class="selfie-thumb selfie-checkout" @click="openCompare(item, 'checkout')" title="Xem ảnh check-out" />
                <span v-if="!item.image_path && !item.check_out_image_path" class="text-muted">—</span>
              </div>
            </td>
            <td class="status-cell">
              <span class="al-status" :class="getStatusClass(item.status)">{{ getStatusLabel(item.status) }}</span>
              <span v-if="item.is_flagged" class="flag-icon" title="Bất thường">🚩</span>
            </td>
            <td class="actions-cell">
              <button class="btn-flag" @click="openFlagModal(item)" :title="item.is_flagged ? 'Bỏ cờ' : 'Gắn cờ'" :class="{ 'flagged': item.is_flagged }">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="al-pagination" v-if="pagination.last_page > 1">
        <span class="page-info">{{ pagination.from }} - {{ pagination.to }} / {{ pagination.total }}</span>
        <div class="page-buttons">
          <button class="page-btn" :disabled="!pagination.prev_page_url" @click="fetchAttendances(pagination.current_page - 1)">Trước</button>
          <button v-for="p in visiblePages" :key="p" class="page-btn" :class="{ active: pagination.current_page === p }" @click="fetchAttendances(p)">{{ p }}</button>
          <button class="page-btn" :disabled="!pagination.next_page_url" @click="fetchAttendances(pagination.current_page + 1)">Sau</button>
        </div>
      </div>
    </div>

    <!-- ===== MODAL: So sánh ảnh ===== -->
    <Teleport to="body">
    <Transition name="al-modal">
      <div v-if="showCompareModal" class="al-modal-overlay" @click.self="showCompareModal = false">
        <div class="al-modal-box al-modal-wide">
          <div class="al-modal-head">
            <h3>So sánh Ảnh Nhân viên</h3>
            <button class="al-btn-close" @click="showCompareModal = false">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="al-modal-body">
            <div class="compare-user-info">
              <strong>{{ compareItem?.user_name }}</strong>
              <span>{{ compareItem?.user_role }}</span>
            </div>
            <div class="compare-grid">
              <div class="compare-side">
                <p class="compare-label">Ảnh hồ sơ</p>
                <img v-if="compareItem?.user_avatar" :src="getFullUrl(compareItem.user_avatar)" class="compare-img" />
                <div v-else class="compare-placeholder">Chưa có ảnh</div>
              </div>
              <div class="compare-side">
                <p class="compare-label">{{ compareType === 'checkout' ? 'Ảnh check-out' : 'Ảnh check-in' }}</p>
                <img v-if="compareSelfie" :src="getFullUrl(compareSelfie)" class="compare-img" />
                <div v-else class="compare-placeholder">Không có ảnh</div>
              </div>
            </div>
            <div class="compare-actions">
              <button v-if="!compareItem?.is_flagged" class="al-btn-danger" @click="flagFromCompare(true)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                Gắn cờ bất thường
              </button>
              <button v-else class="al-btn-success" @click="flagFromCompare(false)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Bỏ cờ
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
    </Teleport>

    <!-- ===== MODAL: Flag ===== -->
    <Teleport to="body">
    <Transition name="al-modal">
      <div v-if="showFlagModal" class="al-modal-overlay" @click.self="showFlagModal = false">
        <div class="al-modal-box" style="max-width:440px">
          <div class="al-modal-head" :class="{ 'al-modal-head-danger': !flagItem?.is_flagged }">
            <h3>{{ flagItem?.is_flagged ? 'Bỏ cờ bất thường' : 'Gắn cờ bất thường' }}</h3>
            <button class="al-btn-close" @click="showFlagModal = false">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="al-modal-body">
            <div v-if="!flagItem?.is_flagged" class="al-form-group">
              <label>Lý do gắn cờ</label>
              <textarea v-model="flagNote" class="al-textarea" rows="3" placeholder="Ví dụ: Không phải nhân viên A, ảnh mờ..."></textarea>
            </div>
            <p v-else class="flag-remove-text">Bản ghi #{{ flagItem?.id }} sẽ được bỏ cờ bất thường.</p>
            <div class="al-modal-footer">
              <button type="button" @click="showFlagModal = false" class="al-btn-outline">Hủy</button>
              <button class="al-btn-submit" :class="flagItem?.is_flagged ? 'al-btn-success' : 'al-btn-danger'" @click="submitFlag" :disabled="flagging">
                <span v-if="flagging" class="al-spinner-sm"></span>
                {{ flagItem?.is_flagged ? 'Bỏ cờ' : 'Gắn cờ' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
    </Teleport>

    <!-- Toast -->
    <Teleport to="body">
    <Transition name="al-toast">
      <div v-if="toastVisible" class="al-toast" :class="'al-toast-' + toast.type">{{ toast.message }}</div>
    </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* ===== Page Header ===== */
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.section-title { font-size: 1.4rem; font-weight: 700; color: var(--text-main); }
.section-desc { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }

/* ===== Filters ===== */
.filter-bar { padding: 16px 20px; margin-bottom: 20px; }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; align-items: end; }
.filter-item label { display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 6px; }
.al-input {
  width: 100%; padding: 8px 12px; border-radius: 8px;
  border: 1px solid var(--border-color); background: var(--ocean-deepest);
  color: var(--text-main); font-family: var(--font-inter); font-size: 0.85rem;
  transition: all 0.2s; box-sizing: border-box;
}
.al-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.al-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 10px center;
}
.filter-actions { display: flex; align-items: flex-end; }
.btn-reset {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border-color);
  background: var(--card-bg); color: var(--text-muted); font-size: 0.82rem;
  font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: var(--font-inter);
  white-space: nowrap;
}
.btn-reset:hover { border-color: var(--primary); color: var(--primary); background: var(--hover-bg); }

/* ===== Table ===== */
.table-wrapper { overflow-x: auto; }
.al-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.al-table th {
  text-align: left; padding: 14px 14px; font-weight: 700; font-size: 0.72rem;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--text-muted); border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest);
  white-space: nowrap;
}
.al-table td { padding: 12px 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.al-row { transition: background 0.15s; }
.al-row:hover { background: var(--hover-bg); }
.row-flagged { background: #fff5f5 !important; }
.row-flagged:hover { background: #fee2e2 !important; }
.id-cell { color: var(--text-light); font-weight: 700; font-size: 0.8rem; }
.empty-cell { text-align: center; padding: 40px !important; color: var(--text-light); }

.user-info-cell { display: flex; align-items: center; gap: 10px; }
.user-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); flex-shrink: 0; }
.avatar-circle {
  width: 36px; height: 36px; border-radius: 50%; background: #eef2ff; color: #4f46e5;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
}
.user-name { font-weight: 600; color: var(--text-main); font-size: 0.85rem; }
.user-role { font-size: 0.7rem; color: var(--text-muted); }

.shift-badge {
  display: inline-flex; padding: 3px 8px; border-radius: 6px;
  font-size: 0.75rem; font-weight: 600; background: var(--ocean-deepest); color: var(--text-muted);
  border: 1px solid var(--border-color);
}
.location-cell { color: var(--primary); font-weight: 500; font-size: 0.82rem; }
.date-cell { color: var(--text-muted); font-size: 0.82rem; white-space: nowrap; }

.time-in { color: #2e7d32; font-weight: 600; font-size: 0.82rem; }
.time-out { color: #c62828; font-weight: 600; font-size: 0.82rem; }
.pending-badge {
  display: inline-flex; padding: 2px 8px; border-radius: 6px;
  font-size: 0.72rem; font-weight: 600; background: #fff3e0; color: #e65100;
}

.distance-badge { font-size: 0.8rem; font-weight: 600; }
.dist-ok { color: #2e7d32; }
.dist-far { color: #c62828; }
.text-muted { color: var(--text-light); font-size: 0.82rem; }

.selfie-group { display: flex; gap: 4px; }
.selfie-thumb {
  width: 36px; height: 36px; border-radius: 8px; object-fit: cover;
  cursor: pointer; border: 2px solid var(--border-color); transition: all 0.2s;
}
.selfie-thumb:hover { border-color: var(--primary); transform: scale(1.08); }
.selfie-checkout { border-color: #ffcdd2; }

.al-status {
  display: inline-flex; align-items: center; padding: 4px 10px;
  border-radius: 20px; font-size: 0.72rem; font-weight: 700;
}
.st-working { background: #e8f5e9; color: #2e7d32; }
.st-done { background: #e3f2fd; color: #1565c0; }
.st-late { background: #fff3e0; color: #e65100; }
.st-missing { background: #f5f5f5; color: #757575; }
.flag-icon { margin-left: 4px; font-size: 0.75rem; }

.status-th, .actions-th { text-align: center !important; }
.status-cell, .actions-cell { text-align: center; }

.btn-flag {
  width: 34px; height: 34px; border-radius: 8px;
  display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.2s;
  background: #fff3e0; color: #e65100; border: 1.5px solid #ffe0b2;
}
.btn-flag:hover { background: #ffe0b2; color: #bf360c; }
.btn-flag.flagged { background: #ffebee; color: #c62828; border-color: #ffcdd2; }

/* ===== Pagination ===== */
.al-pagination {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px 16px; border-top: 1px solid var(--border-color);
}
.page-info { font-size: 0.8rem; color: var(--text-muted); }
.page-buttons { display: flex; gap: 4px; }
.page-btn {
  padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border-color);
  background: var(--card-bg); color: var(--text-main); font-size: 0.8rem;
  font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: var(--font-inter);
}
.page-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); background: var(--hover-bg); }
.page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* Spinner */
.al-spinner {
  width: 32px; height: 32px; border: 3px solid var(--border-color);
  border-top-color: var(--primary); border-radius: 50%; animation: alSpin 0.7s linear infinite;
  margin: 0 auto;
}
.al-spinner-sm {
  width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff; border-radius: 50%; animation: alSpin 0.6s linear infinite; display: inline-block;
}
@keyframes alSpin { to { transform: rotate(360deg); } }
</style>

<!-- Non-scoped styles for Teleported modals/toasts -->
<style>
/* ===== AL Modal ===== */
.al-modal-overlay {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.al-modal-box {
  width: 100%; max-width: 520px; padding: 0;
  background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d9e8f0);
  border-radius: 16px; overflow: hidden;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}
.al-modal-wide { max-width: 620px; }
.al-modal-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 24px; border-bottom: 1px solid var(--border-color, #d9e8f0);
}
.al-modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main, #102a43); }
.al-modal-head-danger { background: #ffebee; }
.al-modal-head-danger h3 { color: #c62828; }
.al-btn-close {
  background: none; border: none; cursor: pointer;
  color: var(--text-muted, #627d98); display: flex; align-items: center; justify-content: center;
  padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.al-btn-close:hover { background: var(--hover-bg, #e6f4fa); color: var(--primary, var(--primary)); }

.al-modal-body { padding: 24px; }
.al-modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

/* Compare */
.compare-user-info { text-align: center; margin-bottom: 16px; }
.compare-user-info strong { font-size: 1rem; color: var(--text-main); }
.compare-user-info span { margin-left: 8px; font-size: 0.85rem; color: var(--text-muted); }
.compare-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.compare-side { text-align: center; }
.compare-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; }
.compare-img { max-width: 100%; max-height: 250px; object-fit: cover; border-radius: 12px; border: 2px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.compare-placeholder { width: 100%; height: 180px; background: var(--ocean-deepest, #f3f4f6); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 0.9rem; }
.compare-actions { text-align: center; margin-top: 20px; }

/* Flag Form */
.al-form-group { margin-bottom: 16px; }
.al-form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
.al-textarea {
  width: 100%; padding: 10px 14px; border-radius: 8px;
  border: 1px solid var(--border-color); background: var(--ocean-deepest);
  color: var(--text-main); font-family: var(--font-inter); font-size: 0.85rem;
  resize: vertical; box-sizing: border-box; transition: all 0.2s;
}
.al-textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.flag-remove-text { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 8px; }

/* Buttons */
.al-btn-outline {
  padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color, #d9e8f0);
  background: var(--card-bg); color: var(--text-main); font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; font-family: var(--font-inter);
}
.al-btn-outline:hover { border-color: var(--ocean-mid, #b3e0f2); background: var(--ocean-deepest); }
.al-btn-submit {
  padding: 10px 20px; border-radius: 8px; border: none;
  color: #fff; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;
  font-family: var(--font-inter);
}
.al-btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
.al-btn-danger { background: #ef5350; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
.al-btn-danger:hover { background: #e53935; }
.al-btn-success { background: #26a69a; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
.al-btn-success:hover { background: #00897b; }

/* Toast */
.al-toast {
  position: fixed; top: 24px; right: 24px; z-index: 2000;
  padding: 14px 22px; border-radius: 10px; color: #fff;
  font-size: 0.85rem; font-weight: 600;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
}
.al-toast-success { background: var(--seafoam, #26a69a); }
.al-toast-error { background: var(--coral, #ef5350); }

/* Transitions */
.al-modal-enter-active, .al-modal-leave-active { transition: all 0.25s ease; }
.al-modal-enter-from, .al-modal-leave-to { opacity: 0; }
.al-modal-enter-from .al-modal-box, .al-modal-leave-to .al-modal-box { transform: scale(0.95) translateY(10px); }

.al-toast-enter-active { transition: all 0.3s ease; }
.al-toast-leave-active { transition: all 0.2s ease; }
.al-toast-enter-from { opacity: 0; transform: translateX(40px); }
.al-toast-leave-to { opacity: 0; transform: translateX(40px); }
</style>
