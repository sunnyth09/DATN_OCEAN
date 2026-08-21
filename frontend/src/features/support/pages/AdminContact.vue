<template>
  <div class="admin-contact">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Liên hệ</h1>
        <p class="page-subtitle">Xem và phản hồi các yêu cầu hỗ trợ, câu hỏi từ khách hàng</p>
      </div>
      <div class="header-badge">
        <span>{{ pagination.total }} liên hệ</span>
      </div>
    </div>

    <!-- Filters Bar -->
    <div class="filters-bar">
      <!-- Search -->
      <div class="search-wrap">
        <AppIcon name="search" size="16" class="search-icon" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Tìm kiếm theo tên, email hoặc tiêu đề..."
          class="search-input"
          @input="debouncedFetch"
        />
      </div>

      <!-- Filter status tabs -->
      <div class="filter-tabs">
        <button
          v-for="tab in [{ v: '', l: 'Tất cả' }, { v: 'pending', l: 'Chờ xử lý' }, { v: 'replied', l: 'Đã phản hồi' }]"
          :key="tab.v"
          class="tab-btn"
          :class="{ 'tab-btn--active': statusFilter === tab.v }"
          @click="filterByStatus(tab.v)"
        >
          {{ tab.l }}
        </button>
      </div>
    </div>

    <!-- Table -->
    <AdminTableSkeleton v-if="isLoading" :columns="6" :rows="5" />

    <div v-else class="table-wrap">
      <table class="review-table">
        <thead>
          <tr>
            <th>Người gửi</th>
            <th>Chủ đề</th>
            <th>Nội dung</th>
            <th>Ngày gửi</th>
            <th>Trạng thái</th>
            <th class="text-center">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="contacts.length === 0">
            <td colspan="6" class="empty-cell">
              <div class="empty-state">
                <p>Không có yêu cầu liên hệ nào phù hợp</p>
              </div>
            </td>
          </tr>

          <tr
            v-for="c in contacts"
            :key="c.id"
            class="review-row"
          >
            <!-- Người gửi -->
            <td>
              <div class="user-cell">
                <img
                  :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(c.name || 'Khách')}&background=e63b6f&color=fff&size=40`"
                  class="user-avatar"
                  alt="avatar"
                />
                <div>
                  <div class="user-name">{{ c.name }}</div>
                  <div class="user-email">{{ c.email }}</div>
                </div>
              </div>
            </td>

            <!-- Tiêu đề / Chủ đề -->
            <td>
              <span class="subject-badge">{{ c.subject }}</span>
            </td>

            <!-- Nội dung -->
            <td>
              <div class="review-content" :title="c.message">
                {{ c.message }}
              </div>
            </td>

            <!-- Ngày gửi -->
            <td class="date-cell">
              {{ formatDate(c.created_at) }}
            </td>

            <!-- Trạng thái -->
            <td>
              <span class="status-badge" :class="c.status === 'pending' ? 'badge--pending' : 'badge--approved'">
                {{ c.status === 'pending' ? 'Chờ xử lý' : 'Đã phản hồi' }}
              </span>
            </td>

            <!-- Thao tác -->
            <td>
              <div class="action-btns" style="align-items: center; justify-content: center;">
                <button
                  class="btn-action"
                  :class="c.status === 'pending' ? 'btn-primary-action' : 'btn-view'"
                  :title="c.status === 'pending' ? 'Phản hồi khách hàng' : 'Xem chi tiết'"
                  @click="openReplyModal(c)"
                >
                  {{ c.status === 'pending' ? 'Phản hồi' : 'Xem' }}
                </button>
                <button
                  class="btn-action btn-danger"
                  title="Xóa liên hệ"
                  @click="openDeleteConfirm(c)"
                >
                  Xóa
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button
        class="page-btn"
        :disabled="pagination.current_page <= 1"
        @click="changePage(pagination.current_page - 1)"
      >
        ← Trước
      </button>
      <span class="page-info">Trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
      <button
        class="page-btn"
        :disabled="pagination.current_page >= pagination.last_page"
        @click="changePage(pagination.current_page + 1)"
      >
        Tiếp →
      </button>
    </div>

    <!-- Reply / Detail Modal -->
    <Transition name="modal">
      <div v-if="showReplyModal && replyingContact" class="modal-overlay" @click.self="showReplyModal = false">
        <div class="modal-box">
          <div class="modal-header">
            <h3>Chi tiết & Phản hồi Liên hệ #{{ replyingContact.id }}</h3>
            <button class="btn-close-modal" @click="showReplyModal = false">&times;</button>
          </div>

          <div class="modal-body">
            <div class="info-grid">
              <div class="info-group">
                <label>Người gửi:</label>
                <p>{{ replyingContact.name }}</p>
              </div>
              <div class="info-group">
                <label>Email:</label>
                <p>{{ replyingContact.email }}</p>
              </div>
              <div class="info-group">
                <label>Chủ đề:</label>
                <p>{{ replyingContact.subject }}</p>
              </div>
              <div class="info-group">
                <label>Ngày gửi:</label>
                <p>{{ formatDate(replyingContact.created_at) }}</p>
              </div>
              <div class="info-group" style="grid-column: span 2;">
                <label>Trạng thái hiện tại:</label>
                <p>
                  <span class="status-badge" :class="replyingContact.status === 'pending' ? 'badge--pending' : 'badge--approved'">
                    {{ replyingContact.status === 'pending' ? 'Chờ xử lý' : 'Đã phản hồi' }}
                  </span>
                </p>
              </div>
            </div>

            <!-- Nội dung liên hệ -->
            <div class="ticket-content">
              <h4>Nội dung tin nhắn:</h4>
              <div class="description-box">
                {{ replyingContact.message }}
              </div>
            </div>

            <!-- Form phản hồi qua Email -->
            <div class="reply-section">
              <h4>Phản hồi khách hàng (Gửi qua Email)</h4>
              <textarea
                v-model="replyContent"
                placeholder="Nhập nội dung phản hồi chi tiết cho khách hàng..."
                class="reply-input"
                rows="4"
              ></textarea>
              <div v-if="replyError" class="reply-error-msg">{{ replyError }}</div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showReplyModal = false">Đóng</button>
            <button
              class="btn btn-primary"
              :disabled="isReplying"
              @click="submitReply"
            >
              <span v-if="isReplying" class="spinner-small"></span>
              <span v-else>{{ replyingContact.status === 'pending' ? 'Gửi phản hồi Email' : 'Gửi lại Email' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import AppIcon from '@/components/AppIcon.vue';
import api from '@/axios';
import Swal from 'sweetalert2';
import { useUiStore } from '@/stores/ui';

const uiStore = useUiStore();

const contacts = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const statusFilter = ref('');
const isReplying = ref(false);
const showReplyModal = ref(false);
const replyingContact = ref(null);
const replyContent = ref('');
const replyError = ref('');
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

let searchTimer = null;

const showToast = (message, type = 'success') => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    title: type === 'success' ? 'Thành công' : (type === 'error' || type === 'danger' ? 'Lỗi' : 'Thông báo'),
    text: message,
    icon: type === 'danger' ? 'error' : type,
    showConfirmButton: false,
    timer: 3000,
  });
};

const debouncedFetch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => fetchContacts(1), 400);
};

const filterByStatus = (status) => {
  statusFilter.value = status;
  fetchContacts(1);
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    fetchContacts(page);
  }
};

const fetchContacts = async (page = 1) => {
  isLoading.value = true;
  try {
    const res = await api.get('/admin/contacts', {
      params: {
        page,
        search: searchQuery.value || undefined,
        status: statusFilter.value || undefined,
      },
    });
    if (res.data.status === 'success') {
      contacts.value = res.data.data || [];
      pagination.value = {
        current_page: res.data.current_page || 1,
        last_page: res.data.last_page || 1,
        total: res.data.total || contacts.value.length,
      };
    }
  } catch (error) {
    console.error('Lỗi tải danh sách liên hệ:', error);
    showToast('Lỗi tải danh sách liên hệ!', 'error');
  } finally {
    isLoading.value = false;
  }
};

const openReplyModal = (contact) => {
  replyingContact.value = contact;
  replyContent.value = '';
  replyError.value = '';
  showReplyModal.value = true;
};

const submitReply = async () => {
  if (!replyContent.value.trim()) {
    replyError.value = 'Vui lòng nhập nội dung phản hồi.';
    return;
  }
  replyError.value = '';
  isReplying.value = true;
  try {
    await api.post(`/admin/contacts/${replyingContact.value.id}/reply`, {
      reply: replyContent.value,
    });
    showReplyModal.value = false;
    showToast('Đã gửi phản hồi email thành công!', 'success');
    window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
    window.dispatchEvent(new Event('admin-notification-received'));
    fetchContacts(pagination.value.current_page);
    refreshPendingCount();
  } catch (error) {
    replyError.value = error.response?.data?.message || 'Gửi phản hồi thất bại.';
  } finally {
    isReplying.value = false;
  }
};

const openDeleteConfirm = async (contact) => {
  const result = await Swal.fire({
    title: 'Xác nhận xóa',
    text: `Bạn có chắc muốn xóa yêu cầu liên hệ từ "${contact.name}"? Hành động này không thể hoàn tác!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Đồng ý xóa',
    cancelButtonText: 'Hủy',
  });

  if (!result.isConfirmed) return;

  try {
    await api.delete(`/admin/contacts/${contact.id}`);
    showToast('Đã xóa liên hệ thành công!', 'success');
    window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
    window.dispatchEvent(new Event('admin-notification-received'));
    fetchContacts(pagination.value.current_page);
    refreshPendingCount();
  } catch (error) {
    showToast(error.response?.data?.message || 'Xóa thất bại.', 'error');
  }
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleString('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
};

