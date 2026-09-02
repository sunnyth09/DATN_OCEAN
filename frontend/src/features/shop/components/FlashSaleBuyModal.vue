<script setup>
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import api, { getUser } from '@/axios.js';
import AppIcon from '@/components/AppIcon.vue';
import { getStorageUrl } from '@/utils/url';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  saleItem: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'success']);
const router = useRouter();

const user = computed(() => getUser());
const addresses = ref([]);
const selectedAddressId = ref(null);
const recipientName = ref('');
const recipientPhone = ref('');
const shippingAddress = ref('');
const paymentMethod = ref('cod');
const isSubmitting = ref(false);
const isLoadingAddresses = ref(false);
const variants = ref([]);
const selectedVariantId = ref(null);
const selectedColor = ref(null);
const selectedSize = ref(null);

const isOpen = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

const formatPrice = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
};

const resolveImage = (url) => {
  if (!url) return '/images/no-image.png';
  if (url.startsWith('http')) return url;
  return getStorageUrl(url);
};

const uniqueColors = computed(() => {
  return [...new Set(variants.value.map((v) => v.color).filter(Boolean))];
});

const uniqueSizes = computed(() => {
  return [...new Set(variants.value.map((v) => v.size).filter(Boolean))];
});

const autoSelectVariant = () => {
  if (variants.value.length === 0) return;
  const inStock = variants.value.find((v) => (v.stock || 0) > 0) || variants.value[0];
  if (inStock) {
    selectedVariantId.value = inStock.variant_id;
    selectedColor.value = inStock.color || null;
    selectedSize.value = inStock.size || null;
  }
};

const onSelectColor = (color) => {
  selectedColor.value = color;
  const varsForColor = variants.value.filter((v) => v.color === color);
  if (varsForColor.length === 0) return;

  const currentSizeMatch = selectedSize.value
    ? varsForColor.find((v) => v.size === selectedSize.value && (v.stock || 0) > 0)
    : null;
  const inStockMatch = varsForColor.find((v) => (v.stock || 0) > 0);
  const matched = currentSizeMatch || inStockMatch || varsForColor[0];

  if (matched) {
    selectedVariantId.value = matched.variant_id;
    selectedSize.value = matched.size || null;
  }
};

const onSelectSize = (size) => {
  selectedSize.value = size;
  const matched = variants.value.find(
    (v) => v.size === size && (selectedColor.value ? v.color === selectedColor.value : true)
  ) || variants.value.find((v) => v.size === size);
  if (matched) {
    selectedVariantId.value = matched.variant_id;
    if (matched.color) selectedColor.value = matched.color;
  }
};

const loadProductVariants = async () => {
  const prodId = props.saleItem.product_id;
  if (!prodId) return;
  if (Array.isArray(props.saleItem.variants) && props.saleItem.variants.length > 0) {
    variants.value = props.saleItem.variants;
    autoSelectVariant();
    return;
  }
  try {
    const res = await api.get(`/products/${prodId}/variants`);
    variants.value = res.data?.data || [];
    autoSelectVariant();
  } catch (e) {
    variants.value = [];
  }
};

const loadUserAddresses = async () => {
  if (!user.value) return;
  isLoadingAddresses.value = true;
  try {
    const res = await api.get('/profile/addresses');
    const list = res.data?.data || [];
    addresses.value = list;

    // Ưu tiên địa chỉ mặc định
    const defaultAddr = list.find((a) => a.is_default) || list[0];
    if (defaultAddr) {
      selectedAddressId.value = defaultAddr.address_id || defaultAddr.id;
      recipientName.value = defaultAddr.recipient_name || user.value.name || '';
      recipientPhone.value = defaultAddr.phone || user.value.phone || '';
      shippingAddress.value = [
        defaultAddr.address_line,
        defaultAddr.ward,
        defaultAddr.district,
        defaultAddr.province,
      ]
        .filter(Boolean)
        .join(', ');
    } else {
      recipientName.value = user.value.name || '';
      recipientPhone.value = user.value.phone || '';
      shippingAddress.value = '';
    }
  } catch (e) {
    recipientName.value = user.value.name || '';
    recipientPhone.value = user.value.phone || '';
  } finally {
    isLoadingAddresses.value = false;
  }
};

