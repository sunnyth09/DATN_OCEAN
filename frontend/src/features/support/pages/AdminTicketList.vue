<template>
  <div class="admin-tickets animate-in">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">Quản lý Khiếu nại</h2>
        <p class="page-desc">Theo dõi và xử lý khiếu nại từ khách hàng</p>
      </div>
      <div class="header-right">
        <span class="ticket-count">{{ pagination ? pagination.total : 0 }} khiếu nại</span>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <div class="search-box">
        <AppIcon name="search" size="18" class="search-icon" />
        <input 
          type="text" 
          v-model="searchQuery" 
          placeholder="Tìm kiếm theo mã đơn, tên khách, lý do..."
          @keyup.enter="fetchTickets"
        >
      </div>
      
      <div class="filter-actions">
        <select v-model="statusFilter" @change="fetchTickets" class="filter-select">
          <option value="all">Tất cả trạng thái</option>
          <option value="pending">Chờ xử lý</option>
          <option value="processing">Đang xử lý</option>
          <option value="resolved">Đã giải quyết</option>
          <option value="closed">Đã đóng</option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <div class="table-container">
      <table class="table" v-if="!loading && tickets.length > 0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Khách hàng</th>
            <th>Mã đơn</th>
            <th>Lý do</th>
            <th>Ngày gửi</th>
            <th>Trạng thái</th>
            <th class="text-right">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ticket in tickets" :key="ticket.ticket_id">
            <td>#{{ ticket.ticket_id }}</td>
            <td>
              <div class="user-info">
                <span class="user-name">{{ ticket.user?.full_name || 'Khách' }}</span>
                <span class="user-email">{{ ticket.user?.email }}</span>
              </div>
            </td>
            <td>
              <span v-if="ticket.order" class="order-code">#{{ ticket.order.order_code }}</span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <div class="reason-text" :title="ticket.reason">{{ ticket.reason }}</div>
            </td>
            <td>{{ formatDate(ticket.created_at) }}</td>
            <td>
              <span class="status-badge" :class="ticket.status">
                {{ getStatusText(ticket.status) }}
              </span>
            </td>
            <td class="text-right">
              <button class="btn-icon" @click="openModal(ticket)" title="Xem chi tiết">
                <AppIcon name="eye" size="18" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page > 1" class="pagination-controls">
          <button :disabled="currentPage === 1" @click="changePage(currentPage - 1)" class="btn-page">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
              Trước
          </button>
          <div class="page-numbers">
              <button 
                  v-for="page in pagination.last_page" 
                  :key="page" 
                  @click="changePage(page)" 
                  class="btn-page-number" 
                  :class="{'active': currentPage === page}"
              >
                  {{ page }}
              </button>
          </div>
          <button :disabled="currentPage === pagination.last_page" @click="changePage(currentPage + 1)" class="btn-page">
              Sau
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
          </button>
      </div>
      
      <!-- Empty State -->
      <div v-if="!loading && tickets.length === 0" class="empty-state">
        <AppIcon name="inbox" size="48" class="empty-icon" />
        <p>Không có khiếu nại nào phù hợp</p>
      </div>
      
      <!-- Loading State -->
      <AdminTableSkeleton v-if="loading" :columns="6" :rows="5" />
    </div>

    <!-- Detail/Reply Modal -->
    <Transition name="modal">
      <div v-if="showModal && selectedTicket" class="modal-overlay" @click.self="closeModal">
        <div class="modal-box">
          <div class="modal-header">
            <h3>Chi tiết Khiếu nại #{{ selectedTicket.ticket_id }}</h3>
            <button class="btn-close" @click="closeModal">&times;</button>
          </div>
          
          <div class="modal-body">
            <div class="info-grid">
              <div class="info-group">
                <label>Khách hàng:</label>
                <p>{{ selectedTicket.user?.full_name }} ({{ selectedTicket.user?.email }})</p>
              </div>
              <div class="info-group">
                <label>Đơn hàng:</label>
                <p>
                  <span v-if="selectedTicket.order" class="order-code">#{{ selectedTicket.order.order_code }}</span>
                  <span v-else>Không xác định</span>
                </p>
              </div>
              <div class="info-group">
                <label>Ngày gửi:</label>
                <p>{{ formatDate(selectedTicket.created_at) }}</p>
              </div>
              <div class="info-group">
                <label>Trạng thái hiện tại:</label>
                <p>
                   <span class="status-badge" :class="selectedTicket.status">
                    {{ getStatusText(selectedTicket.status) }}
                  </span>
                </p>
              </div>
            </div>

            <div class="ticket-content">
              <h4>Lý do: {{ selectedTicket.reason }}</h4>
              <div class="description-box" v-html="sanitizeHtml(selectedTicket.description || '(Không có nội dung mô tả)')"></div>
              <div class="image-box" v-if="selectedTicket.image_url">
                 <p><strong>Ảnh minh chứng:</strong></p>
                 <img :src="getImageUrl(selectedTicket.image_url)" alt="Minh chứng" class="evidence-img" @click="openImage(selectedTicket.image_url)">
              </div>
            </div>

            <div class="reply-section">
              <h4>Phản hồi từ Admin</h4>
              <textarea 
                v-model="replyContent" 
                placeholder="Nhập nội dung phản hồi cho khách hàng..." 
                class="reply-input"
                rows="4"
                :disabled="isTicketClosed"
              ></textarea>
              
              <div class="status-update mt-3">
                <label>Cập nhật trạng thái:</label>
                <select v-model="updateStatus" class="filter-select w-100" :disabled="isTicketClosed">
                  <option v-for="opt in availableStatusOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
                <p v-if="isTicketClosed" class="ticket-closed-hint">
                  🔒 Khiếu nại này đã đóng và được lưu trữ, không thể chỉnh sửa thêm.
                </p>
              </div>
            </div>
          </div>
          
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeModal">Đóng</button>
            <button class="btn btn-primary" @click="submitReply" :disabled="actionLoading || isTicketClosed">
              <span v-if="actionLoading" class="spinner-small"></span>
              <span v-else>Cập nhật & Phản hồi</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import Swal from 'sweetalert2';