const refreshPendingCount = async () => {
  try {
    const res = await api.get('/admin/contacts/pending-count');
    if (res.data.status === 'success') {
      uiStore.setPendingContactCount(res.data.count || 0);
    }
  } catch (e) {
    // Silently fail
  }
};

onMounted(() => {
  fetchContacts();
});
</script>

<style scoped>
.admin-contact {
  padding: 24px 20px;
  min-height: calc(100vh - 70px);
  box-sizing: border-box;
  max-width: 100%;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
}
.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 4px;
  color: var(--text-main);
}
.page-subtitle {
  color: #6c757d;
  font-size: 0.92rem;
  margin: 0;
}
.header-badge {
  background: var(--primary);
  color: #fff;
  padding: 6px 18px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.88rem;
  white-space: nowrap;
}

/* Filters Bar */
.filters-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  background: var(--card-bg);
  border: 1px solid #e9ecef;
  border-radius: 12px;
  padding: 14px 18px;
  margin-bottom: 20px;
}

.search-wrap {
  position: relative;
  flex: 1 1 220px;
}
.search-icon {
  position: absolute;
  top: 50%;
  left: 12px;
  transform: translateY(-50%);
  color: #aaa;
}
.search-input {
  width: 100%;
  padding: 8px 12px 8px 36px;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  font-size: 0.9rem;
  outline: none;
  transition: border 0.2s;
  box-sizing: border-box;
  background: transparent;
  color: var(--text-main);
}
.search-input:focus {
  border-color: var(--primary);
}

