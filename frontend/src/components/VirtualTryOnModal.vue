<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import api from '@/axios';

const props = defineProps({
  productId: { type: Number, required: true },
  productImageUrl: { type: String, required: true },
  productName: { type: String, default: '' },
  show: { type: Boolean, default: false }
});

const emit = defineEmits(['close']);

const fileInputRef = ref(null);
const uploadedFile = ref(null);
const previewImage = ref(null);
const isLoading = ref(false);
const resultImage = ref(null);
const isMock = ref(false);
const errorMessage = ref('');

const handleFileUpload = (event) => {
  const file = event.target.files[0];
  if (!file) return;

  // Validate size
  if (file.size > 5 * 1024 * 1024) {
    errorMessage.value = "Kích thước ảnh tối đa là 5MB.";
    return;
  }
  
  // Validate type
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    errorMessage.value = "Chỉ hỗ trợ ảnh JPG, PNG, WEBP.";
    return;
  }

  uploadedFile.value = file;
  errorMessage.value = '';
  resultImage.value = null;

  const reader = new FileReader();
  reader.onload = (e) => {
    previewImage.value = e.target.result;
  };
  reader.readAsDataURL(file);
};

const triggerFileInput = () => {
  if (fileInputRef.value) fileInputRef.value.click();
};

const submitTryOn = async () => {
  if (!uploadedFile.value) {
    errorMessage.value = "Vui lòng tải lên ảnh của bạn.";
    return;
  }

  isLoading.value = true;
  errorMessage.value = '';

  const formData = new FormData();
  formData.append('product_id', props.productId);
  formData.append('user_image', uploadedFile.value);

  try {
    const response = await api.post('/try-on', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      },
      timeout: 90000 // Timeout 90s cho AI processing
    });

    if (response.data.status === 'success') {
      resultImage.value = response.data.data.result_image_url;
      isMock.value = response.data.data.is_mock;
    }
  } catch (error) {
    console.error("Try-On Error:", error);
    errorMessage.value = error.response?.data?.message || "Đã có lỗi xảy ra. Vui lòng thử lại sau.";
  } finally {
    isLoading.value = false;
  }
};

const reset = () => {
  uploadedFile.value = null;
  previewImage.value = null;
  resultImage.value = null;
  errorMessage.value = '';
  isMock.value = false;
  if (fileInputRef.value) fileInputRef.value.value = '';
};

const closeModal = () => {
  reset();
  emit('close');
};

watch(() => props.show, (newVal) => {
  if (!newVal) {
    reset();
  }
});

// Chặn scroll body khi mở modal
watch(() => props.show, (newVal) => {
  if (newVal) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = 'auto';
  }
});

onBeforeUnmount(() => {
  document.body.style.overflow = 'auto';
});
</script>

