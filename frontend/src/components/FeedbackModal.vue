<script setup>
import { ref, watch, nextTick, computed } from 'vue';
import api from '@/axios';
import { Toast } from 'bootstrap';
import { getStorageUrl } from '@/utils/url';

const toastData = ref({ message: '', type: 'success' });
const showToast = (message, type = 'success') => {
  toastData.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('feedbackToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 3000 }).show();
  });
};

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  order: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['update:modelValue', 'feedback-submitted']);

// Form review cho mỗi item
const reviewForms = ref({});
const submitting = ref(false);

const initForms = () => {
    reviewForms.value = {};
    if (unreviewedItems.value.length > 0) {
        unreviewedItems.value.forEach(item => {
            reviewForms.value[item.order_item_id] = {
                rating: 0,
                content: ''
            };
        });
    }
};

watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        initForms();
    }
});

const unreviewedItems = computed(() => {
    if (!props.order || !props.order.items) return [];
    return props.order.items.filter(item => !item.comment);
});

const getProductImage = (item) => {
    let path = null;
    if (item.variant && item.variant.image_url) path = item.variant.image_url;
    else if (item.product && item.product.thumbnail_url) path = item.product.thumbnail_url;
    else return '0';
    return getImageUrl(path);
};


const setRating = (itemId, rating) => {
    if (reviewForms.value[itemId]) {
        reviewForms.value[itemId].rating = rating;
    }
};

const closeModal = () => {
    emit('update:modelValue', false);
};

const submitFeedback = async () => {
    submitting.value = true;
    let submittedCount = 0;
    
    // Thu thập các items đã được rate
    const itemsToSubmit = Object.keys(reviewForms.value).filter(
        itemId => reviewForms.value[itemId].rating > 0
    );

    if (itemsToSubmit.length === 0) {
        showToast('Vui lòng chọn số sao cho ít nhất 1 sản phẩm.', 'danger');
        submitting.value = false;
        return;
    }

    try {
        let lastError = null;
        for (const itemId of itemsToSubmit) {
            const form = reviewForms.value[itemId];
            const itemOriginal = props.order.items.find(i => i.order_item_id == itemId);
            if (!itemOriginal) continue;

            try {
               await api.post('/profile/orders/feedback', {
                   order_item_id: Number(itemId),
                   product_id: itemOriginal.product_id,
                   rating: form.rating,
                   content: form.content
               });
               submittedCount++;
            } catch (err) {
               console.error('Lỗi khi submit item ' + itemId, err.response?.data || err);
               lastError = err.response?.data?.message || 'Lỗi không xác định từ server';
            }
        }

        if (submittedCount > 0) {
            showToast('Các đánh giá hợp lệ đã được ghi nhận. Cảm ơn bạn!', 'success');
            emit('feedback-submitted');
            closeModal();
        } else {
            showToast(lastError ? lastError : 'Có thể bạn đã đánh giá toàn bộ sản phẩm trong đơn này rồi.', 'danger');
        }
    } catch (error) {
        console.error(error);
        showToast('Không thể gửi đánh giá lúc này.', 'danger');
    } finally {
        submitting.value = false;
    }
};

