<template>
  <div class="admin-users animate-in">
    <div class="page-header">
      <div class="header-info">
        <h1 class="page-title">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 00-3-3.87"/>
            <path d="M16 3.13a4 4 0 010 7.75"/>
          </svg>
          Quản Lý Khách Hàng
        </h1>
        <p class="page-subtitle">Quản lý tài khoản khách hàng, phân quyền, điểm thưởng và khôi phục tài khoản đã xóa.</p>
      </div>
      <button class="btn-primary" @click="openCreateModal">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"/>
          <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Thêm Khách Hàng
      </button>
    </div>

    <!-- Status Tabs Bar with Live Counts -->
    <div class="users-tabs-bar mb-3">
      <button
        class="tab-pill"
        :class="{ active: currentTab === 'all' }"
        @click="switchTab('all')"
      >
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        <span>Tất cả</span>
        <span class="tab-badge">{{ counts.all }}</span>
      </button>

      <button
        class="tab-pill"
        :class="{ active: currentTab === 'active' }"
        @click="switchTab('active')"
      >
        <span class="status-dot dot-active"></span>
        <span>Đang hoạt động</span>
        <span class="tab-badge">{{ counts.active }}</span>
      </button>

      <button
        class="tab-pill"
        :class="{ active: currentTab === 'banned' }"
        @click="switchTab('banned')"
      >
        <span class="status-dot dot-banned"></span>
        <span>Bị cấm</span>
        <span class="tab-badge">{{ counts.banned }}</span>
      </button>

      <button
        class="tab-pill"
        :class="{ active: currentTab === 'inactive' }"
        @click="switchTab('inactive')"
      >
        <span class="status-dot dot-inactive"></span>
        <span>Không hoạt động</span>
        <span class="tab-badge">{{ counts.inactive }}</span>
      </button>

      <button
        class="tab-pill tab-trash"
        :class="{ active: currentTab === 'trashed' }"
        @click="switchTab('trashed')"
      >
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
        </svg>
        <span>Thùng rác (Xóa mềm)</span>
        <span class="tab-badge badge-trash">{{ counts.trashed }}</span>
      </button>
    </div>

    <!-- Filters & Search Bar -->
    <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.1s">
      <div class="filters-left">
        <div class="search-box">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input
            v-model="searchQuery"
            @input="debouncedFetch"
            type="text"
            placeholder="Tìm theo tên, email, SĐT hoặc ID..."
            class="search-input"
          />
        </div>

        <!-- Role Filter Dropdown -->
        <select v-model="roleFilter" @change="onFilterChange" class="filter-select">
          <option value="">Tất cả vai trò</option>
          <option value="customer">Khách hàng</option>
          <option value="seller">Người bán</option>
          <option value="staff">Nhân viên</option>
          <option value="admin">Quản trị viên</option>
        </select>
      </div>

      <div class="filters-right">
        <span class="table-count">
          Tổng <strong>{{ totalUsers }}</strong> {{ currentTab === 'trashed' ? 'trong thùng rác' : 'khách hàng' }}
        </span>
      </div>
    </div>

    <!-- Floating Bulk Actions Bar -->
    <div v-if="selectedUserIds.length > 0" class="bulk-action-bar animate-in">
      <div class="bulk-action-info">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <span>Đã chọn <strong>{{ selectedUserIds.length }}</strong> khách hàng</span>
      </div>
      <div class="bulk-action-btns">
        <template v-if="currentTab === 'trashed'">
          <button class="btn-bulk-restore" @click="handleBulkRestore" :disabled="isBulkActionLoading">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="1 4 1 10 7 10"></polyline>
              <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
            </svg>
            <span>Khôi phục đã chọn ({{ selectedUserIds.length }})</span>
          </button>
          <button class="btn-bulk-force" @click="handleBulkForceDelete" :disabled="isBulkActionLoading">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="3 6 5 6 21 6"></polyline>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
            <span>Xóa vĩnh viễn đã chọn</span>
          </button>
        </template>
        <template v-else>
          <button class="btn-bulk-trash" @click="handleBulkSoftDelete" :disabled="isBulkActionLoading">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="3 6 5 6 21 6"></polyline>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
            <span>Chuyển vào thùng rác</span>
          </button>
        </template>
        <button class="btn-bulk-cancel" @click="clearSelection">Bỏ chọn</button>
      </div>
    </div>

    <!-- Table -->
    <AdminTableSkeleton v-if="loading" :columns="currentTab === 'trashed' ? 8 : 10" :rows="8" />

    <div v-else class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width: 40px; text-align: center;">
                <input
                  type="checkbox"
                  class="form-check-input select-checkbox"
                  :checked="isAllSelected"
                  @change="toggleSelectAll"
                  :disabled="users.length === 0"
                />
              </th>
              <th>ID</th>
              <th>Họ tên</th>
              <th>Email</th>
              <th>SĐT</th>
              <th>Vai trò</th>
              <th v-if="currentTab !== 'trashed'">Điểm thưởng</th>
              <th>Trạng thái</th>
              <th>{{ currentTab === 'trashed' ? 'Ngày xóa mềm' : 'Ngày tạo' }}</th>
              <th style="text-align: right;">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="users.length === 0">
              <td :colspan="currentTab === 'trashed' ? 9 : 10" class="empty-cell">
                <span class="empty-emoji">{{ currentTab === 'trashed' ? '🗑️' : '👥' }}</span>
                <h3>{{ currentTab === 'trashed' ? 'Thùng rác trống (Không có khách hàng bị xóa)' : 'Không tìm thấy khách hàng' }}</h3>
                <p v-if="currentTab === 'trashed'" class="small text-muted mt-1">Các tài khoản xóa mềm sẽ lưu trữ tại đây và có thể khôi phục bất cứ lúc nào.</p>
              </td>
            </tr>
            <tr
              v-for="user in users"
              :key="user.user_id"
              v-else
              :class="{ 'row-selected': selectedUserIds.includes(user.user_id), 'row-trashed': currentTab === 'trashed' }"
            >
              <!-- Checkbox -->
              <td style="text-align: center;">
                <input
                  type="checkbox"
                  class="form-check-input select-checkbox"
                  :value="user.user_id"
                  v-model="selectedUserIds"
                />
              </td>

              <!-- ID -->
              <td><span class="badge-id">#{{ user.user_id }}</span></td>

              <!-- User Info -->
              <td>
                <div class="user-info-cell">
                  <div class="avatar-circle" :class="{ 'avatar-trashed': currentTab === 'trashed' }">
                    {{ (user.full_name || '?')[0].toUpperCase() }}
                  </div>
                  <div>
                    <span class="prod-name">{{ user.full_name || '—' }}</span>
                    <span v-if="user.deleted_at" class="badge-trashed-tag ms-1">Đã xóa</span>
                  </div>
                </div>
              </td>

              <!-- Email -->
              <td class="email-cell">{{ user.email }}</td>

              <!-- Phone -->
              <td>{{ user.phone || '—' }}</td>

              <!-- Role -->
              <td>
                <template v-if="currentTab === 'trashed'">
                  <span class="badge-type" :class="user.role">
                    {{ user.role === 'admin' ? 'Quản trị viên' : (user.role === 'staff' ? 'Nhân viên' : (user.role === 'seller' ? 'Người bán' : 'Khách hàng')) }}
                  </span>
                </template>
                <template v-else>
                  <select :value="user.role" @change="updateRole(user.user_id, $event.target.value)" class="role-select" :class="'role-' + user.role">
                    <option value="customer">Khách hàng</option>
                    <option value="seller">Người bán</option>
                    <option value="staff">Nhân viên</option>
                    <option value="admin">Quản trị viên</option>
                  </select>
                </template>
              </td>

              <!-- Reward Points (Active View) -->
              <td v-if="currentTab !== 'trashed'">
                <strong style="color: #E63B6F; font-size: 0.9rem">{{ user.reward_points ?? 0 }}</strong>
              </td>

              <!-- Status -->
              <td>
                <template v-if="currentTab === 'trashed'">
                  <span class="badge-status" :class="user.status">
                    {{ user.status === 'active' ? 'Hoạt động' : (user.status === 'banned' ? 'Bị cấm' : 'Không hoạt động') }}
                  </span>
                </template>
                <template v-else>
                  <select :value="user.status" @change="updateStatus(user.user_id, $event.target.value)" class="status-select" :class="'status-' + user.status">
                    <option value="active">Hoạt động</option>
                    <option value="inactive">Không hoạt động</option>
                    <option value="banned">Bị cấm</option>
                  </select>
                </template>
              </td>

              <!-- Date -->
              <td style="color:var(--text-muted); font-size:0.8rem">
                {{ currentTab === 'trashed' ? formatDateTime(user.deleted_at) : formatDate(user.created_at) }}
              </td>

              <!-- Actions -->
              <td style="text-align: right;">
                <!-- TRASHED ACTIONS -->
                <div v-if="currentTab === 'trashed'" class="actions-cell justify-content-end">
                  <button class="btn-action-restore" title="Khôi phục khách hàng" @click="handleRestore(user)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="1 4 1 10 7 10"></polyline>
                      <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                    </svg>
                    <span>Khôi phục</span>
                  </button>
                  <button class="btn-action-force" title="Xóa vĩnh viễn" @click="confirmForceDelete(user)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="3 6 5 6 21 6"></polyline>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                  </button>
                </div>

                <!-- NORMAL ACTIONS -->
                <div v-else class="actions-cell justify-content-end">
                  <button class="btn-icon view" title="Xem chi tiết" @click="viewUser(user.user_id)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                  </button>
                  <button class="btn-icon edit" title="Sửa" @click="openEditModal(user)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                      <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                  </button>
                  <button class="btn-icon del" title="Chuyển vào thùng rác" @click="confirmDelete(user)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6"/>
                      <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="pagination-controls">
          <button :disabled="currentPage === 1" @click="changePage(currentPage - 1)" class="btn-page">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
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
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- ===== Modal: Tạo / Sửa User ===== -->
    <Teleport to="body">
      <div class="qv-backdrop" v-if="isFormModalOpen" @click.self="closeFormModal">
        <div class="qv-modal animate-in" style="max-width:520px">
          <div class="qv-header">
            <h2>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
              {{ isEditing ? 'Sửa Khách Hàng' : 'Thêm Khách Hàng Mới' }}
            </h2>
            <button class="qv-close" @click="closeFormModal">×</button>
          </div>
          <form @submit.prevent="handleSubmit" novalidate class="qv-body">
            <div class="qv-form-group">
              <label class="qv-form-label">Họ tên <span style="color:var(--coral)">*</span></label>
              <input v-model="form.full_name" type="text" class="qv-form-input" :class="{'is-invalid': errors.full_name}" placeholder="Nguyễn Văn A" />
              <span v-if="errors.full_name" class="field-error">{{ errors.full_name }}</span>
            </div>
            <div class="qv-meta" style="margin-bottom:14px">
              <div class="qv-form-group" style="margin-bottom:0">
                <label class="qv-form-label">Email <span style="color:var(--coral)">*</span></label>
                <input v-model="form.email" type="email" class="qv-form-input" :class="{'is-invalid': errors.email}" placeholder="email@example.com" />
                <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
              </div>
              <div class="qv-form-group" style="margin-bottom:0">
                <label class="qv-form-label">Số điện thoại</label>
                <input v-model="form.phone" type="text" class="qv-form-input" :class="{'is-invalid': errors.phone}" placeholder="0901234567" />
                <span v-if="errors.phone" class="field-error">{{ errors.phone }}</span>
              </div>
            </div>
            <div class="qv-form-group">
              <label class="qv-form-label">{{ isEditing ? 'Mật khẩu mới (bỏ trống = giữ nguyên)' : 'Mật khẩu' }} <span v-if="!isEditing" style="color:var(--coral)">*</span></label>
              <input v-model="form.password" type="password" class="qv-form-input" :class="{'is-invalid': errors.password}" placeholder="Tối thiểu 8 ký tự, chữ hoa, số, ký tự đặc biệt" />
              <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
            </div>
            <div class="qv-meta" style="margin-bottom:14px">
              <div class="qv-form-group" style="margin-bottom:0">
                <label class="qv-form-label">Vai trò</label>
                <select v-model="form.role" class="qv-form-input">
                  <option value="customer">Khách hàng</option>
                  <option value="seller">Người bán</option>
                  <option value="staff">Nhân viên</option>
                  <option value="admin">Quản trị viên</option>
                </select>
              </div>
              <div class="qv-form-group" style="margin-bottom:0">
                <label class="qv-form-label">Trạng thái</label>
                <select v-model="form.status" class="qv-form-input">
                  <option value="active">Hoạt động</option>
                  <option value="inactive">Không hoạt động</option>
                  <option value="banned">Bị cấm</option>
                </select>
              </div>
            </div>
            <!-- Inline error -->
            <div v-if="formError" class="form-error-box">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
              </svg>
              {{ formError }}
            </div>
            <div class="qv-footer">
              <button type="submit" class="btn-primary" :disabled="isSubmitting">
                {{ isSubmitting ? 'Đang lưu...' : (isEditing ? 'Cập nhật' : 'Tạo mới') }}
              </button>
              <button type="button" class="btn-outline" @click="closeFormModal">Hủy</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ===== Modal: Chi tiết User ===== -->
    <Teleport to="body">
      <div class="qv-backdrop" v-if="isDetailModalOpen" @click.self="closeDetailModal">
        <div class="qv-modal animate-in" style="max-width:620px">
          <div class="qv-header">
            <h2>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              Chi Tiết Khách Hàng
            </h2>
            <button class="qv-close" @click="closeDetailModal">×</button>
          </div>

          <div v-if="detailLoading" class="qv-loading"><div class="spinner"></div><p>Đang tải...</p></div>

          <div class="qv-body" v-if="detailData && !detailLoading">
            <!-- Profile header -->
            <div class="qv-top" style="gap:16px; margin-bottom:20px; align-items:center">
              <div class="detail-avatar-lg">{{ (detailData.full_name || '?')[0].toUpperCase() }}</div>
              <div style="flex:1">
                <h3 class="qv-name" style="font-size:1.15rem; margin:0">{{ detailData.full_name }}</h3>
                <p class="qv-slug" style="margin:2px 0 0 0">{{ detailData.email }}</p>
              </div>
              <span class="badge-status" :class="detailData.status" style="font-size:0.72rem; padding:5px 12px">
                {{ detailData.status === 'active' ? 'Hoạt động' : detailData.status === 'banned' ? 'Bị cấm' : 'Không hoạt động' }}
              </span>
            </div>

            <!-- Info grid -->
            <div class="qv-meta" style="margin-bottom:20px">
              <div class="qv-meta-item">
                <span class="qv-meta-label">ID</span>
                <span class="qv-meta-value">#{{ detailData.user_id }}</span>
              </div>
              <div class="qv-meta-item">
                <span class="qv-meta-label">Điểm thưởng</span>
                <span class="qv-meta-value" style="color: #E63B6F; font-weight: 800">{{ detailData.reward_points ?? 0 }} điểm</span>
              </div>
              <div class="qv-meta-item">
                <span class="qv-meta-label">Số điện thoại</span>
                <span class="qv-meta-value">{{ detailData.phone || '—' }}</span>
              </div>
              <div class="qv-meta-item">
                <span class="qv-meta-label">Vai trò</span>
                <span class="badge-type" :class="detailData.role">
                  {{ detailData.role === 'admin' ? 'Quản trị viên' : (detailData.role === 'staff' ? 'Nhân viên' : (detailData.role === 'seller' ? 'Người bán' : 'Khách hàng')) }}
                </span>
              </div>
              <div class="qv-meta-item">
                <span class="qv-meta-label">Ngày tạo</span>
                <span class="qv-meta-value">{{ formatDate(detailData.created_at) }}</span>
              </div>
            </div>

            <!-- Điều Chỉnh Điểm Thưởng section -->
            <div class="qv-section">
              <h4 class="qv-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                </svg>
                Điều Chỉnh Điểm Thưởng
              </h4>
              <div class="points-adjustment-box">
                <div class="adjust-inputs">
                  <div class="adj-field">
                    <label class="adj-label">Số điểm (Cộng: dương, Trừ: âm)</label>
                    <input v-model.number="adjustForm.delta" type="number" placeholder="VD: 100 hoặc -50" class="qv-form-input adj-input" />
                  </div>
                  <div class="adj-field" style="flex: 2">
                    <label class="adj-label">Lý do điều chỉnh</label>
                    <input v-model="adjustForm.description" type="text" placeholder="Nhập lý do điều chỉnh điểm..." class="qv-form-input adj-input" />
                  </div>
                </div>
                <button type="button" class="btn-primary btn-adjust animate-in" style="margin-top:10px" @click="submitPointsAdjustment" :disabled="isAdjusting">
                  {{ isAdjusting ? 'Đang lưu...' : 'Xác Nhận Thay Đổi' }}
                </button>
              </div>
            </div>

            <!-- Địa chỉ section -->
            <div class="qv-section">
              <h4 class="qv-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                  <circle cx="12" cy="10" r="3"/>
                </svg>
                Địa chỉ ({{ detailAddresses.length }})
              </h4>
              <div v-if="detailAddresses.length === 0" class="qv-empty">Chưa có địa chỉ nào</div>
              <div v-else class="addr-list">
                <div v-for="addr in detailAddresses" :key="addr.id" class="addr-card">
                  <div class="addr-top">
                    <strong>{{ addr.recipient_name }}</strong>
                    <span style="color:var(--text-muted)">{{ addr.phone }}</span>
                    <span v-if="addr.is_default" class="badge-status active" style="font-size:0.6rem; padding:2px 8px; margin-left:auto">Mặc định</span>
                  </div>
                  <div class="addr-text">{{ addr.address_line }}, {{ addr.ward }}, {{ addr.province }}</div>
                </div>
              </div>
            </div>

            <!-- Mã giảm giá section -->
            <div class="qv-section">
              <h4 class="qv-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M20 12V8H6a2 2 0 01-2-2c0-1.1.9-2 2-2h12v4"/>
                  <path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/>
                  <path d="M18 12a2 2 0 00-2 2c0 1.1.9 2 2 2h4v-4h-4z"/>
                </svg>
                Mã giảm giá đã lưu ({{ detailCoupons.length }})
              </h4>
              <div v-if="detailCoupons.length === 0" class="qv-empty">Chưa lưu mã nào</div>
              <div v-else class="qv-variants-table-wrap">
                <table class="qv-variants-table">
                  <thead>
                    <tr>
                      <th>Mã</th>
                      <th>Loại</th>
                      <th>Giá trị</th>
                      <th>Đã dùng</th>
                      <th>Ngày lưu</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="cp in detailCoupons" :key="cp.code">
                      <td><code>{{ cp.code }}</code></td>
                      <td><span class="badge-type" :class="cp.type">{{ cp.type }}</span></td>
                      <td class="qv-v-price">{{ cp.type === 'percent' ? cp.value + '%' : formatCurrency(cp.value) }}</td>
                      <td><span class="badge-stock" :class="{ good: cp.used_count === 0, low: cp.used_count > 0 }">{{ cp.used_count }}</span></td>
                      <td style="font-size:0.78rem; color:var(--text-muted)">{{ formatDate(cp.saved_at) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Footer -->
            <div class="qv-footer">
              <button class="btn-primary" @click="closeDetailModal(); openEditModal(detailData)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Chỉnh Sửa
              </button>
              <button class="btn-outline" @click="closeDetailModal">Đóng</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';
import api from '@/axios';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';

// State
const users = ref([]);
const loading = ref(true);
const totalUsers = ref(0);
const searchQuery = ref('');
let searchTimer = null;
const pagination = ref(null);
const currentPage = ref(1);

// Tabs & Filters
const currentTab = ref('all'); // 'all' | 'active' | 'banned' | 'inactive' | 'trashed'
const roleFilter = ref('');
const counts = ref({ all: 0, active: 0, inactive: 0, banned: 0, trashed: 0 });

// Bulk Selection
const selectedUserIds = ref([]);
const isBulkActionLoading = ref(false);

const isAllSelected = computed(() => {
  return users.value.length > 0 && selectedUserIds.value.length === users.value.length;
});

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedUserIds.value = [];
  } else {
    selectedUserIds.value = users.value.map(u => u.user_id);
  }
};

