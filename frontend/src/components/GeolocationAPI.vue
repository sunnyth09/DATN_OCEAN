<template>
  <div class="attendance-card">
    <h3>Quản lý ca làm việc</h3>
    <div v-if="!isCheckedIn">
      <button @click="handleCheckIn" :disabled="loading" class="btn-checkin">
        Check-in vào ca
      </button>
    </div>
    <div v-else>
      <p>Bắt đầu ca lúc: {{ checkInTime }}</p>
      <button @click="handleCheckOut" :disabled="loading" class="btn-checkout">
        Check-out kết thúc ca
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import api from '@/axios';
import Swal from 'sweetalert2';

const isCheckedIn = ref(false);
const checkInTime = ref('');
const loading = ref(false);

const getCurrentPosition = () => new Promise((resolve, reject) => {
  if (!navigator.geolocation) {
    reject(new Error('Geolocation is not supported'));
    return;
  }

  navigator.geolocation.getCurrentPosition(resolve, reject);
});

const handleCheckIn = async () => {
  loading.value = true;

  try {
    const position = await getCurrentPosition();
    const response = await api.post('/admin/attendance/check-in', {
      lat: position.coords.latitude,
      lng: position.coords.longitude,
    });

    isCheckedIn.value = true;
    checkInTime.value = response.data?.data?.check_in_at || '';
  } catch (error) {
    const message = error.response?.data?.message || 'Bạn cần cấp quyền vị trí để check-in.';
    Swal.fire('Lỗi', message, 'error');
  } finally {
    loading.value = false;
  }
};

const handleCheckOut = async () => {
  loading.value = true;

  try {
    let payload = {};

    try {
      const position = await getCurrentPosition();
      payload = {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
      };
    } catch {
      payload = {};
    }

    await api.post('/admin/attendance/check-out', payload);
    isCheckedIn.value = false;
    checkInTime.value = '';
    Swal.fire('Thành công', 'Check-out thành công.', 'success');
  } catch (error) {
    const message = error.response?.data?.message || 'Không thể check-out.';
    Swal.fire('Lỗi', message, 'error');
  } finally {
    loading.value = false;
  }
};
</script>
