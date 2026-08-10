<template>
  <div class="admin-layout">
    <div class="admin-header">
      <h2>Lịch sử đổi quà (User Rewards)</h2>
    </div>

    <div class="admin-content mt-4">
      <AdminTableSkeleton v-if="isLoading" :columns="7" :rows="5" />
      <div v-else class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Khách hàng</th>
              <th>Quà tặng</th>
              <th>Điểm đã tiêu</th>
              <th>Trạng thái</th>
              <th>Ngày đổi</th>
              <th>Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ur in userRewards" :key="ur.id">
              <td>#{{ ur.id }}</td>
              <td>
                <div v-if="ur.user">
                  <strong>{{ ur.user.full_name }}</strong><br>
                  <small class="text-muted">{{ ur.user.email }}</small>
                </div>
                <span v-else class="text-muted">N/A</span>
              </td>
              <td>
                <div v-if="ur.reward">
                  <strong>{{ ur.reward.name }}</strong>
                  <span class="badge ms-1" :class="ur.reward.type === 'voucher' ? 'bg-success' : 'bg-info'">
                    {{ ur.reward.type === 'voucher' ? 'Voucher' : 'Quà vật lý' }}
                  </span>
                </div>
                <span v-else class="text-muted">N/A</span>
              </td>
              <td><strong class="text-danger">{{ ur.points_spent }} đ</strong></td>
              <td>
                <span class="badge" :class="statusClass(ur.status)">
                  {{ statusText(ur.status) }}
                </span>
              </td>
              <td>{{ new Date(ur.created_at).toLocaleString('vi-VN') }}</td>
              <td>
                <div class="dropdown" v-if="ur.status === 'pending'">
                  <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Đổi trạng thái
                  </button>
                  <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" @click.prevent="updateStatus(ur.id, 'completed')">Đã hoàn thành (Gửi quà)</a></li>
                    <li><a class="dropdown-item text-danger" href="#" @click.prevent="updateStatus(ur.id, 'cancelled')">Hủy</a></li>
                  </ul>
                </div>
              </td>
            </tr>
            <tr v-if="userRewards.length === 0">
              <td colspan="7" class="text-center text-muted py-4">Chưa có lịch sử đổi quà nào.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import api from '@/axios';
import Swal from 'sweetalert2';

const userRewards = ref([]);
const isLoading = ref(true);

const fetchUserRewards = async () => {
  isLoading.value = true;
  try {
    const res = await api.get('/admin/user-rewards');
    userRewards.value = res.data.data;
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const updateStatus = async (id, status) => {
  try {
    await api.put(`/admin/user-rewards/${id}/status`, { status });
    Swal.fire({ toast: true, position: 'top-end', title: 'Thành công', text: 'Cập nhật trạng thái thành công', icon: 'success', showConfirmButton: false, timer: 3000 });
    fetchUserRewards();
  } catch (error) {
    Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: 'Có lỗi xảy ra', icon: 'error', showConfirmButton: false, timer: 3000 });
  }
};

const statusClass = (status) => {
  switch (status) {
    case 'pending': return 'bg-warning text-dark';
    case 'completed': return 'bg-success';
    case 'cancelled': return 'bg-danger';
    default: return 'bg-secondary';
  }
};

const statusText = (status) => {
  switch (status) {
    case 'pending': return 'Chờ xử lý';
    case 'completed': return 'Đã hoàn thành';
    case 'cancelled': return 'Đã hủy';
    default: return status;
  }
};

onMounted(() => {
  fetchUserRewards();
});
</script>

<style scoped>
.admin-header {
  margin-bottom: 20px;
}
</style>