const clearSelection = () => {
  selectedUserIds.value = [];
};

// Form modal state
const isFormModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const isSubmitting = ref(false);
const formError = ref('');
const errors = ref({});
const form = ref({ full_name: '', email: '', phone: '', password: '', role: 'customer', status: 'active' });

// Detail modal state
const isDetailModalOpen = ref(false);
const detailLoading = ref(false);
const detailData = ref(null);
const detailAddresses = ref([]);
const detailCoupons = ref([]);

// Loyalty adjustment state
const adjustForm = ref({ delta: null, description: '' });
const isAdjusting = ref(false);

const showToast = (message, type = 'success') => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: type === 'danger' ? 'error' : type,
    title: type === 'danger' ? 'Lỗi' : (type === 'success' ? 'Thành công' : 'Thông báo'),
    text: message,
    showConfirmButton: false,
    timer: 3000
  });
};

// Fetch tab live counts
const fetchCounts = async () => {
  try {
    const res = await api.get('/admin/users/counts');
    if (res.data.status === 'success') {
      counts.value = res.data.data;
    }
  } catch (e) {
    console.error('Lỗi tải thống kê user counts:', e);
  }
};

// Fetch Users List with filters
const fetchUsers = async () => {
  try {
    loading.value = true;
    selectedUserIds.value = [];

    const params = {
      search: searchQuery.value || undefined,
      role: roleFilter.value || undefined,
      per_page: 10,
      page: currentPage.value,
    };

    if (currentTab.value === 'trashed') {
      params.trashed = 'only';
    } else if (currentTab.value !== 'all') {
      params.status = currentTab.value;
    }

    const response = await api.get('/admin/users', { params });
    users.value = response.data.data;
    totalUsers.value = response.data.total;
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
    };
  } catch (error) {
    showToast('Lỗi tải danh sách khách hàng!', 'danger');
  } finally {
    loading.value = false;
  }
};

