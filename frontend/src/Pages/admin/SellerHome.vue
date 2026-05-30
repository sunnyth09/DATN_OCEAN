<template>
  <div class="seller-dashboard">
    <div class="welcome-card card shadow-sm border-0 mb-4 bg-primary text-white">
      <div class="card-body p-4 p-md-5">
        <h2 class="display-6 fw-bold mb-3">Chào mừng trở lại, {{ userName }}! 👋</h2>
        <p class="lead mb-0 opacity-75">
          Hôm nay là một ngày tuyệt vời để hỗ trợ khách hàng và bắt đầu các đơn hàng mới. Đừng quên tiến hành kiểm tra công việc và điểm danh đúng giờ!
        </p>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <!-- Quick Action: POS -->
      <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 text-center quick-action-card" @click="$router.push('/seller/pos')">
          <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
            <div class="icon-circle bg-blue-100 text-blue-600 mb-3">
              <i class="bi bi-shop fs-3"></i>
            </div>
            <h5 class="fw-bold">Bán hàng (POS)</h5>
            <p class="text-muted small mb-0">Tạo đơn hàng nhanh chóng tại quầy</p>
          </div>
        </div>
      </div>
      
      <!-- Quick Action: Đơn hàng -->
      <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 text-center quick-action-card" @click="$router.push('/seller/order')">
          <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
            <div class="icon-circle bg-green-100 text-green-600 mb-3">
              <i class="bi bi-box-seam fs-3"></i>
            </div>
            <h5 class="fw-bold">Quản lý Đơn hàng</h5>
            <p class="text-muted small mb-0">Theo dõi, xác nhận và cập nhật trạng thái đơn</p>
          </div>
        </div>
      </div>

      <!-- Quick Action: CSKH -->
      <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 text-center quick-action-card" @click="$router.push('/seller/chat')">
          <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
            <div class="icon-circle bg-purple-100 text-purple-600 mb-3">
              <i class="bi bi-chat-dots fs-3"></i>
            </div>
            <h5 class="fw-bold">Chăm sóc khách hàng</h5>
            <p class="text-muted small mb-0">Trao đổi và giải đáp thắc mắc qua Live Chat</p>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-clipboard-check me-2"></i>Chính sách & Hướng dẫn dành cho Nhân viên Bán hàng</h5>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item px-0 py-3 d-flex align-items-start border-bottom">
                    <i class="bi bi-dot fs-4 text-primary me-2" style="margin-top:-6px"></i>
                    <div>
                        <strong>Yêu cầu Chấm công:</strong> Bạn cần phải tiến hành Check-in và Check-out mỗi ngày để được ghi nhận vào hệ thống nội bộ. Yêu cầu bật chia sẻ Vị trí (GPS) và sử dụng đúng Wifi cửa hàng.
                    </div>
                </li>
                <li class="list-group-item px-0 py-3 d-flex align-items-start border-bottom">
                    <i class="bi bi-dot fs-4 text-primary me-2" style="margin-top:-6px"></i>
                    <div>
                        <strong>Thao tác Đơn hàng:</strong> Đối với đơn mới, phải gọi xác nhận trước khi "Duyệt". Nếu hủy đơn, cần ghi rõ lý do.
                    </div>
                </li>
                 <li class="list-group-item px-0 py-3 d-flex align-items-start">
                    <i class="bi bi-dot fs-4 text-primary me-2" style="margin-top:-6px"></i>
                    <div>
                        <strong>Hỗ trợ Khách hàng:</strong> Trả lời tin nhắn theo quy tắc chuyên nghiệp. Không chia sẻ giá vốn và các cấu hình ưu đãi nhạy cảm.
                    </div>
                </li>
            </ul>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const userName = ref('');

onMounted(() => {
  const userData = sessionStorage.getItem('user');
  if (userData) {
    try {
      const user = JSON.parse(userData);
      userName.value = user.full_name || user.name || 'Seller';
    } catch (e) {
      console.error("Failed to parse user data", e);
    }
  }
});
</script>

<style scoped>
.welcome-card {
  border-radius: 16px;
  background: linear-gradient(135deg, #1d4ed8 0%, #E63B6F 100%);
}

.quick-action-card {
  border-radius: 16px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.quick-action-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
  border-color: #E63B6F !important;
}

.icon-circle {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bg-blue-100 { background-color: #dbeafe; }
.text-blue-600 { color: #2563eb; }

.bg-green-100 { background-color: #dcfce7; }
.text-green-600 { color: #16a34a; }

.bg-purple-100 { background-color: #f3e8ff; }
.text-purple-600 { color: #9333ea; }
</style>
