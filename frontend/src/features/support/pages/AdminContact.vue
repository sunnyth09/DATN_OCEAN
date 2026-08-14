<template>
  <div class="admin-contact animate-in">
    <div class="page-header">
      <div class="header-left">
        <h2 class="section-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
          Quản lý Liên hệ
        </h2>
        <p class="section-desc">Xem và phản hồi yêu cầu hỗ trợ từ khách hàng.</p>
      </div>
    </div>

    <!-- Search & Filter -->
    <div class="search-bar ocean-card">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input v-model="searchQuery" @input="debouncedFetch" type="text" placeholder="Tìm kiếm theo tên, email hoặc tiêu đề..." class="search-input" />
      <div class="filter-tabs">
        <button :class="['filter-tab', { active: statusFilter === '' }]" @click="filterByStatus('')">Tất cả</button>
        <button :class="['filter-tab', { active: statusFilter === 'pending' }]" @click="filterByStatus('pending')">Chờ xử lý</button>
        <button :class="['filter-tab', { active: statusFilter === 'replied' }]" @click="filterByStatus('replied')">Đã phản hồi</button>
      </div>
      <span class="contact-count">{{ contacts.length }} liên hệ</span>
    </div>

    <!-- Table -->
    <AdminTableSkeleton v-if="isLoading" :columns="5" :rows="5" />
    <div v-else class="ocean-card table-wrapper">
      <table class="contact-table">
        <thead>
          <tr>
            <th>Người gửi</th>
            <th>Tiêu đề</th>
            <th>Nội dung</th>
            <th>Ngày gửi</th>
            <th class="actions-th">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="contacts.length === 0">
            <td colspan="5" class="empty-cell">Chưa có liên hệ nào.</td>
          </tr>
          <tr v-for="c in contacts" :key="c.id" class="contact-row">
            <td>
              <div class="sender-info">
                <span class="sender-name">{{ c.name }}</span>
                <span class="sender-email">{{ c.email }}</span>
                <span :class="['status-badge', c.status]">{{ c.status === 'pending' ? 'Chờ xử lý' : 'Đã phản hồi' }}</span>
              </div>
            </td>
            <td><span class="subject-text">{{ c.subject }}</span></td>
            <td><span class="message-preview">{{ c.message }}</span></td>
            <td class="date-cell">{{ formatDate(c.created_at) }}</td>
            <td class="actions-cell">
              <button v-if="c.status === 'pending'" class="btn-action reply" title="Phản hồi" @click="openReplyModal(c)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </button>
              <button class="btn-action delete" title="Xóa" @click="openDeleteConfirm(c)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Reply Modal -->
    <Teleport to="body">
    <Transition name="contact-modal">
      <div v-if="showReplyModal" class="contact-modal-overlay" @click.self="showReplyModal = false">
        <div class="contact-modal-box">
          <div class="contact-modal-head">
            <h3>Phản hồi khách hàng</h3>
            <button class="contact-btn-close" @click="showReplyModal = false">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="contact-modal-body">
            <div class="reply-info">
              <p class="reply-label">Người nhận:</p>
              <p class="reply-value">{{ replyingContact?.name }} ({{ replyingContact?.email }})</p>
            </div>
            <div class="reply-info">
              <p class="reply-label">Tiêu đề gốc:</p>
              <p class="reply-value">{{ replyingContact?.subject }}</p>
            </div>
            <div class="reply-info">
              <p class="reply-label">Nội dung gốc:</p>
              <p class="reply-value reply-message">{{ replyingContact?.message }}</p>
            </div>
            <div class="contact-form-group">
              <label>Nội dung phản hồi:</label>
              <textarea v-model="replyContent" rows="5" class="contact-form-control" placeholder="Nhập nội dung phản hồi..."></textarea>
            </div>
            <div v-if="replyError" class="contact-form-error">{{ replyError }}</div>
            <div class="contact-modal-footer">
              <button type="button" @click="showReplyModal = false" class="contact-btn-outline">Hủy</button>
              <button type="button" @click="submitReply" class="contact-btn-primary" :disabled="isReplying">
                <span v-if="isReplying" class="contact-spinner-sm"></span>
                {{ isReplying ? 'Đang gửi...' : 'Gửi phản hồi' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
    </Teleport>

    <!-- Delete Modal -->
    <Teleport to="body">
    <Transition name="contact-modal">
      <div v-if="showDeleteModal" class="contact-modal-overlay" @click.self="showDeleteModal = false">
        <div class="contact-modal-box" style="max-width:440px">
          <div class="contact-modal-head contact-modal-head-danger">
            <h3>
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              Xóa liên hệ?
            </h3>
            <button class="contact-btn-close" @click="showDeleteModal = false">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="contact-modal-body">
            <p>Bạn có chắc chắn muốn xóa liên hệ từ <strong>{{ deletingContact?.name }}</strong>?</p>
            <p class="contact-text-hint">Hành động này không thể hoàn tác.</p>
            <div class="contact-modal-footer">
              <button type="button" @click="showDeleteModal = false" class="contact-btn-outline">Giữ lại</button>
              <button type="button" @click="confirmDelete" class="contact-btn-danger">Đồng ý xóa</button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
    </Teleport>

    <!-- Toast -->
    <Teleport to="body">
    <Transition name="contact-toast">
      <div v-if="toastVisible" class="contact-toast" :class="'contact-toast-' + toast.type">
        {{ toast.message }}
      </div>
    </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import api from '@/axios';
import { useUiStore } from '@/stores/ui';

const uiStore = useUiStore();

const contacts = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const statusFilter = ref('');
const isReplying = ref(false);
const showReplyModal = ref(false);
const showDeleteModal = ref(false);
const replyingContact = ref(null);
const deletingContact = ref(null);
const replyContent = ref('');
const replyError = ref('');
const toastVisible = ref(false);
const toast = ref({ message: '', type: 'success' });

let searchTimer = null;
let toastTimer = null;

const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  toastVisible.value = true;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toastVisible.value = false; }, 3000);
};