const switchTab = (tab) => {
  currentTab.value = tab;
  currentPage.value = 1;
  selectedUserIds.value = [];
  fetchUsers();
  fetchCounts();
};

const onFilterChange = () => {
  currentPage.value = 1;
  fetchUsers();
};

const debouncedFetch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    currentPage.value = 1;
    fetchUsers();
  }, 400);
};

const changePage = (page) => {
  if (page < 1 || (pagination.value && page > pagination.value.last_page)) return;
  currentPage.value = page;
  fetchUsers();
};

// Single Delete (Soft Delete)
const confirmDelete = async (user) => {
  const result = await Swal.fire({
    title: 'Chuyển vào thùng rác?',
    html: `Bạn có chắc muốn xóa khách hàng <strong>${user.full_name}</strong>?<br/><span class="text-muted small">${user.email}</span><br/><span class="text-secondary small mt-1 d-block">Tài khoản sẽ được chuyển vào Thùng rác và có thể khôi phục sau.</span>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Đồng ý xóa',
    cancelButtonText: 'Hủy'
  });

  if (!result.isConfirmed) return;

  try {
    const res = await api.delete(`/admin/users/${user.user_id}`);
    showToast(res.data.message || 'Đã chuyển khách hàng vào thùng rác!');
    fetchUsers();
    fetchCounts();
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi khi xóa khách hàng!', 'danger');
  }
};

// Single Restore
const handleRestore = async (user) => {
  try {
    const res = await api.post(`/admin/users/${user.user_id}/restore`);
    showToast(res.data.message || 'Khôi phục tài khoản thành công!');
    fetchUsers();
    fetchCounts();
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi khi khôi phục tài khoản!', 'danger');
  }
};

// Single Force Delete
const confirmForceDelete = async (user) => {
  const result = await Swal.fire({
    title: 'XÓA VĨNH VIỄN?',
    html: `Hành động này sẽ <strong>xóa hoàn toàn</strong> tài khoản <strong>${user.full_name}</strong> khỏi cơ sở dữ liệu và <strong class="text-danger">KHÔNG THỂ KHÔI PHỤC</strong>.<br/><br/>Bạn có chắc chắn muốn thực hiện?`,
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#b91c1c',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Xác nhận xóa vĩnh viễn',
    cancelButtonText: 'Hủy'
  });

  if (!result.isConfirmed) return;

  try {
    const res = await api.delete(`/admin/users/${user.user_id}/force`);
    showToast(res.data.message || 'Đã xóa vĩnh viễn khách hàng!');
    fetchUsers();
    fetchCounts();
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi khi xóa vĩnh viễn!', 'danger');
  }
};

// Bulk Soft Delete
const handleBulkSoftDelete = async () => {
  const count = selectedUserIds.value.length;
  const result = await Swal.fire({
    title: `Xóa ${count} khách hàng?`,
    text: `Chuyển ${count} tài khoản đã chọn vào thùng rác?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Đồng ý',
    cancelButtonText: 'Hủy'
  });

  if (!result.isConfirmed) return;

  isBulkActionLoading.value = true;
  try {
    // Xóa lần lượt hoặc gọi bulk
    for (const id of selectedUserIds.value) {
      await api.delete(`/admin/users/${id}`);
    }
    showToast(`Đã chuyển ${count} khách hàng vào thùng rác!`);
    selectedUserIds.value = [];
    fetchUsers();
    fetchCounts();
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi khi thực hiện thao tác hàng loạt!', 'danger');
  } finally {
    isBulkActionLoading.value = false;
  }
};

