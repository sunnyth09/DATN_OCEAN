<template>
  <div class="admin-layout">
    <div class="admin-header">
      <h2>Quản lý Quà Tặng (Loyalty)</h2>
      <button class="btn btn-primary" @click="openModal()">
        <i class="fas fa-plus"></i> Thêm quà tặng
      </button>
    </div>

    <div class="admin-content mt-4">
      <AdminTableSkeleton v-if="isLoading" :columns="6" :rows="5" />
      <div v-else class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Tên quà</th>
              <th>Loại</th>
              <th>Điểm yêu cầu</th>
              <th>Ngày tạo</th>
              <th>Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="reward in rewards" :key="reward.id">
              <td>#{{ reward.id }}</td>
              <td>
                <div class="d-flex align-items-center">
                  <div class="ms-2">
                    <strong>{{ reward.name }}</strong>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge" :class="reward.type === 'voucher' ? 'bg-success' : 'bg-info'">
                  {{ reward.type === 'voucher' ? 'Voucher' : 'Quà vật lý' }}
                </span>
              </td>
              <td><strong class="text-danger">{{ reward.points_required }} đ</strong></td>
              <td>{{ new Date(reward.created_at).toLocaleDateString('vi-VN') }}</td>
              <td>
                <button class="btn btn-sm btn-outline-primary me-2" @click="openModal(reward)">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" @click="deleteReward(reward.id)">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="rewards.length === 0">
              <td colspan="6" class="text-center text-muted py-4">Chưa có quà tặng nào.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Form -->
    <div class="modal fade" id="rewardModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ isEditing ? 'Cập nhật' : 'Thêm' }} Quà Tặng</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveReward">
              <div class="mb-3">
                <label class="form-label">Tên quà tặng <span class="text-danger">*</span></label>
                <input type="text" class="form-control" v-model="form.name" required />
              </div>
              <div class="mb-3">
                <label class="form-label">Mô tả</label>
                <textarea class="form-control" v-model="form.description" rows="3"></textarea>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Loại quà <span class="text-danger">*</span></label>
                  <select class="form-select" v-model="form.type" required>
                    <option value="voucher">Voucher giảm giá</option>
                    <option value="item">Quà vật lý (Áo, Mũ...)</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Điểm yêu cầu <span class="text-danger">*</span></label>
                  <input type="number" class="form-control" v-model="form.points_required" min="1" required />
                </div>
              </div>
              <div class="modal-footer px-0 pb-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                  <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                  {{ isEditing ? 'Cập nhật' : 'Lưu' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import api from '@/axios';
import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';

const rewards = ref([]);
const isLoading = ref(true);
const isSubmitting = ref(false);
const isEditing = ref(false);
let modalInstance = null;

const defaultForm = () => ({
  id: null,
  name: '',
  description: '',
  type: 'voucher',
  points_required: 100,
  image: ''
});
const form = ref(defaultForm());

const fetchRewards = async () => {
  isLoading.value = true;
  try {
    const res = await api.get('/admin/rewards');
    rewards.value = res.data.data;
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const openModal = (item = null) => {
  if (item) {
    isEditing.value = true;
    form.value = { ...item };
  } else {
    isEditing.value = false;
    form.value = defaultForm();
  }
  if (!modalInstance) {
    modalInstance = new Modal(document.getElementById('rewardModal'));
  }
  modalInstance.show();
};

const saveReward = async () => {
  isSubmitting.value = true;
  try {
    if (isEditing.value) {
      await api.put(`/admin/rewards/${form.value.id}`, form.value);
      Swal.fire({ toast: true, position: 'top-end', title: 'Thành công', text: 'Cập nhật thành công', icon: 'success', showConfirmButton: false, timer: 3000 });
    } else {
      await api.post('/admin/rewards', form.value);
      Swal.fire({ toast: true, position: 'top-end', title: 'Thành công', text: 'Thêm mới thành công', icon: 'success', showConfirmButton: false, timer: 3000 });
    }
    modalInstance.hide();
    fetchRewards();
  } catch (error) {
    Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: 'Có lỗi xảy ra', icon: 'error', showConfirmButton: false, timer: 3000 });
  } finally {
    isSubmitting.value = false;
  }
};

const deleteReward = async (id) => {
  const result = await Swal.fire({
    title: 'Xóa quà tặng?',
    text: 'Bạn không thể hoàn tác hành động này!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Đồng ý xóa'
  });

  if (result.isConfirmed) {
    try {
      await api.delete(`/admin/rewards/${id}`);
      Swal.fire({ toast: true, position: 'top-end', title: 'Đã xóa', text: 'Quà tặng đã bị xóa', icon: 'success', showConfirmButton: false, timer: 3000 });
      fetchRewards();
    } catch (error) {
      Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: 'Không thể xóa', icon: 'error', showConfirmButton: false, timer: 3000 });
    }
  }
};

onMounted(() => {
  fetchRewards();
});
</script>

<style scoped>
.admin-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
</style>