const debouncedFetch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => fetchContacts(), 500);
};

const filterByStatus = (status) => {
  statusFilter.value = status;
  fetchContacts();
};

const fetchContacts = async () => {
  isLoading.value = true;
  try {
    const res = await api.get('/admin/contacts', {
      params: { search: searchQuery.value, status: statusFilter.value }
    });
    contacts.value = res.data.data;
  } catch (error) {
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
    await api.post(`/admin/contacts/${replyingContact.value.id}/reply`, { reply: replyContent.value });
    showReplyModal.value = false;
    showToast('Đã gửi phản hồi thành công!', 'success');
    window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
    window.dispatchEvent(new Event('admin-notification-received'));
    fetchContacts();
    refreshPendingCount();
  } catch (error) {
    replyError.value = error.response?.data?.message || 'Gửi phản hồi thất bại.';
  } finally {
    isReplying.value = false;
  }
};

const openDeleteConfirm = (contact) => {
  deletingContact.value = contact;
  showDeleteModal.value = true;
};

const confirmDelete = async () => {
  if (!deletingContact.value) return;
  try {
    await api.delete(`/admin/contacts/${deletingContact.value.id}`);
    showDeleteModal.value = false;
    showToast('Đã xóa liên hệ thành công!', 'success');
    window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
    window.dispatchEvent(new Event('admin-notification-received'));
    fetchContacts();
    refreshPendingCount();
  } catch (error) {
    showDeleteModal.value = false;
    showToast(error.response?.data?.message || 'Xóa thất bại.', 'error');
  }
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
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

onMounted(fetchContacts);
</script>

<style scoped>
/* ===== Page Header ===== */
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.section-title { font-size: 1.4rem; font-weight: 700; color: var(--text-main); }
.section-desc { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }

/* ===== Search ===== */
.search-bar {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; margin-bottom: 16px; flex-wrap: wrap;
}
.search-bar svg { color: var(--text-light); flex-shrink: 0; }
.search-input { flex: 1; min-width: 200px; background: transparent; border: none; outline: none; font-size: 0.9rem; color: var(--text-main); font-family: var(--font-inter); }
.search-input::placeholder { color: var(--text-light); }
.filter-tabs { display: flex; gap: 4px; margin-left: auto; }
.filter-tab {
  padding: 6px 14px; border-radius: 6px; border: 1px solid var(--border-color);
  background: var(--ocean-deepest); color: var(--text-muted);
  font-family: var(--font-inter); font-size: 0.78rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s;
}
.filter-tab:hover { border-color: var(--primary); color: var(--primary); }
.filter-tab.active { background: rgba(230, 59, 111,0.1); border-color: rgba(230, 59, 111,0.3); color: var(--primary); }
.contact-count { font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; background: var(--ocean-deepest); padding: 4px 10px; border-radius: 20px; }

/* ===== Table ===== */
.table-wrapper { overflow-x: auto; }
.contact-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.contact-table th {
  text-align: left; padding: 14px 16px; font-weight: 700; font-size: 0.72rem;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--text-muted); border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest);
}
.contact-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: top; }
.contact-row { transition: background 0.15s; }
.contact-row:hover { background: var(--hover-bg); }
.empty-cell { text-align: center; padding: 40px !important; color: var(--text-light); }