// Bulk Restore
const handleBulkRestore = async () => {
  const count = selectedUserIds.value.length;
  isBulkActionLoading.value = true;
  try {
    const res = await api.post('/admin/users/bulk-restore', { ids: selectedUserIds.value });
    showToast(res.data.message || `Đã khôi phục ${count} khách hàng!`);
    selectedUserIds.value = [];
    fetchUsers();
    fetchCounts();
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi khi khôi phục hàng loạt!', 'danger');
  } finally {
    isBulkActionLoading.value = false;
  }
};

// Bulk Force Delete
const handleBulkForceDelete = async () => {
  const count = selectedUserIds.value.length;
  const result = await Swal.fire({
    title: `XÓA VĨNH VIỄN ${count} KHÁCH HÀNG?`,
    text: `Hành động này sẽ xóa vĩnh viễn ${count} tài khoản đã chọn khỏi DB và KHÔNG THỂ HOÀN TÁC!`,
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#b91c1c',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Xóa vĩnh viễn',
    cancelButtonText: 'Hủy'
  });

  if (!result.isConfirmed) return;

  isBulkActionLoading.value = true;
  try {
    const res = await api.post('/admin/users/bulk-force-delete', { ids: selectedUserIds.value });
    showToast(res.data.message || `Đã xóa vĩnh viễn ${count} khách hàng!`);
    selectedUserIds.value = [];
    fetchUsers();
    fetchCounts();
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi khi xóa vĩnh viễn hàng loạt!', 'danger');
  } finally {
    isBulkActionLoading.value = false;
  }
};