import api from '@/axios';
import AppIcon from '@/components/AppIcon.vue';
import { getStorageUrl } from '@/utils/url';
import { sanitizeHtml } from '@/utils/sanitize';

const tickets = ref([]);
const loading = ref(false);
const actionLoading = ref(false);
const searchQuery = ref('');
const statusFilter = ref('all');
const currentPage = ref(1);
const pagination = ref(null);

const showModal = ref(false);
const selectedTicket = ref(null);
const replyContent = ref('');
const updateStatus = ref('pending');

const availableStatusOptions = computed(() => {
  const current = selectedTicket.value?.status;
  if (!current) return [];
  if (current === 'closed') {
    return [{ value: 'closed', label: 'Đã đóng' }];
  }
  if (current === 'resolved') {
    return [
      { value: 'resolved', label: 'Đã giải quyết' },
      { value: 'closed', label: 'Đã đóng' }
    ];
  }
  if (current === 'processing') {
    return [
      { value: 'processing', label: 'Đang xử lý' },
      { value: 'resolved', label: 'Đã giải quyết' },
      { value: 'closed', label: 'Đã đóng' }
    ];
  }
  return [
    { value: 'pending', label: 'Chờ xử lý' },
    { value: 'processing', label: 'Đang xử lý' },
    { value: 'resolved', label: 'Đã giải quyết' },
    { value: 'closed', label: 'Đã đóng' }
  ];
});

const isTicketClosed = computed(() => selectedTicket.value?.status === 'closed');

const fetchTickets = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/tickets', {
      params: {
        search: searchQuery.value,
        status: statusFilter.value,
        page: currentPage.value,
        per_page: 5
      }
    });
    if (res.data.status === 'success') {
      tickets.value = res.data.data.data || [];
      pagination.value = {
        current_page: res.data.data.current_page,
        last_page: res.data.data.last_page,
        total: res.data.data.total
      };
    }
  } catch (error) {
    console.error("Lỗi lấy danh sách khiếu nại:", error);
    Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: 'Không thể lấy danh sách khiếu nại', icon: 'error', showConfirmButton: false, timer: 3000 });
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => {
  if (page < 1 || (pagination.value && page > pagination.value.last_page)) return;
  currentPage.value = page;
  fetchTickets();
};