const onSelectSavedAddress = (e) => {
  const addrId = Number(e.target.value);
  const addr = addresses.value.find((a) => (a.address_id || a.id) === addrId);
  if (addr) {
    recipientName.value = addr.recipient_name || '';
    recipientPhone.value = addr.phone || '';
    shippingAddress.value = [addr.address_line, addr.ward, addr.district, addr.province]
      .filter(Boolean)
      .join(', ');
  }
};

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      paymentMethod.value = 'cod';
      loadUserAddresses();
      loadProductVariants();
    }
  }
);

const handleConfirmBuy = async () => {
  if (!user.value) {
    isOpen.value = false;
    Swal.fire({
      icon: 'info',
      title: 'Yêu cầu đăng nhập',
      text: 'Vui lòng đăng nhập tài khoản để tham gia săn Flash Sale!',
      confirmButtonText: 'Đăng nhập ngay',
      showCancelButton: true,
      cancelButtonText: 'Để sau',
      confirmButtonColor: '#E63B6F',
    }).then((r) => {
      if (r.isConfirmed) router.push('/client/login');
    });
    return;
  }

  if (!recipientName.value.trim()) {
    Swal.fire({ icon: 'warning', text: 'Vui lòng nhập tên người nhận hàng!' });
    return;
  }
  if (!recipientPhone.value.trim()) {
    Swal.fire({ icon: 'warning', text: 'Vui lòng nhập số điện thoại nhận hàng!' });
    return;
  }
  if (!shippingAddress.value.trim()) {
    Swal.fire({ icon: 'warning', text: 'Vui lòng nhập địa chỉ giao hàng chi tiết!' });
    return;
  }

  isSubmitting.value = true;
  try {
    const payload = {
      flash_sale_id: props.saleItem.flash_sale_id || props.saleItem.id,
      product_id: props.saleItem.product_id,
      variant_id: selectedVariantId.value,
      quantity: 1,
      recipient_name: recipientName.value.trim(),
      recipient_phone: recipientPhone.value.trim(),
      shipping_address: shippingAddress.value.trim(),
      payment_method: paymentMethod.value,
    };

    const res = await api.post('/flash-sale/buy', payload);

    if (res.data?.status === 'success') {
      isOpen.value = false;
      emit('success', res.data);

      Swal.fire({
        icon: 'success',
        title: 'SĂN DEAL THÀNH CÔNG!',
        html: `
          <div style="text-align: left; font-size: 0.95rem; line-height: 1.6; margin-top: 10px;">
            <p>Mã đơn hàng: <strong style="color: #E63B6F; font-size: 1.1rem;">#${res.data.order_code}</strong></p>
            <p>Sản phẩm: <strong>${props.saleItem.product_name || props.saleItem.name}</strong></p>
            <p>Tổng thanh toán: <strong style="color: #16a34a;">${formatPrice(props.saleItem.sale_price || props.saleItem.flash_price)}</strong> (Freeship 100%)</p>
            <p style="color: #64748b; font-size: 0.85rem; margin-top: 8px;">Đơn hàng Flash Sale đang được xử lý và đóng gói ưu tiên!</p>
          </div>
        `,
        confirmButtonText: 'Xem chi tiết đơn hàng',
        showCancelButton: true,
        cancelButtonText: 'Tiếp tục mua sắm',
        confirmButtonColor: '#E63B6F',
      }).then((r) => {
        if (r.isConfirmed) {
          router.push({ name: 'profile-orders' });
        }
      });
    }
  } catch (err) {
    const message = err.response?.data?.message || 'Không thể đặt mua sản phẩm Flash Sale. Vui lòng thử lại!';
    Swal.fire({
      icon: 'error',
      title: 'Đặt hàng không thành công',
      text: message,
      confirmButtonColor: '#E63B6F',
    });
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <Teleport to="body">
    <Transition name="fs-modal">
      <div v-if="isOpen" class="fs-fast-modal-overlay" @click.self="isOpen = false">
        <div class="fs-fast-modal-card">
          <!-- Header -->
          <div class="fs-fast-modal-header">
            <div class="fs-header-title">
              <div class="fs-lightning-badge">
                <AppIcon name="zap" size="18" stroke-width="2.5" />
              </div>
              <div>
                <h3 class="fs-modal-heading">Đặt Hàng Nhanh Flash Sale</h3>
                <p class="fs-modal-sub">Ưu đãi độc quyền — Giới hạn 1 sản phẩm / khách hàng</p>
              </div>
            </div>
            <button class="fs-btn-close" @click="isOpen = false" aria-label="Đóng">
              <AppIcon name="x" size="20" stroke-width="2" />
            </button>
          </div>

          <!-- Body -->
          <div class="fs-fast-modal-body">
            <!-- Product Preview Card -->
            <div class="fs-product-preview">
              <img
                :src="resolveImage(saleItem.product_thumbnail || saleItem.thumbnail_url || saleItem.image_url)"
                :alt="saleItem.product_name || saleItem.name"
                class="fs-prod-img"
              />
              <div class="fs-prod-info">
                <span class="fs-deal-badge">🔥 Flash Deal</span>
                <h4 class="fs-prod-name">{{ saleItem.product_name || saleItem.name }}</h4>
                <div class="fs-price-group">
                  <span class="fs-price-flash">{{ formatPrice(saleItem.sale_price || saleItem.flash_price) }}</span>
                  <span class="fs-price-origin" v-if="saleItem.original_price">{{ formatPrice(saleItem.original_price) }}</span>
                  <span class="fs-discount-tag" v-if="saleItem.discount_percent">-{{ saleItem.discount_percent }}%</span>
                </div>
                <div class="fs-freeship-pill">
                  <AppIcon name="truck" size="13" /> Miễn phí vận chuyển 100%
                </div>
              </div>
            </div>

            <!-- Variant Selection (Colors & Sizes) -->
            <div class="fs-variants-section" v-if="uniqueColors.length > 0 || uniqueSizes.length > 0">
              <div class="fs-var-group" v-if="uniqueColors.length > 0">
                <label class="fs-var-title">Màu sắc: <strong>{{ selectedColor || 'Chưa chọn' }}</strong></label>
                <div class="fs-chips">
                  <button
                    type="button"
                    v-for="color in uniqueColors"
                    :key="color"
                    class="fs-chip-btn"
                    :class="{ active: selectedColor === color }"
                    @click="onSelectColor(color)"
                  >
                    {{ color }}
                  </button>
                </div>
              </div>

              <div class="fs-var-group mt-2" v-if="uniqueSizes.length > 0">
                <label class="fs-var-title">Kích cỡ / Size: <strong>{{ selectedSize || 'Chưa chọn' }}</strong></label>
                <div class="fs-chips">
                  <button
                    type="button"
                    v-for="size in uniqueSizes"
                    :key="size"
                    class="fs-chip-btn"
                    :class="{ active: selectedSize === size }"
                    @click="onSelectSize(size)"
                  >
                    {{ size }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Address Section -->
            <div class="fs-section">
              <div class="fs-section-header">
                <span class="fs-section-title">
                  <AppIcon name="map-pin" size="16" /> Thông tin nhận hàng
                </span>
                <select
                  v-if="addresses.length > 1"
                  class="fs-address-select"
                  @change="onSelectSavedAddress"
                  :value="selectedAddressId"
                >
                  <option v-for="addr in addresses" :key="addr.address_id || addr.id" :value="addr.address_id || addr.id">
                    {{ addr.recipient_name }} - {{ addr.province }} ({{ addr.is_default ? 'Mặc định' : 'Phụ' }})
                  </option>
                </select>
              </div>

              <div class="fs-form-grid">
                <div class="fs-field">
                  <label>Họ và tên người nhận <span class="req">*</span></label>
                  <input
                    type="text"
                    v-model="recipientName"
                    class="fs-input"
                    placeholder="Ví dụ: Nguyễn Văn A"
                  />
                </div>
                <div class="fs-field">
                  <label>Số điện thoại nhận hàng <span class="req">*</span></label>
                  <input
                    type="tel"
                    v-model="recipientPhone"
                    class="fs-input"
                    placeholder="Ví dụ: 0912345678"
                  />
                </div>
              </div>

              <div class="fs-field mt-2">
                <label>Địa chỉ nhận hàng chi tiết <span class="req">*</span></label>
                <textarea
                  v-model="shippingAddress"
                  class="fs-input fs-textarea"
                  rows="2"
                  placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố"
                ></textarea>
              </div>
            </div>

            <!-- Payment Method Section -->
            <div class="fs-section">
              <span class="fs-section-title mb-2">
                <AppIcon name="credit-card" size="16" /> Phương thức thanh toán
              </span>
              <div class="fs-payment-methods">
                <label class="fs-pay-card" :class="{ active: paymentMethod === 'cod' }">
                  <input type="radio" v-model="paymentMethod" value="cod" />
                  <span class="fs-pay-icon">💵</span>
                  <div class="fs-pay-text">
                    <strong>Thanh toán khi nhận hàng (COD)</strong>
                    <small>Nhận hàng kiểm tra rồi mới thanh toán</small>
                  </div>
                </label>

                <label class="fs-pay-card" :class="{ active: paymentMethod === 'vnpay' }">
                  <input type="radio" v-model="paymentMethod" value="vnpay" />
                  <span class="fs-pay-icon">💳</span>
                  <div class="fs-pay-text">
                    <strong>Cổng thanh toán VNPay</strong>
                    <small>Quét mã QR / Thẻ ATM / Visa / Mastercard</small>
                  </div>
                </label>

                <label class="fs-pay-card" :class="{ active: paymentMethod === 'wallet' }">
                  <input type="radio" v-model="paymentMethod" value="wallet" />
                  <span class="fs-pay-icon">👛</span>
                  <div class="fs-pay-text">
                    <strong>Ví điện tử Ocean</strong>
                    <small>Thanh toán nhanh từ số dư ví nội bộ</small>
                  </div>
                </label>
              </div>
            </div>

            <!-- Bill Summary -->
            <div class="fs-bill-card">
              <div class="fs-bill-row">
                <span>Số lượng:</span>
                <strong>1 sản phẩm (Tối đa)</strong>
              </div>
              <div class="fs-bill-row">
                <span>Tạm tính giá Flash Sale:</span>
                <span>{{ formatPrice(saleItem.sale_price || saleItem.flash_price) }}</span>
              </div>
              <div class="fs-bill-row text-success">
                <span>Phí vận chuyển ưu đãi:</span>
                <span class="fw-bold">0₫ (Miễn phí)</span>
              </div>
              <div class="fs-bill-divider"></div>
              <div class="fs-bill-row fs-bill-total">
                <span>Tổng thanh toán:</span>
                <strong class="fs-total-amount">{{ formatPrice(saleItem.sale_price || saleItem.flash_price) }}</strong>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="fs-fast-modal-footer">
            <button class="fs-btn-cancel" @click="isOpen = false" :disabled="isSubmitting">
              Hủy bỏ
            </button>
            <button class="fs-btn-submit" @click="handleConfirmBuy" :disabled="isSubmitting">
              <AppIcon name="zap" size="18" stroke-width="2.5" v-if="!isSubmitting" />
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
              <span>{{ isSubmitting ? 'Đang xác nhận đặt hàng...' : 'XÁC NHẬN SĂN DEAL NGAY' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fs-fast-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(6px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.fs-fast-modal-card {
  background: #ffffff;
  border-radius: 20px;
  max-width: 580px;
  width: 100%;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  overflow: hidden;
  animation: modalPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPop {
  0% { transform: scale(0.95) translateY(10px); opacity: 0; }
  100% { transform: scale(1) translateY(0); opacity: 1; }
}

.fs-fast-modal-header {
  padding: 18px 24px;
  background: linear-gradient(135deg, #fff1f2 0%, #ffffff 100%);
  border-bottom: 1px solid #ffe4e6;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.fs-header-title {
  display: flex;
  align-items: center;
  gap: 12px;
}

.fs-lightning-badge {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(225, 29, 72, 0.3);
}

.fs-modal-heading {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: #1e293b;
}

.fs-modal-sub {
  margin: 2px 0 0;
  font-size: 0.8rem;
  color: #64748b;
}

.fs-btn-close {
  background: #f1f5f9;
  border: none;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.fs-btn-close:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.fs-fast-modal-body {
  padding: 20px 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

/* Product preview */
.fs-product-preview {
  display: flex;
  gap: 16px;
  padding: 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  align-items: center;
}

.fs-prod-img {
  width: 80px;
  height: 80px;
  border-radius: 10px;
  object-fit: cover;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.fs-prod-info {
  flex: 1;
  min-width: 0;
}

.fs-deal-badge {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  color: #e11d48;
  background: #ffe4e6;
  padding: 2px 8px;
  border-radius: 6px;
  margin-bottom: 4px;
}

.fs-prod-name {
  margin: 0 0 6px;
  font-size: 0.95rem;
  font-weight: 700;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.fs-price-group {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 4px;
}

.fs-price-flash {
  font-size: 1.15rem;
  font-weight: 800;
  color: #e11d48;
}

.fs-price-origin {
  font-size: 0.85rem;
  color: #94a3b8;
  text-decoration: line-through;
}

.fs-discount-tag {
  font-size: 0.75rem;
  font-weight: 700;
  color: #ffffff;
  background: #e11d48;
  padding: 1px 6px;
  border-radius: 4px;
}

.fs-freeship-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.75rem;
  font-weight: 700;
  color: #059669;
}

/* Variant selection */
.fs-variants-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: #fdf2f8;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid #fbcfe8;
}

.fs-var-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.fs-var-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: #831843;
}

.fs-var-title strong {
  color: #be123c;
}

.fs-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.fs-chip-btn {
  padding: 5px 12px;
  border-radius: 20px;
  border: 1.5px solid #cbd5e1;
  background: #ffffff;
  font-size: 0.8rem;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  transition: all 0.2s;
}

.fs-chip-btn:hover {
  border-color: #e11d48;
  color: #e11d48;
}

.fs-chip-btn.active {
  background: #e11d48;
  border-color: #e11d48;
  color: #ffffff;
}

/* Sections & Form */
.fs-section {
  display: flex;
  flex-direction: column;
}

.fs-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.fs-section-title {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.9rem;
  font-weight: 700;
  color: #1e293b;
}

.fs-address-select {
  font-size: 0.8rem;
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
  color: #334155;
  outline: none;
}

.fs-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

@media (max-width: 520px) {
  .fs-form-grid {
    grid-template-columns: 1fr;
  }
}

.fs-field label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 4px;
}

.fs-field .req {
  color: #e11d48;
}

.fs-input {
  width: 100%;
  padding: 9px 12px;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.88rem;
  color: #0f172a;
  outline: none;
  transition: all 0.2s;
  background: #ffffff;
}

.fs-input:focus {
  border-color: #e11d48;
  box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.12);
}

.fs-textarea {
  resize: vertical;
  min-height: 54px;
}

/* Payment Methods */
.fs-payment-methods {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.fs-pay-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  background: #ffffff;
}

.fs-pay-card:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.fs-pay-card.active {
  border-color: #e11d48;
  background: #fff1f2;
}

.fs-pay-icon {
  font-size: 1.2rem;
}

.fs-pay-text {
  display: flex;
  flex-direction: column;
}

.fs-pay-text strong {
  font-size: 0.85rem;
  color: #0f172a;
}

.fs-pay-text small {
  font-size: 0.75rem;
  color: #64748b;
}

/* Bill Summary */
.fs-bill-card {
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 0.85rem;
}

.fs-bill-row {
  display: flex;
  justify-content: space-between;
  color: #475569;
}

.fs-bill-divider {
  height: 1px;
  background: #e2e8f0;
  margin: 4px 0;
}

.fs-bill-total {
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
}

.fs-total-amount {
  color: #e11d48;
  font-size: 1.15rem;
}

/* Footer */
.fs-fast-modal-footer {
  padding: 16px 24px;
  border-top: 1px solid #e2e8f0;
  background: #ffffff;
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

.fs-btn-cancel {
  padding: 11px 20px;
  border-radius: 10px;
  border: 1.5px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
}

.fs-btn-cancel:hover {
  background: #f1f5f9;
}

.fs-btn-submit {
  flex: 1;
  padding: 11px 20px;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
  color: #ffffff;
  font-weight: 800;
  font-size: 0.95rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 4px 14px rgba(225, 29, 72, 0.35);
  transition: all 0.2s;
}

.fs-btn-submit:hover:not(:disabled) {
  background: linear-gradient(135deg, #be123c 0%, #9f1239 100%);
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(225, 29, 72, 0.45);
}

.fs-btn-submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

/* Modal Transition */
.fs-modal-enter-active,
.fs-modal-leave-active {
  transition: opacity 0.25s ease;
}

.fs-modal-enter-from,
.fs-modal-leave-to {
  opacity: 0;
}
</style>