// Detail View
const viewUser = async (id) => {
  detailLoading.value = true;
  isDetailModalOpen.value = true;
  try {
    const res = await api.get(`/admin/users/${id}`);
    detailData.value = res.data.data;
    detailAddresses.value = res.data.addresses || [];
    detailCoupons.value = res.data.saved_coupons || [];
  } catch (e) {
    showToast('Lỗi tải chi tiết khách hàng!', 'danger');
    isDetailModalOpen.value = false;
  } finally {
    detailLoading.value = false;
  }
};

const closeDetailModal = () => { isDetailModalOpen.value = false; };

// Form Create & Edit
const openCreateModal = () => {
  isEditing.value = false; editingId.value = null; formError.value = ''; errors.value = {};
  form.value = { full_name: '', email: '', phone: '', password: '', role: 'customer', status: 'active' };
  isFormModalOpen.value = true;
};

const openEditModal = (user) => {
  isEditing.value = true; editingId.value = user.user_id; formError.value = ''; errors.value = {};
  form.value = {
    full_name: user.full_name || '',
    email: user.email || '',
    phone: user.phone || '',
    password: '',
    role: user.role || 'customer',
    status: user.status || 'active'
  };
  isFormModalOpen.value = true;
};

const closeFormModal = () => { isFormModalOpen.value = false; };