.filter-tabs {
  display: flex;
  gap: 6px;
}
.tab-btn {
  padding: 7px 14px;
  border-radius: 8px;
  border: 1px solid #dee2e6;
  background: var(--card-bg);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  color: #495057;
}
.tab-btn:hover {
  background: #fdf2f8;
  border-color: var(--primary);
  color: var(--primary);
}
.tab-btn--active {
  background: var(--primary) !important;
  color: #fff !important;
  border-color: var(--primary) !important;
}

/* Table Wrapper */
.table-wrap {
  background: var(--card-bg);
  border-radius: 12px;
  border: 1px solid #e9ecef;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.table-wrap::-webkit-scrollbar {
  height: 8px;
}
.table-wrap::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}
.table-wrap::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.table-wrap::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.review-table {
  width: 100%;
  min-width: 900px;
  border-collapse: collapse;
  font-size: 0.88rem;
}
.review-table thead tr {
  background: var(--surface-container);
}
.review-table th {
  padding: 13px 16px;
  text-align: left;
  font-weight: 600;
  color: #374151;
  font-size: 0.82rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 1px solid #e9ecef;
  white-space: nowrap;
}
.review-row {
  border-bottom: 1px solid #f0f2f5;
  transition: background 0.15s;
  background: #ffffff;
}
.review-row:hover {
  background: #fafbff;
}
.review-table td {
  padding: 14px 16px;
  vertical-align: middle;
  color: #374151;
}