.sender-info { display: flex; flex-direction: column; gap: 2px; min-width: 180px; }
.sender-name { font-weight: 700; color: var(--text-main); font-size: 0.9rem; }
.sender-email { font-size: 0.8rem; color: var(--text-muted); }
.status-badge { display: inline-block; font-size: 0.7rem; font-weight: 600; padding: 3px 10px; border-radius: 20px; margin-top: 4px; width: fit-content; }
.status-badge.pending { background: #fff3e0; color: #e65100; }
.status-badge.replied { background: #e8f5e9; color: #2e7d32; }

.subject-text { font-weight: 700; color: var(--text-main); }
.message-preview { color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; max-width: 280px; }
.date-cell { color: var(--text-muted); font-size: 0.8rem; white-space: nowrap; }
.actions-th { text-align: center !important; }
.actions-cell { text-align: center; white-space: nowrap; }
.btn-action {
  width: 34px; height: 34px; border-radius: 8px; border: 1.5px solid var(--border-color);
  background: var(--ocean-deepest); color: var(--text-muted);
  display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.2s; margin: 0 2px;
}
.btn-action.reply:hover { background: #e3f2fd; color: #1565c0; border-color: #bbdefb; }
.btn-action.delete:hover { background: #ffebee; color: #c62828; border-color: #ffcdd2; }
</style>

<!-- Non-scoped styles for Teleported elements -->
<style>
/* ===== Contact Modal ===== */
.contact-modal-overlay {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.45); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.contact-modal-box {
  width: 100%; max-width: 520px; padding: 0;
  background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d9e8f0);
  border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}
.contact-modal-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 24px; border-bottom: 1px solid var(--border-color, #d9e8f0);
}
.contact-modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main, #102a43); }
.contact-modal-head-danger { background: #ffebee; }
.contact-modal-head-danger h3 { color: #c62828; }
.contact-btn-close {
  background: none; border: none; cursor: pointer;
  color: var(--text-muted, #627d98); display: flex; align-items: center; justify-content: center;
  padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.contact-btn-close:hover { background: var(--hover-bg, #e6f4fa); color: var(--coral, #ef5350); }
.contact-modal-body { padding: 24px; }
.contact-modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

.reply-info { margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color, #eee); }
.reply-label { font-size: 0.8rem; font-weight: 700; color: var(--text-main, #102a43); margin: 0 0 4px; }
.reply-value { font-size: 0.9rem; color: var(--text-muted, #627d98); margin: 0; }
.reply-message { white-space: pre-wrap; background: var(--ocean-deepest, #f0f7fa); padding: 10px; border-radius: 8px; }

.contact-form-group { margin-bottom: 8px; }
.contact-form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main, #102a43); margin-bottom: 8px; }
.contact-form-control {
  width: 100%; padding: 10px 14px; border-radius: 8px;
  border: 1px solid var(--border-color, #d9e8f0); background: var(--ocean-deepest, #f0f7fa);
  color: var(--text-main, #102a43); font-family: var(--font-inter, 'Inter', sans-serif);
  font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box; resize: vertical;
}
.contact-form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111,0.1); }
.contact-form-control::placeholder { color: var(--text-light, #9fb3c8); }
.contact-form-error {
  padding: 10px 14px; background: #ffebee; border: 1px solid #ffcdd2;
  border-radius: 8px; color: #c62828; font-size: 0.85rem; font-weight: 500; margin-bottom: 8px;
}
.contact-text-hint { color: var(--text-muted, #627d98); font-size: 0.85rem; margin-top: 8px; }

/* Buttons */
.contact-btn-outline {
  padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color, #d9e8f0);
  background: var(--card-bg); color: var(--text-main, #102a43); font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; font-family: var(--font-inter, 'Inter', sans-serif);
}
.contact-btn-outline:hover { border-color: var(--ocean-mid, #b3e0f2); background: var(--ocean-deepest, #f0f7fa); }
.contact-btn-primary {
  padding: 10px 20px; border-radius: 8px; border: none;
  background: var(--primary); color: #fff; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;
  font-family: var(--font-inter, 'Inter', sans-serif);
}
.contact-btn-primary:hover { background: #d82f65; }
.contact-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.contact-btn-danger {
  padding: 10px 20px; border-radius: 8px; border: none;
  background: var(--coral, #ef5350); color: #fff; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s; font-family: var(--font-inter, 'Inter', sans-serif);
}
.contact-btn-danger:hover { background: #e53935; }
.contact-spinner-sm {
  width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff; border-radius: 50%; animation: contactSpin 0.6s linear infinite; display: inline-block;
}
@keyframes contactSpin { to { transform: rotate(360deg); } }

/* Toast */
.contact-toast {
  position: fixed; top: 24px; right: 24px; z-index: 2000;
  padding: 14px 22px; border-radius: 10px; color: #fff;
  font-size: 0.85rem; font-weight: 600; box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}
.contact-toast-success { background: var(--seafoam, #26a69a); }
.contact-toast-error { background: var(--coral, #ef5350); }

/* Transitions */
.contact-modal-enter-active, .contact-modal-leave-active { transition: all 0.25s ease; }
.contact-modal-enter-from, .contact-modal-leave-to { opacity: 0; }
.contact-modal-enter-from .contact-modal-box, .contact-modal-leave-to .contact-modal-box { transform: scale(0.95) translateY(10px); }
.contact-toast-enter-active { transition: all 0.3s ease; }
.contact-toast-leave-active { transition: all 0.2s ease; }
.contact-toast-enter-from { opacity: 0; transform: translateX(40px); }
.contact-toast-leave-to { opacity: 0; transform: translateX(40px); }
</style>