const handleSubmit = async () => {
  formError.value = '';
  errors.value = {};

  let hasError = false;
  if (!form.value.full_name.trim()) { errors.value.full_name = 'Vui lòng nhập họ tên.'; hasError = true; }
  if (!form.value.email.trim()) { errors.value.email = 'Vui lòng nhập email.'; hasError = true; }
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) { errors.value.email = 'Email không hợp lệ.'; hasError = true; }

  if (!isEditing.value && !form.value.password) { errors.value.password = 'Vui lòng nhập mật khẩu.'; hasError = true; }

  if (hasError) return;

  isSubmitting.value = true;
  try {
    const payload = { ...form.value };
    if (isEditing.value && !payload.password) delete payload.password;

    if (isEditing.value) {
      const res = await api.put(`/admin/users/${editingId.value}`, payload);
      showToast(res.data.message || 'Cập nhật thành công!');
    } else {
      const res = await api.post('/admin/users', payload);
      showToast(res.data.message || 'Thêm mới thành công!');
    }

    closeFormModal();
    fetchUsers();
    fetchCounts();
  } catch (e) {
    if (e.response?.status === 422 && e.response.data.errors) {
      errors.value = Object.fromEntries(
        Object.entries(e.response.data.errors).map(([k, v]) => [k, v[0]])
      );
    } else {
      formError.value = e.response?.data?.message || 'Có lỗi xảy ra, vui lòng thử lại!';
    }
  } finally {
    isSubmitting.value = false;
  }
};

// Update Role & Status inline
const updateRole = async (userId, newRole) => {
  try {
    const r = await api.put(`/admin/users/${userId}/role`, { role: newRole });
    showToast(r.data.message);
    fetchUsers();
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi cập nhật vai trò!', 'danger');
    fetchUsers();
  }
};

const updateStatus = async (userId, newStatus) => {
  try {
    const r = await api.put(`/admin/users/${userId}/status`, { status: newStatus });
    showToast(r.data.message);
    fetchUsers();
    fetchCounts();
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi cập nhật trạng thái!', 'danger');
    fetchUsers();
  }
};

// Points adjustment
const submitPointsAdjustment = async () => {
  if (!adjustForm.value.delta) {
    showToast('Vui lòng nhập số điểm!', 'danger');
    return;
  }
  if (!adjustForm.value.description.trim()) {
    showToast('Vui lòng nhập lý do điều chỉnh!', 'danger');
    return;
  }

  isAdjusting.value = true;
  try {
    const res = await api.post(`/admin/loyalty/users/${detailData.value.user_id}/adjust`, {
      delta: adjustForm.value.delta,
      description: adjustForm.value.description.trim(),
    });

    if (res.data.status === 'success') {
      showToast('Điều chỉnh điểm thưởng thành công!');
      detailData.value.reward_points = res.data.data.balance_after;
      adjustForm.value.delta = null;
      adjustForm.value.description = '';
      fetchUsers();
    }
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi khi điều chỉnh điểm!', 'danger');
  } finally {
    isAdjusting.value = false;
  }
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatDateTime = (dateStr) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

const formatCurrency = (val) => {
  if (!val) return '0₫';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

onMounted(() => {
  fetchUsers();
  fetchCounts();
});
</script>

<style scoped>
/* Header */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.page-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-main);
  display: flex;
  align-items: center;
  gap: 12px;
}
.page-subtitle {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin-top: 4px;
  font-weight: 500;
}