const getStatusText = (status) => {
  const map = {
    'pending': 'Chờ xử lý',
    'processing': 'Đang xử lý',
    'resolved': 'Đã giải quyết',
    'closed': 'Đã đóng'
  };
  return map[status] || status;
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

const getImageUrl = (path) => {
  if (!path) return '';
  if (path.startsWith('http')) return path;
  return getStorageUrl(path);
};

const openModal = (ticket) => {
  selectedTicket.value = ticket;
  replyContent.value = ticket.admin_reply || '';
  updateStatus.value = ticket.status;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  selectedTicket.value = null;
};

const submitReply = async () => {
  if (!selectedTicket.value || isTicketClosed.value) return;

  const originalStatus = selectedTicket.value.status;
  const originalReply = (selectedTicket.value.admin_reply || '').trim();
  const currentReply = (replyContent.value || '').trim();

  if (updateStatus.value === originalStatus && currentReply === originalReply) {
    Swal.fire({ toast: true, position: 'top-end', title: 'Thông báo', text: 'Không có thay đổi nào cần cập nhật.', icon: 'info', showConfirmButton: false, timer: 2000 });
    return;
  }

  actionLoading.value = true;
  
  try {
    const res = await api.put(`/admin/tickets/${selectedTicket.value.ticket_id}`, {
      status: updateStatus.value,
      admin_reply: currentReply
    });
    
    if (res.data.status === 'success') {
      Swal.fire({
        title: 'Thành công',
        text: res.data.message || 'Đã cập nhật khiếu nại',
        icon: 'success',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
      });
      window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
      window.dispatchEvent(new Event('admin-notification-received'));
      closeModal();
      fetchTickets();
    }
  } catch (error) {
    console.error("Lỗi cập nhật:", error);
    Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: error.response?.data?.message || 'Có lỗi xảy ra khi cập nhật', icon: 'error', showConfirmButton: false, timer: 3000 });
  } finally {
    actionLoading.value = false;
  }
};

const openImage = (path) => {
  window.open(getImageUrl(path), '_blank');
};

onMounted(() => {
  fetchTickets();
});
</script>

<style scoped>
.admin-tickets {
  padding: 24px;
  background: var(--bg-main, #f8fafc);
  min-height: 100vh;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-main, #0f172a);
  margin: 0 0 4px;
}

.page-desc {
  color: var(--text-muted, #64748b);
  margin: 0;
  font-size: 0.95rem;
}

.ticket-count {
  background: var(--ocean-blue, #1d4ed8);
  color: white;
  padding: 6px 16px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.9rem;
}

/* Filters */
.filter-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--card-bg);
  padding: 16px;
  border-radius: 12px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  gap: 16px;
  flex-wrap: wrap;
}

.search-box {
  position: relative;
  flex: 1;
  min-width: 250px;
  max-width: 400px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-light);
}

.search-box input {
  width: 100%;
  padding: 10px 10px 10px 40px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  outline: none;
  transition: all 0.2s;
}

.search-box input:focus {
  border-color: #1d4ed8;
  box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.1);
}

.filter-select {
  padding: 10px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  outline: none;
  background: var(--card-bg);
  min-width: 160px;
}