/* User cell */
.user-cell {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 160px;
}
.user-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid #e2e8f0;
}
.user-name {
  font-weight: 600;
  font-size: 0.88rem;
  color: var(--text-main);
}
.user-email {
  font-size: 0.78rem;
  color: var(--text-muted, #64748b);
}

/* Subject badge */
.subject-badge {
  display: inline-block;
  font-weight: 600;
  font-size: 0.85rem;
  color: #1e293b;
  max-width: 220px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Content preview */
.review-content {
  margin: 0;
  max-width: 260px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 0.85rem;
  color: #475569;
  line-height: 1.45;
}

.date-cell {
  font-size: 0.8rem;
  color: var(--text-muted, #64748b);
  white-space: nowrap;
}

/* Status badge */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.76rem;
  font-weight: 600;
  white-space: nowrap;
}
.badge--approved {
  background: #d1fae5;
  color: #065f46;
}
.badge--pending {
  background: #fef3c7;
  color: #92400e;
}

/* Action buttons */
.action-btns {
  display: flex;
  flex-direction: column;
  gap: 5px;
  align-items: stretch;
  width: 72px;
}
.btn-action {
  width: 72px;
  padding: 5px 0;
  border-radius: 6px;
  border: none;
  font-size: 0.78rem;
  font-weight: 500;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.1s;
  white-space: nowrap;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  box-sizing: border-box;
}
.btn-action:active {
  transform: scale(0.96);
}
.btn-primary-action {
  background: #2563eb;
  color: #fff;
  border: none;
}
.btn-primary-action:hover {
  background: #1d4ed8;
}
.btn-view {
  background: #2563eb;
  color: #fff;
  border: none;
}
.btn-view:hover {
  background: #1d4ed8;
}
.btn-danger {
  background: #dc2626;
  color: #fff;
  border: none;
}
.btn-danger:hover {
  background: #b91c1c;
}

/* Empty state */
.empty-cell {
  padding: 0 !important;
}
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 60px;
  color: var(--text-light, #94a3b8);
  font-size: 0.95rem;
}

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 24px;
}
.page-btn {
  padding: 8px 20px;
  border-radius: 8px;
  border: 1px solid #dee2e6;
  background: var(--card-bg);
  font-size: 0.88rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}
.page-btn:hover:not(:disabled) {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
}
.page-btn:disabled {
  opacity: 0.4;
  cursor: default;
}
.page-info {
  font-size: 0.88rem;
  color: #6c757d;
}

/* Modal styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}
.modal-box {
  background: white;
  width: 100%;
  max-width: 620px;
  border-radius: 16px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}
.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-header h3 {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 700;
  color: #102a43;
}
.btn-close-modal {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: #94a3b8;
  cursor: pointer;
}
.modal-body {
  padding: 24px;
  overflow-y: auto;
}
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}
.info-group label {
  display: block;
  font-size: 0.82rem;
  color: #64748b;
  margin-bottom: 4px;
}
.info-group p {
  margin: 0;
  font-weight: 600;
  color: #1e293b;
  font-size: 0.9rem;
}

.ticket-content {
  background: #f8fafc;
  padding: 16px 20px;
  border-radius: 12px;
  margin-bottom: 20px;
  border: 1px solid #e2e8f0;
}
.ticket-content h4 {
  margin: 0 0 8px;
  font-size: 0.95rem;
  color: #0369a1;
  font-weight: 700;
}
.description-box {
  color: #334155;
  line-height: 1.6;
  white-space: pre-wrap;
  font-size: 0.9rem;
}

.reply-section h4 {
  margin: 0 0 10px;
  font-size: 0.95rem;
  font-weight: 700;
  color: #102a43;
}
.reply-input {
  width: 100%;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-family: inherit;
  font-size: 0.9rem;
  resize: vertical;
  box-sizing: border-box;
}
.reply-input:focus {
  border-color: var(--primary);
  outline: none;
}
.reply-error-msg {
  color: #dc2626;
  font-size: 0.82rem;
  margin-top: 6px;
  font-weight: 500;
}

.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.btn {
  padding: 8px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  font-size: 0.88rem;
}
.btn-secondary {
  background: #f1f5f9;
  color: #475569;
}
.btn-secondary:hover {
  background: #e2e8f0;
}
.btn-primary {
  background: var(--primary);
  color: white;
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-primary:hover:not(:disabled) {
  background: #d82f65;
}
.btn-primary:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.spinner-small {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Transitions */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