<template>
  <teleport to="body">
    <transition name="modal-fade">
      <div v-if="show" class="tryon-overlay" @click.self="closeModal">
        <div class="tryon-modal">
          
          <div class="tryon-header">
            <h3 class="tryon-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path><circle cx="12" cy="13" r="3"></circle></svg>
              AI Virtual Try-On
              <span class="badge active">Powered by AI</span>
            </h3>
            <button class="tryon-close" @click="closeModal" title="Đóng">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div class="tryon-body">
            
            <div class="tryon-grid" v-if="!resultImage && !isLoading">
              <!-- Cột Ảnh Sản Phẩm -->
              <div class="tryon-col product-col">
                <div class="img-box">
                  <img :src="productImageUrl" :alt="productName" />
                </div>
                <div class="col-title">{{ productName }}</div>
              </div>

              <!-- Cột Upload Ảnh -->
              <div class="tryon-col user-col">
                <div class="img-box upload-box" @click="triggerFileInput" :class="{ 'has-img': previewImage }">
                  <img v-if="previewImage" :src="previewImage" alt="Ảnh của bạn" />
                  <div class="upload-placeholder" v-else>
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#636E72" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                    <span>Click để tải ảnh lên</span>
                    <small>JPG, PNG (Tối đa 5MB)</small>
                    <small style="color:#E63B6F; margin-top:5px">Chụp thẳng, rõ người</small>
                  </div>
                  <input ref="fileInputRef" type="file" accept="image/jpeg, image/png, image/webp" @change="handleFileUpload" hidden />
                </div>
                <div class="col-title">Ảnh của bạn</div>
              </div>
            </div>

            <!-- Loading State -->
            <div class="tryon-loading" v-if="isLoading">
              <div class="spinner"></div>
              <h4>AI đang phân tích và tạo ảnh...</h4>
              <p>Quá trình này có thể mất từ 15-30 giây. Vui lòng không đóng cửa sổ.</p>
            </div>

            <!-- Result State -->
            <div class="tryon-result" v-if="resultImage && !isLoading">
              <div class="img-box result-box">
                <img :src="resultImage" alt="Kết quả Try-On" />
                <div v-if="isMock" class="mock-badge">DEMO MODE</div>
              </div>
            </div>

            <!-- Error Message -->
            <div v-if="errorMessage" class="tryon-error">
              {{ errorMessage }}
            </div>

          </div>

          <!-- Controls -->
          <div class="tryon-controls">
            <button v-if="!resultImage && !isLoading" class="btn-primary" @click="submitTryOn" :disabled="!previewImage">
              ✨ Tạo ảnh thử áo
            </button>
            
            <button v-if="resultImage && !isLoading" class="btn-secondary" @click="reset">
              🔄 Thử ảnh khác
            </button>
            <button v-if="resultImage && !isLoading" class="btn-primary" @click="closeModal">
              Xong
            </button>
          </div>

        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.tryon-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(5px); display: flex; justify-content: center; align-items: center; z-index: 10000; padding: 16px;
}
.tryon-modal {
  background: #ffffff; border-radius: 20px; width: 100%; max-width: 750px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); display: flex; flex-direction: column;
}
.tryon-header {
  display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: #F8F9FA; border-bottom: 1px solid #E9ECEF;
}
.tryon-title {
  display: flex; align-items: center; gap: 10px; font-size: 1.1rem; font-weight: 800; color: #2D3436; margin: 0;
}
.badge { font-size: 0.7rem; padding: 4px 8px; border-radius: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.badge.active { background: #ECFDF5; color: #059669; }

.tryon-close { background: none; border: none; cursor: pointer; color: #636E72; border-radius: 8px; padding: 4px; display: flex; transition: all 0.2s; }
.tryon-close:hover { background: #E9ECEF; color: #E63B6F; }

.tryon-body { position: relative; width: 100%; min-height: 400px; padding: 24px; background: #fff; }

.tryon-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media (max-width: 600px) {
  .tryon-grid { grid-template-columns: 1fr; }
}

.tryon-col { display: flex; flex-direction: column; gap: 12px; }
.col-title { text-align: center; font-weight: 600; color: #2D3436; font-size: 0.95rem; }

.img-box { width: 100%; aspect-ratio: 3/4; border-radius: 12px; overflow: hidden; background: #F8F9FA; display: flex; justify-content: center; align-items: center; border: 1px solid #E9ECEF; position: relative; }
.img-box img { width: 100%; height: 100%; object-fit: contain; }

.upload-box { cursor: pointer; border: 2px dashed #B2BEC3; transition: all 0.2s; }
.upload-box:hover { border-color: #E63B6F; background: #FFF0F3; }
.upload-box.has-img { border: 2px solid #E63B6F; border-style: solid; }

.upload-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: #636E72; padding: 20px; text-align: center; }
.upload-placeholder span { font-weight: 600; color: #2D3436; }
.upload-placeholder small { font-size: 0.8rem; }

.tryon-loading { display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%; min-height: 350px; text-align: center; gap: 16px; }
.tryon-loading h4 { margin: 0; color: #2D3436; font-weight: 700; }
.tryon-loading p { color: #636E72; font-size: 0.9rem; margin: 0; }
.spinner { width: 48px; height: 48px; border: 4px solid #F3F4F6; border-top-color: #E63B6F; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.result-box { aspect-ratio: 3/4; max-height: 500px; margin: 0 auto; width: auto; max-width: 100%; }
.mock-badge { position: absolute; top: 12px; right: 12px; background: rgba(230, 59, 111, 0.9); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }

.tryon-error { background: #FFF0F3; color: #E63B6F; padding: 12px; border-radius: 8px; text-align: center; font-size: 0.9rem; font-weight: 500; margin-top: 20px; }

.tryon-controls { display: flex; justify-content: center; gap: 16px; padding: 16px 24px; background: #F8F9FA; border-top: 1px solid #E9ECEF; }
.tryon-controls button { padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.2s; border: none; font-family: inherit; }
.tryon-controls button:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-primary { background: #E63B6F; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #C4305D; }

.btn-secondary { background: #fff; color: #2D3436; border: 1px solid #B2BEC3 !important; }
.btn-secondary:hover { background: #F8F9FA; border-color: #2D3436 !important; }

.modal-fade-enter-active, .modal-fade-leave-active { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; transform: scale(0.95); }
</style>