/* Table */
.table-container {
  background: var(--card-bg);
  border-radius: 12px;
  padding: 0;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  overflow-x: auto;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table th {
  background: var(--surface-container);
  padding: 16px;
  text-align: left;
  font-weight: 600;
  color: #475569;
  font-size: 0.9rem;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}

.table td {
  padding: 16px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.95rem;
}

/* Pagination Controls */
.pagination-controls {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 24px; border-top: 1px solid var(--border-color, #e2e8f0);
}
.btn-page {
    display: flex; align-items: center; gap: 6px; padding: 8px 14px;
    border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0);
    background: var(--card-bg, #fff); color: var(--text-main, #333);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
}
.btn-page:hover:not(:disabled) { background: var(--hover-bg, #f4f6f8); border-color: var(--ocean-blue, #1d4ed8); color: var(--ocean-blue, #1d4ed8); }
.btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
.page-numbers { display: flex; gap: 6px; }
.btn-page-number {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0);
    background: var(--card-bg, #fff); color: var(--text-main, #333);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.btn-page-number:hover:not(.active) { background: var(--hover-bg, #f4f6f8); }
.btn-page-number.active {
    background: var(--ocean-blue, #1d4ed8); color: white; border-color: var(--ocean-blue, #1d4ed8);
}

.user-info { display: flex; flex-direction: column; }
.user-name { font-weight: 600; color: var(--text-main); }
.user-email { font-size: 0.85rem; color: var(--text-muted); }

.order-code {
  background: #e0f2fe;
  color: #0284c7;
  padding: 4px 8px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.85rem;
}

.reason-text {
  max-width: 250px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 500;
  color: #334155;
}

.status-badge {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  display: inline-block;
}
.status-badge.pending { background: #fef3c7; color: #d97706; }
.status-badge.processing { background: #e0f2fe; color: #0284c7; }
.status-badge.resolved { background: #dcfce3; color: #16a34a; }
.status-badge.closed { background: var(--surface-container); color: var(--text-muted); }

.text-right { text-align: right !important; }
.text-muted { color: var(--text-light); }

.btn-icon {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 8px;
  border-radius: 6px;
  transition: all 0.2s;
}
.btn-icon:hover {
  background: var(--surface-container);
  color: #1d4ed8;
}

/* Empty & Loading */
.empty-state, .loading-state {
  padding: 60px 20px;
  text-align: center;
  color: var(--text-muted);
}
.empty-icon { opacity: 0.5; margin-bottom: 16px; }

.spinner {
  width: 40px; height: 40px;
  border: 3px solid #f1f5f9;
  border-top-color: #1d4ed8;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 16px;
}
@keyframes spin { 100% { transform: rotate(360deg); } }
.spinner-small {
  display: inline-block; width: 14px; height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%; border-top-color: #fff;
  animation: spin 1s ease infinite;
}

/* Modal */
.modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}
.modal-box {
  background: var(--card-bg);
  width: 100%; max-width: 650px;
  border-radius: 16px;
  box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
  display: flex; flex-direction: column;
  max-height: 90vh;
}
.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid #e2e8f0;
  display: flex; justify-content: space-between; align-items: center;
}
.modal-header h3 { margin: 0; font-size: 1.25rem; font-weight: 700; }
.btn-close {
  background: none; border: none; font-size: 1.5rem; color: var(--text-light); cursor: pointer;
}
.modal-body {
  padding: 24px;
  overflow-y: auto;
}
.info-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;
}
.info-group label { display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; }
.info-group p { margin: 0; font-weight: 600; color: var(--text-main); }

.ticket-content {
  background: var(--surface-container); padding: 20px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #e2e8f0;
}
.ticket-content h4 { margin: 0 0 12px; font-size: 1.1rem; color: #dc2626; }
.description-box { color: #334155; line-height: 1.6; word-break: break-word; }
.description-box p { margin: 0 0 8px 0; }
.description-box p:last-child { margin-bottom: 0; }

.image-box { margin-top: 16px; }
.evidence-img { max-width: 100%; max-height: 200px; border-radius: 8px; cursor: zoom-in; border: 1px solid #e2e8f0; }

.reply-section h4 { margin: 0 0 12px; font-size: 1.05rem; }
.reply-input {
  width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;
  font-family: inherit; font-size: 0.95rem; resize: vertical; box-sizing: border-box;
}
.reply-input:focus { border-color: #1d4ed8; outline: none; }
.reply-input:disabled { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }
.ticket-closed-hint {
  margin-top: 8px;
  font-size: 0.82rem;
  color: #64748b;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
  background: #f8fafc;
  padding: 8px 12px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}

.modal-footer {
  padding: 16px 24px; border-top: 1px solid #e2e8f0;
  display: flex; justify-content: flex-end; gap: 12px;
}
.btn {
  padding: 8px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s;
}
.btn-secondary { background: var(--surface-container); color: #475569; }
.btn-secondary:hover { background: #e2e8f0; }
.btn-primary { background: #1d4ed8; color: white; display: flex; align-items: center; gap: 8px; }
.btn-primary:hover:not(:disabled) { background: #1e40af; }
.btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }

.mt-3 { margin-top: 16px; }
.w-100 { width: 100%; box-sizing: border-box; }

/* Transitions */
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