/* Status Tabs Bar */
.users-tabs-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 4px;
}
.tab-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 9999px;
  border: 1px solid var(--border-color, #e2e8f0);
  background: var(--card-bg, #ffffff);
  color: var(--text-muted, #64748b);
  font-size: 0.84rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}
.tab-pill:hover {
  background: var(--hover-bg, #f8fafc);
  color: var(--text-main, #0f172a);
  border-color: #cbd5e1;
}
.tab-pill.active {
  background: #fff0f5;
  color: #e63b6f;
  border-color: #fbcfe8;
  font-weight: 700;
  box-shadow: 0 2px 6px rgba(230, 59, 111, 0.12);
}
.tab-pill.tab-trash.active {
  background: #fef2f2;
  color: #dc2626;
  border-color: #fecaca;
  box-shadow: 0 2px 6px rgba(220, 38, 38, 0.12);
}

.tab-badge {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 9999px;
  background: #f1f5f9;
  color: #475569;
}
.tab-pill.active .tab-badge {
  background: #e63b6f;
  color: #ffffff;
}
.tab-pill.tab-trash.active .tab-badge {
  background: #dc2626;
  color: #ffffff;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}
.dot-active { background: #10b981; }
.dot-banned { background: #ef4444; }
.dot-inactive { background: #94a3b8; }

/* Filters Bar */
.filters-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  margin-bottom: 16px;
  gap: 12px;
}
.filters-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  max-width: 600px;
}
.search-box {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--ocean-deepest, #f8fafc);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 8px;
  padding: 9px 14px;
  flex: 1;
}
.search-box:focus-within {
  border-color: #e63b6f;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}
.search-input {
  background: none;
  border: none;
  outline: none;
  color: var(--text-main, #0f172a);
  font-size: 0.88rem;
  width: 100%;
}
.filter-select {
  padding: 9px 14px;
  border-radius: 8px;
  border: 1px solid var(--border-color, #e2e8f0);
  background: var(--card-bg, #ffffff);
  color: var(--text-main, #0f172a);
  font-size: 0.85rem;
  font-weight: 600;
  outline: none;
  cursor: pointer;
}
.filter-select:focus {
  border-color: #e63b6f;
}
.table-count {
  font-size: 0.85rem;
  color: var(--text-muted, #64748b);
  font-weight: 500;
}
.table-count strong {
  color: var(--text-main, #0f172a);
  font-weight: 800;
}

/* Floating Bulk Action Bar */
.bulk-action-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  background: #1e293b;
  color: #ffffff;
  border-radius: 12px;
  margin-bottom: 16px;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
  animation: slideDown 0.25s ease;
}
.bulk-action-info {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.88rem;
}
.bulk-action-btns {
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-bulk-restore {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 8px;
  border: none;
  background: #10b981;
  color: #ffffff;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-bulk-restore:hover { background: #059669; }

.btn-bulk-force {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 8px;
  border: none;
  background: #dc2626;
  color: #ffffff;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-bulk-force:hover { background: #b91c1c; }

.btn-bulk-trash {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 8px;
  border: none;
  background: #e11d48;
  color: #ffffff;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-bulk-trash:hover { background: #be123c; }

.btn-bulk-cancel {
  padding: 7px 14px;
  border-radius: 8px;
  border: 1px solid #475569;
  background: transparent;
  color: #cbd5e1;
  font-size: 0.82rem;
  cursor: pointer;
}
.btn-bulk-cancel:hover { background: #334155; color: #ffffff; }

/* Table */
.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th {
  padding: 12px 14px;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--text-muted, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.8px;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  background: var(--ocean-deepest, #f8fafc);
  white-space: nowrap;
}
.data-table td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  transition: background 0.15s;
  vertical-align: middle;
}
.data-table tbody tr:hover td {
  background: var(--hover-bg, #f8fafc);
}
.row-selected td {
  background: #fff0f5 !important;
}
.row-trashed td {
  opacity: 0.95;
}

.select-checkbox {
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.badge-id {
  padding: 3px 6px;
  border-radius: 5px;
  font-size: 0.75rem;
  font-weight: 700;
  background: rgba(230, 59, 111, 0.1);
  color: var(--primary, #e63b6f);
}
.user-info-cell { display: flex; align-items: center; gap: 8px; }
.avatar-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #e63b6f 0%, #ff6b8b 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.8rem;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(230, 59, 111, 0.2);
}
.avatar-trashed {
  background: #94a3b8 !important;
  box-shadow: none !important;
}

.prod-name { font-size: 0.86rem; font-weight: 700; color: var(--text-main, #0f172a); white-space: nowrap; }
.email-cell { color: #1d4ed8; font-size: 0.82rem; }

.badge-trashed-tag {
  font-size: 0.65rem;
  font-weight: 700;
  color: #dc2626;
  background: #fef2f2;
  border: 1px solid #fecaca;
  padding: 1px 6px;
  border-radius: 4px;
}

.role-select, .status-select {
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  font-size: 0.76rem;
  font-weight: 600;
  cursor: pointer;
  background: var(--card-bg, #ffffff);
  outline: none;
}
.role-customer { color: #2e7d32; border-color: #c8e6c9; background: #e8f5e9; }
.role-seller { color: #ef6c00; border-color: #ffe0b2; background: #fff3e0; }
.role-staff { color: #7b1fa2; border-color: #e1bee7; background: #f3e5f5; }
.role-admin { color: #d82f65; border-color: #fbcfe8; background: #fff0f5; }

.status-active { color: #2e7d32; border-color: #c8e6c9; background: #e8f5e9; }
.status-inactive { color: #757575; border-color: #e0e0e0; background: #f5f5f5; }
.status-banned { color: #d32f2f; border-color: #ffcdd2; background: #ffebee; }

.badge-status {
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 0.72rem;
  font-weight: 700;
}
.badge-status.active { background: #e8f5e9; color: #2e7d32; }
.badge-status.inactive { background: #f5f5f5; color: #757575; }
.badge-status.banned { background: #ffebee; color: #d32f2f; }

/* Action buttons */
.actions-cell { display: flex; gap: 6px; align-items: center; }
.btn-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid var(--border-color, #e2e8f0);
  background: var(--ocean-deepest, #f8fafc);
  color: var(--text-muted, #64748b);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.btn-icon:hover { border-color: currentColor; background: var(--card-bg, #ffffff); }
.edit:hover { color: #0284c7; border-color: #0284c7; background: rgba(2, 132, 199, 0.08); }
.del:hover { color: #e11d48; border-color: #e11d48; background: rgba(225, 29, 72, 0.08); }
.view:hover { color: #7c3aed; border-color: #7c3aed; background: rgba(124, 58, 237, 0.08); }

.btn-action-restore {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 6px;
  border: 1px solid #a7f3d0;
  background: #ecfdf5;
  color: #059669;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-action-restore:hover {
  background: #10b981;
  color: #ffffff;
  border-color: #10b981;
}

.btn-action-force {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #dc2626;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}
.btn-action-force:hover {
  background: #dc2626;
  color: #ffffff;
  border-color: #dc2626;
}

/* Modal and other styles */
.btn-primary {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 22px;
  border-radius: 8px;
  border: none;
  background: linear-gradient(135deg, #e63b6f 0%, #ff6b8b 100%);
  color: white;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2);
}
.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 14px rgba(230, 59, 111, 0.3);
}

.btn-outline {
  padding: 10px 22px;
  border-radius: 8px;
  border: 1px solid var(--border-color, #e2e8f0);
  background: var(--card-bg, #ffffff);
  color: var(--text-muted, #64748b);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-outline:hover {
  border-color: #e63b6f;
  color: #e63b6f;
}

.pagination-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-top: 1px solid var(--border-color, #e2e8f0);
}
.btn-page {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--border-color, #e2e8f0);
  background: var(--card-bg, #ffffff);
  color: var(--text-main, #333);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-page:hover:not(:disabled) {
  background: var(--hover-bg, #f4f6f8);
  border-color: #e63b6f;
  color: #e63b6f;
}
.btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
.page-numbers { display: flex; gap: 6px; }
.btn-page-number {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid var(--border-color, #e2e8f0);
  background: var(--card-bg, #ffffff);
  color: var(--text-main, #333);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-page-number:hover:not(.active) { background: var(--hover-bg, #f4f6f8); }
.btn-page-number.active {
  background: #e63b6f;
  color: white;
  border-color: #e63b6f;
}

/* Modal Quick View */
.qv-backdrop {
  position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
  background: rgba(0,0,0,0.55); display: flex; align-items: center; justify-content: center;
  z-index: 1000; backdrop-filter: blur(2px);
}
.qv-modal {
  background: var(--card-bg, #ffffff); border-radius: 16px; width: 94%; max-width: 900px;
  max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.qv-header {
  padding: 18px 24px; border-bottom: 1px solid var(--border-color, #e2e8f0);
  display: flex; justify-content: space-between; align-items: center;
  position: sticky; top: 0; background: var(--card-bg, #ffffff); z-index: 10; border-radius: 16px 16px 0 0;
}
.qv-header h2 {
  font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main, #0f172a);
  display: flex; align-items: center; gap: 10px;
}
.qv-close {
  background: none; border: none; font-size: 1.6rem; line-height: 1;
  color: var(--text-muted, #64748b); cursor: pointer; transition: 0.2s; padding: 0; width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center; border-radius: 8px;
}
.qv-close:hover { color: #dc2626; background: rgba(239,83,80,0.08); }
.qv-loading { padding: 60px 20px; text-align: center; color: var(--text-muted); }
.qv-body { padding: 24px; }

.form-error-box {
  display: flex; align-items: center; gap: 8px; padding: 10px 14px; margin-bottom: 14px;
  background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
  color: #dc2626; font-size: 0.82rem; font-weight: 600;
}
.qv-top { display: flex; gap: 28px; margin-bottom: 24px; }
.qv-name { font-size: 1.35rem; font-weight: 800; color: var(--text-main); line-height: 1.35; margin: 0; }
.qv-slug { font-size: 0.8rem; color: var(--text-light, #94a3b8); margin: -4px 0 0 0; }

.qv-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.qv-meta-item { display: flex; flex-direction: column; gap: 3px; padding: 8px 12px; background: var(--ocean-deepest, #f8fafc); border-radius: 8px; }
.qv-meta-label { font-size: 0.7rem; font-weight: 700; color: var(--text-light, #94a3b8); text-transform: uppercase; letter-spacing: 0.5px; }
.qv-meta-value { font-size: 0.85rem; font-weight: 600; color: var(--text-main, #0f172a); }

.qv-section { margin-bottom: 20px; }
.qv-section-title {
  font-size: 0.9rem; font-weight: 800; color: var(--text-main, #0f172a);
  padding-bottom: 10px; border-bottom: 1px solid var(--border-color, #e2e8f0); margin-bottom: 12px;
  display: flex; align-items: center; gap: 8px;
}
.qv-empty { text-align: center; padding: 20px; color: var(--text-muted); font-size: 0.85rem; background: var(--ocean-deepest, #f8fafc); border-radius: 8px; }
.qv-footer { display: flex; gap: 12px; justify-content: flex-end; padding-top: 16px; border-top: 1px solid var(--border-color, #e2e8f0); }

.qv-variants-table-wrap { overflow-x: auto; }
.qv-variants-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
.qv-variants-table th { padding: 10px 12px; text-align: left; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest, #f8fafc); }
.qv-variants-table td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.qv-variants-table code { font-size: 0.78rem; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
.qv-v-price { font-weight: 700; color: #059669; }

.badge-type { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
.badge-type.customer { background: rgba(38,166,154,0.15); color: #167a70; }
.badge-type.seller { background: rgba(255,167,38,0.15); color: #e65100; }
.badge-type.admin { background: rgba(230, 59, 111, 0.15); color: #e63b6f; }
.badge-type.staff { background: rgba(156,39,176,0.15); color: #7b1fa2; }
.badge-type.percent { background: rgba(239,83,80,0.15); color: #c62828; }
.badge-type.fixed { background: rgba(38,166,154,0.15); color: #167a70; }
.badge-type.free_ship { background: rgba(3,169,244,0.15); color: #0284c7; }

.badge-stock { padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; }
.badge-stock.good { background: rgba(38,166,154,0.15); color: #167a70; }
.badge-stock.low { background: rgba(255,167,38,0.15); color: #e65100; }

.detail-avatar-lg {
  width: 52px; height: 52px; border-radius: 50%; background: #e63b6f; color: #fff;
  display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; flex-shrink: 0;
}

.addr-list { display: flex; flex-direction: column; gap: 6px; }
.addr-card { padding: 10px 14px; border-radius: 8px; background: var(--ocean-deepest, #f8fafc); border: 1px solid var(--border-color, #e2e8f0); font-size: 0.82rem; }
.addr-top { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.addr-text { color: var(--text-muted); font-size: 0.78rem; }

.qv-form-group { margin-bottom: 14px; }
.qv-form-label { display: block; font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.3px; }
.qv-form-input {
  width: 100%; padding: 10px 14px; border-radius: 8px; border: 1.5px solid var(--border-color, #e2e8f0);
  font-size: 0.85rem; color: var(--text-main); box-sizing: border-box;
  outline: none; transition: all 0.2s; background: var(--ocean-deepest, #f8fafc);
}
.qv-form-input:focus { border-color: #e63b6f; box-shadow: 0 0 0 3px rgba(230, 59, 111,0.08); background: #ffffff; }
.qv-form-input.is-invalid { border-color: #ef4444; background: #fef2f2; }
.field-error { display: block; color: #ef4444; font-size: 0.72rem; font-weight: 600; margin-top: 6px; }

.empty-cell { text-align: center; padding: 60px 20px !important; }
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 12px; }
.empty-cell h3 { font-size: 1rem; color: #64748b; margin: 0; }

.points-adjustment-box {
  background: var(--ocean-deepest, #f8fafc);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 10px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.adjust-inputs { display: flex; gap: 12px; }
.adj-field { display: flex; flex-direction: column; gap: 4px; flex: 1; }
.adj-label { font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
.adj-input { background: #ffffff; }
.btn-adjust { align-self: flex-end; padding: 8px 18px; }

.animate-in { animation: fadeSlideUp 0.35s ease both; }
@keyframes fadeSlideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

@media (max-width: 768px) {
  .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
  .filters-bar { flex-direction: column; gap: 12px; align-items: stretch; }
  .filters-left { max-width: 100%; flex-direction: column; }
  .qv-meta { grid-template-columns: 1fr; }
  .bulk-action-bar { flex-direction: column; gap: 10px; align-items: stretch; }
  .bulk-action-btns { justify-content: flex-end; }
}
</style>