const getImageUrl = (path) => {
    if (!path || path === '0') return 'https://placehold.co/100x100?text=No+Image';
    return getStorageUrl(path);
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="modelValue" class="modal-overlay" @click.self="closeModal">
        <div class="modal-content review-modal-container">
          <!-- Header -->
          <div class="modal-header">
            <h3 class="modal-title">Đánh giá sản phẩm</h3>
            <button class="btn-close" @click="closeModal">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
          </div>

          <!-- Body -->
          <div class="modal-body">
            <p v-if="order" class="order-ref">Đơn hàng #{{ order.order_code }}</p>

            <div v-if="unreviewedItems.length > 0" class="review-items-list">
              <div v-for="item in unreviewedItems" :key="item.order_item_id" class="review-item-card">
                <div class="product-info-mini">
                  <img :src="getProductImage(item)" class="mini-img" :alt="item.product_name"/>
                  <div class="mini-details">
                    <p class="mini-name">{{ item.product_name }}</p>
                    <p class="mini-variant" v-if="item.variant_name">Phân loại: {{ item.variant_name }}</p>
                  </div>
                </div>

                <div class="rating-box">
                  <span class="rating-label">Chất lượng sản phẩm:</span>
                  <div class="stars">
                    <i 
                      v-for="star in 5" 
                      :key="star" 
                      class="fas fa-star" 
                      :class="{ 'active': reviewForms[item.order_item_id]?.rating >= star }"
                      @click="setRating(item.order_item_id, star)"
                    ></i>
                  </div>
                  <span class="rating-desc" v-if="reviewForms[item.order_item_id]?.rating > 0">
                    {{ ['Tệ', 'Không hài lòng', 'Bình thường', 'Hài lòng', 'Tuyệt vời'][reviewForms[item.order_item_id].rating - 1] }}
                  </span>
                </div>

                <textarea 
                  v-model="reviewForms[item.order_item_id].content"
                  class="review-textarea" 
                  placeholder="Hãy chia sẻ những điều bạn thích về sản phẩm này nhé."
                  rows="3"
                ></textarea>
              </div>
            </div>

          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <button class="btn-cancel" @click="closeModal" :disabled="submitting">Trở lại</button>
            <button class="btn-submit" @click="submitFeedback" :disabled="submitting">
              <span v-if="submitting" class="spinner-small"></span>
              <span v-else>Gửi Đánh Giá</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Bootstrap Toast (outside teleport so it's always accessible) -->
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 10050">
    <div class="toast align-items-center border-0" :class="toastData.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="feedbackToast" role="alert">
      <div class="d-flex">
        <div class="toast-body">{{ toastData.message }}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px);
  display: flex; justify-content: center; align-items: center; z-index: 10000;
  padding: 20px;
}
.review-modal-container {
  background: white; border-radius: 12px; width: 100%; max-width: 600px;
  display: flex; flex-direction: column; max-height: 90vh;
}
.modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 16px 20px; border-bottom: 1px solid #e2e8f0;
}
.modal-title { margin: 0; font-size: 1.2rem; font-weight: 700; color: #1e293b; }
.btn-close { background: none; border: none; cursor: pointer; color: #64748b; padding: 4px; }
.btn-close:hover { color: #0f172a; }

.modal-body {
  padding: 20px; overflow-y: auto; flex: 1;
}
.order-ref { font-size: 0.9rem; color: #64748b; margin-bottom: 16px; font-weight: 600; }

.review-items-list { display: flex; flex-direction: column; gap: 20px; }
.review-item-card { 
  border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; 
  background: #f8fafc;
}

.product-info-mini { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.mini-img { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0; }
.mini-details p { margin: 0; }
.mini-name { font-weight: 600; font-size: 0.95rem; color: #0f172a; }
.mini-variant { font-size: 0.85rem; color: #64748b; margin-top: 4px; }

.rating-box { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.rating-label { font-size: 0.95rem; font-weight: 600; color: #334155; }
.stars { display: flex; gap: 6px; }
.stars i { color: #cbd5e1; font-size: 1.4rem; cursor: pointer; transition: 0.2s; }
.stars i:hover, .stars i.active { color: #fbbf24; }
.rating-desc { font-size: 0.9rem; color: #d97706; font-weight: 600; }

.review-textarea {
  width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px;
  font-family: inherit; font-size: 0.95rem; resize: vertical; outline: none; background: white;
}
.review-textarea:focus { border-color: #E63B6F; }

.modal-footer {
  padding: 16px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px;
}
.btn-cancel { padding: 10px 20px; background: white; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; color: #475569; cursor: pointer; }
.btn-cancel:hover { background: #f1f5f9; }
.btn-submit { padding: 10px 20px; background: #E63B6F; border: 1px solid #E63B6F; border-radius: 8px; font-weight: 600; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; min-width: 120px; }
.btn-submit:hover:not(:disabled) { background: #0369a1; }
.btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }

.spinner-small {
  width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.4); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite;
}
@keyframes spin { 100% { transform: rotate(360deg); } }

/* Transition effects */
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.3s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-active .modal-content { animation: modalIn 0.3s ease-out; }
.modal-fade-leave-active .modal-content { animation: modalOut 0.3s ease-in; }
@keyframes modalIn { from { transform: scale(0.95) translateY(10px); } to { transform: scale(1) translateY(0); } }
@keyframes modalOut { from { transform: scale(1) translateY(0); } to { transform: scale(0.95) translateY(10px); } }
</style>
