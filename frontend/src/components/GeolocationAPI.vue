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
import axios from 'axios';
import Swal from 'sweetalert2';

const isCheckedIn = ref(false);
const checkInTime = ref('');
const loading = ref(false);

const handleCheckIn = () => {
  loading.value = true;
  
  // Lấy vị trí GPS của nhân viên
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(async (position) => {
      try {
        const response = await axios.post('/api/attendance/check-in', {
          lat: position.coords.latitude,
          lng: position.coords.longitude
        });
        isCheckedIn.value = true;
        checkInTime.value = response.data.data.check_in_at;
      } catch (error) {
        Swal.fire('Lỗi', error.response.data.message, 'error');
      } finally {
        loading.value = false;
      }
    }, (error) => {
      Swal.fire('Lưu ý', "Bạn cần cấp quyền truy cập vị trí để check-in!", 'warning');
      loading.value = false;
    });
  }
};
</script>