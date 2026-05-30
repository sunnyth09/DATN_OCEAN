<template>
  <div class="attendance-list-container p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 mb-0 fw-bold text-gray-800">Quản lý lịch sử Chấm Công</h2>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body p-3">
        <div class="row g-3 align-items-end">
          <div class="col-md-2">
            <label class="form-label small fw-medium">Từ ngày</label>
            <input type="date" class="form-control form-control-sm" v-model="filters.from_date" @change="fetchAttendances(1)" />
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-medium">Đến ngày</label>
            <input type="date" class="form-control form-control-sm" v-model="filters.to_date" @change="fetchAttendances(1)" />
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-medium">Trạng thái</label>
            <select class="form-select form-select-sm" v-model="filters.status" @change="fetchAttendances(1)">
              <option value="">Tất cả</option>
              <option value="checked_in">Đang làm</option>
              <option value="checked_out">Hoàn tất</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-medium">Gắn cờ</label>
            <select class="form-select form-select-sm" v-model="filters.is_flagged" @change="fetchAttendances(1)">
              <option value="">Tất cả</option>
              <option value="1">Có cờ</option>
              <option value="0">Bình thường</option>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-sm btn-outline-secondary w-100" @click="resetFilters">
              <i class="bi bi-arrow-counterclockwise me-1"></i> Xóa lọc
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">ID</th>
                <th>Nhân sự</th>
                <th>Ca</th>
                <th>Vị trí</th>
                <th>Ngày</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>K/cách</th>
                <th>Ảnh</th>
                <th>Trạng thái</th>
                <th class="text-end pe-3">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="11" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td>
              </tr>
              <tr v-else-if="attendances.length === 0">
                <td colspan="11" class="text-center py-4 text-muted">Chưa có dữ liệu.</td>
              </tr>
              <tr v-else v-for="item in attendances" :key="item.id" :class="{ 'flagged-row': item.is_flagged }">
                <td class="ps-3 fw-medium text-muted">#{{ item.id }}</td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <img v-if="item.user_avatar" :src="getFullUrl(item.user_avatar)" class="rounded-circle" width="32" height="32" style="object-fit: cover;" />
                    <div v-else class="avatar-placeholder">{{ (item.user_name || '?')[0] }}</div>
                    <div>
                      <div class="fw-bold small">{{ item.user_name || 'N/A' }}</div>
                      <span class="text-muted" style="font-size: 0.7rem;">{{ item.user_role }}</span>
                    </div>
                  </div>
                </td>
                <td><span class="badge text-bg-light fw-medium small">{{ item.shift_name || 'N/A' }}</span></td>
                <td><span class="text-primary small">{{ item.location_name || 'N/A' }}</span></td>
                <td class="small">{{ formatDate(item.work_date) }}</td>
                <td><span class="text-success fw-medium small">{{ formatTime(item.check_in_at) }}</span></td>
                <td>
                  <span v-if="item.check_out_at" class="text-danger fw-medium small">{{ formatTime(item.check_out_at) }}</span>
                  <span v-else class="badge bg-warning text-dark" style="font-size: 0.7rem;">Chưa</span>
                </td>
                <td>
                  <span v-if="item.check_in_distance_meters != null" class="small"
                        :class="item.check_in_distance_meters > 200 ? 'text-danger' : 'text-success'">
                    {{ Math.round(item.check_in_distance_meters) }}m
                  </span>
                  <span v-else class="text-muted small">-</span>
                </td>
                <td>
                  <div class="d-flex gap-1">
                    <img v-if="item.image_path" :src="getFullUrl(item.image_path)" class="selfie-thumb" @click="openCompare(item)" title="Xem ảnh check-in" />
                    <img v-if="item.check_out_image_path" :src="getFullUrl(item.check_out_image_path)" class="selfie-thumb selfie-checkout" @click="openCompare(item, 'checkout')" title="Xem ảnh check-out" />
                    <span v-if="!item.image_path && !item.check_out_image_path" class="text-muted small">-</span>
                  </div>
                </td>
                <td>
                  <span class="badge" :class="getStatusBadge(item.status)">{{ getStatusLabel(item.status) }}</span>
                  <span v-if="item.is_flagged" class="badge text-bg-danger ms-1" title="Bất thường">🚩</span>
                </td>
                <td class="text-end pe-3">
                  <button class="btn btn-sm btn-outline-warning" @click="openFlagModal(item)" :title="item.is_flagged ? 'Bỏ cờ' : 'Gắn cờ'">
                    <i class="bi" :class="item.is_flagged ? 'bi-flag-fill' : 'bi-flag'"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center" v-if="pagination.last_page > 1">
        <span class="text-muted small">{{ pagination.from }} - {{ pagination.to }} / {{ pagination.total }}</span>
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item" :class="{ disabled: !pagination.prev_page_url }">
            <button class="page-link shadow-none" @click="fetchAttendances(pagination.current_page - 1)">Trước</button>
          </li>
          <li class="page-item" v-for="p in visiblePages" :key="p" :class="{ active: pagination.current_page === p }">
            <button class="page-link shadow-none" @click="fetchAttendances(p)">{{ p }}</button>
          </li>
          <li class="page-item" :class="{ disabled: !pagination.next_page_url }">
            <button class="page-link shadow-none" @click="fetchAttendances(pagination.current_page + 1)">Sau</button>
          </li>
        </ul>
      </div>
    </div>

    <!-- ===== MODAL: So sánh ảnh ===== -->
    <div v-if="showCompareModal" class="modal-overlay" @click.self="showCompareModal = false">
      <div class="compare-modal">
        <div class="modal-header-custom">
          <h5 class="fw-bold mb-0">So sánh Ảnh Nhân viên</h5>
          <button class="btn-close" @click="showCompareModal = false"></button>
        </div>
        <div class="modal-body-custom">
          <div class="text-center mb-3">
            <strong>{{ compareItem?.user_name }}</strong>
            <span class="text-muted ms-2">{{ compareItem?.user_role }}</span>
          </div>
          <div class="row g-3">
            <div class="col-6 text-center">
              <p class="small fw-medium text-muted mb-2">Ảnh hồ sơ</p>
              <img v-if="compareItem?.user_avatar" :src="getFullUrl(compareItem.user_avatar)" class="compare-img rounded shadow-sm" />
              <div v-else class="compare-placeholder">Chưa có ảnh</div>
            </div>
            <div class="col-6 text-center">
              <p class="small fw-medium text-muted mb-2">{{ compareType === 'checkout' ? 'Ảnh check-out' : 'Ảnh check-in' }}</p>
              <img v-if="compareSelfie" :src="getFullUrl(compareSelfie)" class="compare-img rounded shadow-sm" />
              <div v-else class="compare-placeholder">Không có ảnh</div>
            </div>
          </div>
          <div class="mt-3 text-center">
            <button v-if="!compareItem?.is_flagged" class="btn btn-sm btn-outline-danger" @click="flagFromCompare(true)">
              <i class="bi bi-flag me-1"></i> Gắn cờ bất thường
            </button>
            <button v-else class="btn btn-sm btn-outline-success" @click="flagFromCompare(false)">
              <i class="bi bi-check-lg me-1"></i> Bỏ cờ
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== MODAL: Flag ===== -->
    <div v-if="showFlagModal" class="modal-overlay" @click.self="showFlagModal = false">
      <div class="modal-box-sm">
        <div class="modal-header-custom">
          <h5 class="fw-bold mb-0">{{ flagItem?.is_flagged ? 'Bỏ cờ bất thường' : 'Gắn cờ bất thường' }}</h5>
          <button class="btn-close" @click="showFlagModal = false"></button>
        </div>
        <div class="modal-body-custom">
          <div v-if="!flagItem?.is_flagged" class="mb-3">
            <label class="form-label fw-medium">Lý do gắn cờ</label>
            <textarea v-model="flagNote" class="form-control" rows="3" placeholder="Ví dụ: Không phải nhân viên A, ảnh mờ..."></textarea>
          </div>
          <p v-else class="text-muted">Bản ghi #{{ flagItem?.id }} sẽ được bỏ cờ bất thường.</p>
          <button class="btn w-100 fw-bold" :class="flagItem?.is_flagged ? 'btn-success' : 'btn-danger'" @click="submitFlag" :disabled="flagging">
            <span v-if="flagging" class="spinner-border spinner-border-sm me-2"></span>
            {{ flagItem?.is_flagged ? 'Bỏ cờ' : 'Gắn cờ' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <Transition name="al-toast">
      <div v-if="toastVisible" class="al-toast" :class="'al-toast-' + toast.type">{{ toast.message }}</div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../axios';

const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');
const getFullUrl = (path) => {
    if (!path) return '';
    return path.startsWith('http') ? path : BASE_URL + path;
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
const getStatusBadge = (s) => ({ 'checked_in': 'text-bg-success', 'checked_out': 'text-bg-info', 'late': 'text-bg-warning', 'missing_checkout': 'text-bg-secondary' }[s] || 'text-bg-secondary');

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

<style scoped>
.attendance-list-container { max-width: 1500px; margin: 0 auto; }

.flagged-row { background: #fef2f2 !important; }
.flagged-row:hover { background: #fee2e2 !important; }

.avatar-placeholder { width: 32px; height: 32px; border-radius: 50%; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.8rem; flex-shrink: 0; }

.selfie-thumb { width: 36px; height: 36px; border-radius: 6px; object-fit: cover; cursor: pointer; border: 2px solid #e5e7eb; transition: all 0.2s; }
.selfie-thumb:hover { border-color: #3b82f6; transform: scale(1.1); }
.selfie-checkout { border-color: #fca5a5; }

/* Compare Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 9998; backdrop-filter: blur(3px); }
.compare-modal { background: white; border-radius: 16px; width: 100%; max-width: 600px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
.modal-box-sm { background: white; border-radius: 16px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
.modal-header-custom { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #eee; }
.modal-body-custom { padding: 24px; }

.compare-img { max-width: 100%; max-height: 250px; object-fit: cover; border: 2px solid #e5e7eb; }
.compare-placeholder { width: 100%; height: 180px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 0.9rem; }

.al-toast { position: fixed; bottom: 30px; right: 30px; padding: 14px 28px; border-radius: 12px; color: white; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.2); z-index: 9999; }
.al-toast-success { background: linear-gradient(135deg, #10b981, #059669); }
.al-toast-error { background: linear-gradient(135deg, #ef4444, #dc2626); }
.al-toast-enter-active { transition: all 0.3s ease; }
.al-toast-leave-active { transition: all 0.2s ease; }
.al-toast-enter-from, .al-toast-leave-to { opacity: 0; transform: translateX(40px); }
</style>
