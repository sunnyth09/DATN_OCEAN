<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import api from '@/axios';
import Swal from 'sweetalert2';
import { getStorageUrl } from '@/utils/url';

const showToastNotify = (message, type = 'success') => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    title: type === 'success' ? 'Thành công' : (type === 'error' || type === 'danger' ? 'Lỗi' : 'Thông báo'),
    text: message,
    icon: type === 'danger' ? 'error' : type,
    showConfirmButton: false,
    timer: 3000
  });
};
const toast = {
  success: (msg) => showToastNotify(msg, 'success'),
  error: (msg) => showToastNotify(msg, 'danger'),
};

const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleString('vi-VN', { hour: '2-digit', minute:'2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getImageUrl = (path) => {
  if (!path) return '/placeholder.jpg';
  return getStorageUrl(path);
};

// ================== SEARCH PRODUCTS ==================
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);

const handleSearch = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    return;
  }
  isSearching.value = true;
  try {
    const res = await api.get('/admin/pos/products/search', { params: { q: searchQuery.value } });
    if (res.data.status === 'success') {
      searchResults.value = res.data.data;
    }
  } catch (error) {
    console.error('Search error:', error);
  } finally {
    isSearching.value = false;
  }
};

let searchTimeout;
const onSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(handleSearch, 400);
};

// ================== SELECT VARIANT MODAL ==================
const selectedProduct = ref(null);
const selectedVariant = ref(null);
const showVariantModal = ref(false);

const selectProduct = (product) => {
  if (product.variants.length === 1) {
    addToCart(product.variants[0], product);
  } else {
    selectedProduct.value = product;
    selectedVariant.value = null;
    showVariantModal.value = true;
  }
};

const closeVariantModal = () => {
    showVariantModal.value = false;
    selectedProduct.value = null;
    selectedVariant.value = null;
};

const confirmVariantSelect = () => {
    if(!selectedVariant.value) return;
    addToCart(selectedVariant.value, selectedProduct.value);
    closeVariantModal();
};

const selectVariant = (variant) => {
  if(variant.stock <= 0) return;
  selectedVariant.value = variant;
};

// ================== CART ==================
const cartItems = ref([]);

const addToCart = (variant, product) => {
  const existingItem = cartItems.value.find(item => item.variant_id === variant.variant_id);
  if (existingItem) {
    if (existingItem.quantity < variant.stock) {
      existingItem.quantity++;
    } else {
      toast.error('Số lượng vượt quá tồn kho!');
    }
  } else {
    cartItems.value.push({
      variant_id: variant.variant_id,
      product_name: product.name,
      variant_name: variant.variant_name,
      color: variant.color,
      size: variant.size,
      price: variant.price,
      stock: variant.stock,
      image_url: variant.image_url || product.thumbnail,
      quantity: 1
    });
  }
};

const increaseQuantity = (item) => {
  if (item.quantity < item.stock) {
    item.quantity++;
  } else {
    toast.error('Số lượng vượt quá tồn kho!');
  }
};

const decreaseQuantity = (item) => {
  if (item.quantity > 1) {
    item.quantity--;
  } else {
    removeFromCart(item);
  }
};

const removeFromCart = (item) => {
  cartItems.value = cartItems.value.filter(i => i.variant_id !== item.variant_id);
};

const clearCart = async () => {
  if (cartItems.value.length === 0) return;

  const result = await Swal.fire({
    title: 'Xác nhận xóa',
    text: 'Bạn có chắc chắn muốn xóa toàn bộ sản phẩm khỏi đơn hàng hiện tại?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e63b6f',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Xóa tất cả',
    cancelButtonText: 'Hủy',
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    cartItems.value = [];
    removeCoupon();
    toast.success('Đã xóa toàn bộ sản phẩm khỏi đơn hàng!');
  }
};

// ================== COUPON ==================
const couponCode = ref('');
const appliedCoupon = ref(null);
const availableCoupons = ref([]);

const fetchCoupons = async () => {
    try {
        const res = await api.get('/coupons/public');
        if (res.data.status === 'success') availableCoupons.value = res.data.data;
    } catch (e) {
        console.error('Lỗi lấy coupons', e);
    }
};

const applyCoupon = () => {
    if (!couponCode.value.trim()) {
      toast.error('Vui lòng nhập mã giảm giá');
      return;
    }
    const found = availableCoupons.value.find(c => c.code.toUpperCase() === couponCode.value.trim().toUpperCase());
    if (found) {
        if (subtotal.value < (found.min_order_value || 0)) {
            toast.error(`Đơn tối thiểu phải từ ${formatPrice(found.min_order_value)}`);
            return;
        }
        appliedCoupon.value = found;
        let discount = 0;
        if (found.type === 'fixed') discount = found.value;
        else if (found.type === 'percent') discount = subtotal.value * (found.value / 100);
        if (found.max_discount_value && discount > found.max_discount_value) {
            discount = found.max_discount_value;
        }
        discountAmount.value = discount;
        toast.success(`Đã áp dụng mã ${found.code}`);
    } else {
        toast.error('Mã giảm giá không hợp lệ hoặc hết hạn');
    }
};

const removeCoupon = () => {
    appliedCoupon.value = null;
    couponCode.value = '';
    discountAmount.value = 0;
};

// ================== CHECKOUT ==================
const isCheckingOut = ref(false);
const paymentMethod = ref('pos_cash');
const customerName = ref('');
const customerPhone = ref('');
const note = ref('');
const discountAmount = ref(0);

const formattedDiscount = computed(() => {
  if (!discountAmount.value) return '';
  return new Intl.NumberFormat('vi-VN').format(discountAmount.value);
});

const onDiscountInput = (e) => {
  const el = e.target;
  let cursorPosition = el.selectionStart;
  const oldLength = el.value.length;
  const numericValue = String(el.value).replace(/\D/g, '');
  const newAmount = numericValue ? parseInt(numericValue, 10) : 0;
  discountAmount.value = newAmount;
  const formatted = newAmount ? new Intl.NumberFormat('vi-VN').format(newAmount) : '';
  if (el.value !== formatted) {
      el.value = formatted;
      const newLength = formatted.length;
      cursorPosition += (newLength - oldLength);
      if (cursorPosition < 0) cursorPosition = 0;
      el.setSelectionRange(cursorPosition, cursorPosition);
  }
};

// ================== CUSTOMER SEARCH ==================
const customerId = ref(null);
const customerFound = ref(false);
const isSearchingCustomer = ref(false);

let phoneTimeout;
const searchCustomerByPhone = async () => {
  if (customerPhone.value.length < 9) {
    customerFound.value = false;
    customerId.value = null;
    return;
  }
  isSearchingCustomer.value = true;
  try {
    const res = await api.get('/admin/users', { params: { search: customerPhone.value } });
    if (res.data && res.data.status === 'success' && res.data.data.length > 0) {
       const user = res.data.data.find(u => u.phone === customerPhone.value);
       if (user) {
         customerName.value = user.full_name;
         customerId.value = user.user_id;
         customerFound.value = true;
       } else {
         customerFound.value = false;
         customerId.value = null;
       }
    } else {
      customerFound.value = false;
      customerId.value = null;
    }
  } catch (e) {
    console.error('Lỗi tìm kiếm sđt khách hàng', e);
  } finally {
    isSearchingCustomer.value = false;
  }
};

const onPhoneInput = () => {
    customerFound.value = false;
    customerId.value = null;
    clearTimeout(phoneTimeout);
    phoneTimeout = setTimeout(searchCustomerByPhone, 400);
};

const subtotal = computed(() => {
  return cartItems.value.reduce((total, item) => total + (item.price * item.quantity), 0);
});

const grandTotal = computed(() => {
  return Math.max(0, subtotal.value - discountAmount.value);
});

const isDownloadingPdf = ref(false);

const downloadReceiptPdf = async (order) => {
    try {
        isDownloadingPdf.value = true;
        const response = await api.get(`/admin/pos/orders/${order.order_id}/receipt-pdf`, { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `hoadon_${order.order_code}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.parentNode.removeChild(link);
        toast.success('Đã tải PDF thành công!');
    } catch (error) {
        toast.error('Lỗi khi tải PDF hoá đơn. Vui lòng thử lại!');
        console.error('PDF error:', error);
    } finally {
        isDownloadingPdf.value = false;
    }
};

const checkoutOrder = ref(null);
const showCheckoutSuccess = ref(false);
const customerNameError = ref(false);

const handleCheckout = async () => {
  if (cartItems.value.length === 0) {
    toast.error('Giỏ hàng trống!');
    return;
  }
  if (!customerName.value || !customerName.value.trim()) {
    customerNameError.value = true;
    toast.error('Vui lòng nhập tên khách hàng!');
    nextTick(() => {
      const nameInput = document.querySelector('.customer-name-input');
      if (nameInput) nameInput.focus();
    });
    return;
  }
  customerNameError.value = false;

  isCheckingOut.value = true;
  try {
    const payload = {
      items: cartItems.value.map(item => ({
        variant_id: item.variant_id,
        quantity: item.quantity
      })),
      user_id: customerId.value,
      customer_name: customerName.value.trim(),
      customer_phone: customerPhone.value,
      payment_method: paymentMethod.value,
      note: note.value,
      discount_amount: discountAmount.value
    };
    const res = await api.post('/admin/pos/checkout', payload);
    if (res.data.status === 'success') {
      const createdOrder = res.data.data;
      checkoutOrder.value = createdOrder;
      showCheckoutSuccess.value = true;
      cartItems.value = [];
      customerName.value = '';
      customerPhone.value = '';
      customerId.value = null;
      customerFound.value = false;
      note.value = '';
      removeCoupon();
      searchQuery.value = '';
      searchResults.value = [];
      toast.success('Thanh toán đơn hàng thành công!');
    }
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Lỗi thanh toán';
    toast.error(errorMsg);
  } finally {
    isCheckingOut.value = false;
  }
};

// ================== BARCODE SCANNER ==================
const barcodeValue = ref('');
const isScannerActive = ref(true);
const showMobileScannerModal = ref(false);
const isScanning = ref(false);
const scanResult = ref(null);
const barcodeInputRef = ref(null);
let scanResultTimeout = null;

const focusBarcodeInput = () => {
  nextTick(() => {
    if (barcodeInputRef.value && isScannerActive.value) {
      barcodeInputRef.value.focus();
    }
  });
};

const toggleScanner = () => {
  isScannerActive.value = !isScannerActive.value;
  if (isScannerActive.value) {
    focusBarcodeInput();
  }
};

const showScanResult = (type, message) => {
  scanResult.value = { type, message };
  clearTimeout(scanResultTimeout);
  scanResultTimeout = setTimeout(() => {
    scanResult.value = null;
  }, 3000);
};

const sessionId = ref(crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).substring(2, 15));

const connectMobileScanner = () => {
    if (window.Echo) {
        window.Echo.private('pos-scanner.' + sessionId.value)
            .listen('PosBarcodeScanned', (e) => {
                const barcode = e.barcode;
                toast.success(`Đã nhận mã từ Mobile: ${barcode}`);
                searchByBarcode(barcode);
            });
    }
};

const searchByBarcode = async (barcode) => {
  if (!barcode || isScanning.value) return;
  isScanning.value = true;
  scanResult.value = null;
  try {
    const res = await api.get('/admin/pos/products/scan', { params: { code: barcode } });
    if (res.data.status === 'success') {
      const variantData = res.data.data;
      const existingItem = cartItems.value.find(item => item.variant_id === variantData.variant_id);
      if (existingItem) {
        if (existingItem.quantity < variantData.stock) {
          existingItem.quantity++;
          showScanResult('success', `+1 ${variantData.product.name} (${variantData.color || ''} ${variantData.size || ''})`);
        } else {
          showScanResult('error', 'Số lượng vượt quá tồn kho!');
        }
      } else {
        if (variantData.stock <= 0) {
          showScanResult('error', `${variantData.product.name} đã hết hàng!`);
        } else {
          cartItems.value.push({
            variant_id: variantData.variant_id,
            product_name: variantData.product.name,
            variant_name: variantData.variant_name,
            color: variantData.color,
            size: variantData.size,
            price: variantData.price,
            stock: variantData.stock,
            image_url: variantData.image_url || variantData.product.thumbnail,
            quantity: 1
          });
          showScanResult('success', `Đã thêm: ${variantData.product.name} (${variantData.color || ''} ${variantData.size || ''})`);
        }
      }
    }
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Không tìm thấy sản phẩm với mã này';
    showScanResult('error', errorMsg);
  } finally {
    isScanning.value = false;
    barcodeValue.value = '';
    focusBarcodeInput();
  }
};

const onBarcodeEnter = () => {
  const barcode = barcodeValue.value.trim();
  if (barcode) {
    searchByBarcode(barcode);
  }
};

let barcodeBuffer = '';
let barcodeTimeout = null;

const handleGlobalKeydown = (e) => {
  if (e.key === 'F2') {
    e.preventDefault();
    if (cartItems.value.length > 0) handleCheckout();
    return;
  }
  if (e.key === 'F3') {
    e.preventDefault();
    const searchInput = document.querySelector('.pos-search-input');
    if (searchInput) searchInput.focus();
    return;
  }

  const activeEl = document.activeElement;
  const isOtherInput = activeEl &&
    (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA') &&
    activeEl !== barcodeInputRef.value;

  if (isOtherInput || !isScannerActive.value) return;

  if (e.key === 'Enter' && barcodeBuffer.length > 3) {
    e.preventDefault();
    searchByBarcode(barcodeBuffer);
    barcodeBuffer = '';
    return;
  }

  if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
    barcodeBuffer += e.key;
    clearTimeout(barcodeTimeout);
    barcodeTimeout = setTimeout(() => {
      barcodeBuffer = '';
    }, 200);
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown);
  focusBarcodeInput();
  fetchCoupons();
  setTimeout(connectMobileScanner, 1000);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown);
  clearTimeout(scanResultTimeout);
  clearTimeout(barcodeTimeout);
  if (window.Echo) {
      window.Echo.leave('pos-scanner.' + sessionId.value);
  }
});
</script>

<template>
  <div class="pos-wrap">
    <!-- ===== LEFT: PRODUCT PANEL ===== -->
    <div class="pos-left">

      <!-- Top Bar -->
      <div class="pos-topbar">
        <div class="pos-brand">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
          </svg>
          <span>POS <span class="pos-brand-accent">Bán hàng</span></span>
        </div>
        <div class="pos-topbar-actions">
          <span class="pos-hint-key"><kbd>F2</kbd> Thanh toán</span>
          <span class="pos-hint-key"><kbd>F3</kbd> Tìm SP</span>
          <button class="scanner-pill" :class="{ active: isScannerActive }" @click="toggleScanner">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M3 5v4M3 5h4M21 5h-4M21 5v4M3 19v-4M3 19h4M21 19h-4M21 19v-4M7 10h10v4H7z"/>
            </svg>
            <span>Scanner {{ isScannerActive ? 'BẬT' : 'TẮT' }}</span>
            <span class="scanner-dot" :class="{ on: isScannerActive }"></span>
          </button>
        </div>
      </div>

      <!-- Barcode + Search -->
      <div class="pos-input-area">
        <!-- Barcode -->
        <transition name="slide-down">
          <div v-if="isScannerActive" class="barcode-row">
            <div class="barcode-field">
              <div class="barcode-icon-wrap">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :class="{ scanning: isScanning }">
                  <path d="M3 5v4M3 5h4M21 5h-4M21 5v4M3 19v-4M3 19h4M21 19h-4M21 19v-4M7 10h10v4H7z"/>
                </svg>
                <div v-if="isScanning" class="scan-ripple"></div>
              </div>
              <input
                ref="barcodeInputRef"
                type="text"
                v-model="barcodeValue"
                @keyup.enter="onBarcodeEnter"
                class="barcode-input"
                placeholder="Quét hoặc nhập mã barcode / SKU rồi nhấn Enter..."
                autocomplete="off"
                :disabled="isScanning"
              />
              <button class="barcode-btn" @click="onBarcodeEnter" :disabled="isScanning || !barcodeValue.trim()">
                <svg v-if="isScanning" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 0.8s linear infinite">
                  <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
                <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
              </button>
            </div>
            <transition name="fade-up">
              <div v-if="scanResult" class="scan-toast" :class="scanResult.type">
                <svg v-if="scanResult.type === 'success'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
                <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ scanResult.message }}
              </div>
            </transition>
          </div>
        </transition>

        <!-- Search Row -->
        <div class="search-row">
          <div class="search-field">
            <svg class="search-icon-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input
              type="text"
              class="search-input pos-search-input"
              v-model="searchQuery"
              @input="onSearchInput"
              placeholder="Tìm sản phẩm theo tên, SKU..."
            />
            <div v-if="isSearching" class="search-spinner">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 0.8s linear infinite">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
              </svg>
            </div>
          </div>
          <button class="qr-btn" @click="showMobileScannerModal = true" title="Quét bằng App">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
              <path d="M14 14h1v1h-1zM17 14h1v1h-1zM14 17h1v1h-1zM17 17h4v4h-4z"/>
            </svg>
            QR App
          </button>
        </div>
      </div>

      <!-- Product Grid -->
      <div class="product-area">
        <!-- Empty initial state -->
        <div v-if="searchResults.length === 0 && searchQuery.length < 2" class="empty-state">
          <div class="empty-illustration">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" opacity="0.3">
              <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/>
              <path d="M16 10a4 4 0 01-8 0"/>
            </svg>
          </div>
          <p class="empty-title">Quét barcode hoặc tìm kiếm sản phẩm</p>
          <p class="empty-sub">Nhập ít nhất 2 ký tự để hiện kết quả</p>
        </div>

        <!-- No results -->
        <div v-else-if="searchResults.length === 0 && searchQuery.length >= 2 && !isSearching" class="empty-state">
          <div class="empty-illustration">
            <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" opacity="0.3">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
          </div>
          <p class="empty-title">Không tìm thấy sản phẩm</p>
          <p class="empty-sub">Thử tìm bằng từ khóa khác hoặc quét barcode</p>
        </div>

        <!-- Product cards -->
        <div v-else-if="searchResults.length > 0" class="product-grid">
          <div
            v-for="product in searchResults"
            :key="product.product_id"
            class="product-card"
            @click="selectProduct(product)"
          >
            <div class="product-thumb">
              <img :src="getImageUrl(product.thumbnail)" :alt="product.name" onerror="this.src='/placeholder.jpg'" />
              <div v-if="product.variants.length > 1" class="multi-badge">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                {{ product.variants.length }} loại
              </div>
              <div class="card-overlay">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              </div>
            </div>
            <div class="product-meta">
              <p class="product-name">{{ product.name }}</p>
              <div class="product-price">
                <template v-if="product.variants.length === 1">
                  {{ formatPrice(product.variants[0].price) }}
                </template>
                <template v-else>
                  <span class="price-range">Xem tuỳ chọn →</span>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== RIGHT: CART & CHECKOUT ===== -->
    <div class="pos-right">

      <!-- Cart Header -->
      <div class="cart-head">
        <div class="cart-head-left">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/>
          </svg>
          <span class="cart-title">Đơn hiện tại</span>
          <span v-if="cartItems.length" class="cart-badge">{{ cartItems.length }}</span>
        </div>
        <button class="clear-btn" @click="clearCart" :disabled="cartItems.length === 0">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          Xóa hết
        </button>
      </div>

      <!-- Cart Items -->
      <div class="cart-list">
        <!-- Empty cart -->
        <div v-if="cartItems.length === 0" class="cart-empty">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" opacity="0.25">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
          </svg>
          <p>Giỏ hàng trống</p>
          <small>Quét barcode hoặc tìm sản phẩm để thêm</small>
        </div>

        <!-- Items -->
        <transition-group name="list" tag="div">
          <div v-for="item in cartItems" :key="item.variant_id" class="cart-item">
            <img :src="getImageUrl(item.image_url)" class="item-thumb" onerror="this.src='/placeholder.jpg'" />
            <div class="item-content">
              <div class="item-top">
                <span class="item-name" :title="item.product_name">{{ item.product_name }}</span>
                <button class="remove-btn" @click="removeFromCart(item)" title="Xoá">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
              </div>
              <div v-if="item.color || item.size" class="item-tags">
                <span v-if="item.color" class="tag">{{ item.color }}</span>
                <span v-if="item.size" class="tag">{{ item.size }}</span>
              </div>
              <div class="item-bottom">
                <div class="qty-wrap">
                  <button class="qty-btn" @click="decreaseQuantity(item)">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  </button>
                  <span class="qty-val">{{ item.quantity }}</span>
                  <button class="qty-btn" @click="increaseQuantity(item)">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  </button>
                </div>
                <div class="item-prices">
                  <span class="item-unit">{{ formatPrice(item.price) }}/sp</span>
                  <span class="item-total">{{ formatPrice(item.price * item.quantity) }}</span>
                </div>
              </div>
            </div>
          </div>
        </transition-group>
      </div>

      <!-- Checkout Panel -->
      <div class="checkout-panel">

        <!-- Customer -->
        <div class="panel-section">
          <div class="section-label">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Khách hàng <span style="color:#ef4444; margin-left:2px;">*</span>
          </div>
          <div class="customer-inputs">
            <div class="field-wrap" :class="{ 'has-error': customerNameError }">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <input type="text" v-model="customerName" class="customer-name-input" @input="customerNameError = false" placeholder="Tên khách hàng *" />
            </div>
            <div class="field-wrap">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.52 9.82 19.79 19.79 0 01.47 1.18 2 2 0 012.45.01h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.29 6.29l.79-.79a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
              <input type="text" v-model="customerPhone" @input="onPhoneInput" placeholder="Số điện thoại" />
              <span v-if="customerFound" class="check-icon">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
              </span>
              <svg v-if="isSearchingCustomer" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin 0.8s linear infinite; margin-left:4px;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            </div>
          </div>
          <div class="field-wrap mt-6">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <input type="text" v-model="note" placeholder="Ghi chú đơn hàng..." />
          </div>
        </div>

        <!-- Payment Method -->
        <div class="panel-section">
          <div class="section-label">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Phương thức thanh toán
          </div>
          <div class="pay-methods">
            <label class="pay-opt" :class="{ active: paymentMethod === 'pos_cash' }">
              <input type="radio" value="pos_cash" v-model="paymentMethod" />
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
              <span>Tiền mặt</span>
            </label>
            <label class="pay-opt" :class="{ active: paymentMethod === 'pos_transfer' }">
              <input type="radio" value="pos_transfer" v-model="paymentMethod" />
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h1v1h-1zM17 14h1v1h-1zM14 17h1v1h-1zM17 17h4v4h-4z"/></svg>
              <span>Chuyển khoản</span>
            </label>
            <label class="pay-opt" :class="{ active: paymentMethod === 'pos_card' }">
              <input type="radio" value="pos_card" v-model="paymentMethod" />
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
              <span>Quẹt thẻ</span>
            </label>
          </div>
        </div>

        <!-- Coupon -->
        <div class="panel-section">
          <div class="coupon-row">
            <div class="coupon-field">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
              <input type="text" class="coupon-input text-upper" placeholder="Nhập mã giảm giá..." v-model="couponCode" @keyup.enter="applyCoupon" />
            </div>
            <button v-if="!appliedCoupon" class="coupon-apply-btn" @click="applyCoupon">Áp dụng</button>
            <button v-else class="coupon-remove-btn" @click="removeCoupon">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Huỷ
            </button>
          </div>
          <div v-if="appliedCoupon" class="coupon-chip">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
            Mã <strong>{{ appliedCoupon.code }}</strong> đã được áp dụng
          </div>
        </div>

        <!-- Totals -->
        <div class="totals-block">
          <div class="total-line">
            <span>Tạm tính</span>
            <span>{{ formatPrice(subtotal) }}</span>
          </div>
          <div class="total-line discount-line">
            <span>Giảm giá</span>
            <div class="discount-field">
              <span class="d-symbol">₫</span>
              <input type="text" :value="formattedDiscount" @input="onDiscountInput" placeholder="0" />
            </div>
          </div>
          <div class="grand-line">
            <span>Khách phải trả</span>
            <span class="grand-amount">{{ formatPrice(grandTotal) }}</span>
          </div>
        </div>

        <!-- Checkout Button -->
        <button class="checkout-btn" @click="handleCheckout" :disabled="isCheckingOut || cartItems.length === 0">
          <span v-if="isCheckingOut">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin 0.8s linear infinite; margin-right:8px;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            Đang xử lý...
          </span>
          <span v-else>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:8px;"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            THANH TOÁN{{ cartItems.length > 0 ? ' · ' + formatPrice(grandTotal) : '' }}
          </span>
        </button>
      </div>
    </div>

    <!-- ===== MODAL: CHỌN BIẾN THỂ ===== -->
    <transition name="modal-pop">
      <div v-if="showVariantModal" class="modal-backdrop" @click.self="closeVariantModal">
        <div class="modal-box">
          <div class="modal-head">
            <div>
              <p class="modal-eyebrow">Chọn phân loại</p>
              <h3 class="modal-title">{{ selectedProduct?.name }}</h3>
            </div>
            <button class="modal-close" @click="closeVariantModal">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="modal-body">
            <div class="variants-list">
              <div
                v-for="variant in selectedProduct?.variants"
                :key="variant.variant_id"
                class="variant-row"
                :class="{ selected: selectedVariant?.variant_id === variant.variant_id, oos: variant.stock <= 0 }"
                @click="selectVariant(variant)"
              >
                <img :src="getImageUrl(variant.image_url || selectedProduct?.thumbnail)" class="var-img" onerror="this.src='/placeholder.jpg'" />
                <div class="var-info">
                  <div class="var-name">{{ variant.color }}<span v-if="variant.size"> · {{ variant.size }}</span></div>
                  <div class="var-price">{{ formatPrice(variant.price) }}</div>
                  <div class="var-stock" :class="{ low: variant.stock > 0 && variant.stock <= 5 }">
                    {{ variant.stock <= 0 ? 'Hết hàng' : `Còn ${variant.stock} sản phẩm` }}
                  </div>
                </div>
                <div v-if="selectedVariant?.variant_id === variant.variant_id" class="var-check">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
                </div>
                <div v-if="variant.stock <= 0" class="oos-label">Hết</div>
              </div>
            </div>
          </div>
          <div class="modal-foot">
            <button class="modal-cancel" @click="closeVariantModal">Hủy bỏ</button>
            <button class="modal-confirm" :disabled="!selectedVariant" @click="confirmVariantSelect">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
              Thêm vào giỏ hàng
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ===== MODAL: THANH TOÁN THÀNH CÔNG ===== -->
    <transition name="modal-pop">
      <div v-if="showCheckoutSuccess" class="modal-backdrop" @click.self="showCheckoutSuccess = false">
        <div class="modal-box success-modal">
          <div class="success-icon-wrap">
            <div class="success-ring"></div>
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
          </div>
          <h3 class="success-title">Thanh toán thành công!</h3>
          <div class="success-code">
            <span>Mã đơn hàng</span>
            <strong>{{ checkoutOrder?.order_code }}</strong>
          </div>
          <div class="success-actions">
            <button class="modal-cancel" @click="downloadReceiptPdf(checkoutOrder)" :disabled="isDownloadingPdf">
              <svg v-if="isDownloadingPdf" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin 0.8s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Xuất PDF
            </button>
            <button class="modal-confirm" @click="showCheckoutSuccess = false">
              Đóng & Bán tiếp
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ===== MODAL: QR APP ===== -->
    <transition name="modal-pop">
      <div v-if="showMobileScannerModal" class="modal-backdrop" @click.self="showMobileScannerModal = false">
        <div class="modal-box qr-modal">
          <div class="modal-head">
            <div>
              <p class="modal-eyebrow">Liên kết thiết bị</p>
              <h3 class="modal-title">Quét bằng App điện thoại</h3>
            </div>
            <button class="modal-close" @click="showMobileScannerModal = false">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="qr-body">
            <div class="qr-frame">
              <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent('pos_session:' + sessionId)}`" alt="QR" />
            </div>
            <p class="qr-hint">Dùng App nhân viên quét mã QR này để kết nối điện thoại làm máy quét barcode</p>
          </div>
          <div class="modal-foot">
            <button class="modal-confirm w-full" @click="showMobileScannerModal = false">Đóng</button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
@keyframes spin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

/* GLOBAL BOX SIZING RESET FOR POS SCOPE */
.pos-wrap, .pos-wrap *, .pos-wrap *::before, .pos-wrap *::after {
  box-sizing: border-box;
}

/* ============================================================
   ROOT LAYOUT
   ============================================================ */
.pos-wrap {
  display: flex;
  height: calc(100vh - 70px);
  background: var(--ocean-deepest, #f0f2f7);
  margin: -24px;
  font-family: var(--font-inter, 'Inter', sans-serif);
  overflow: hidden;
  width: calc(100% + 48px);
}

/* ============================================================
   LEFT PANEL
   ============================================================ */
.pos-left {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

/* Top bar */
.pos-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 24px;
  background: var(--card-bg, #fff);
  border-bottom: 1px solid var(--border-color, #e5e7eb);
  flex-shrink: 0;
  height: 60px;
}
.pos-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--text-main, #111827);
}
.pos-brand svg { color: var(--primary, #e63b6f); }
.pos-brand-accent { color: var(--primary, #e63b6f); }

.pos-topbar-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}
.pos-hint-key {
  font-size: 0.72rem;
  color: var(--text-muted, #6b7280);
}
.pos-hint-key kbd {
  background: var(--hover-bg, #f3f4f6);
  border: 1px solid var(--border-color, #d1d5db);
  border-radius: 4px;
  padding: 2px 6px;
  font-size: 0.7rem;
  font-family: inherit;
  box-shadow: 0 1px 1px rgba(0,0,0,0.05);
}

.scanner-pill {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 7px 14px;
  border-radius: 100px;
  border: 1.5px solid var(--border-color, #d1d5db);
  background: var(--card-bg, #fff);
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-muted, #6b7280);
  cursor: pointer;
  transition: all 0.25s;
}
.scanner-pill.active {
  border-color: transparent;
  background: linear-gradient(135deg, #e63b6f, #ff6b9d);
  color: #fff;
  box-shadow: 0 4px 14px rgba(230,59,111,.35);
}
.scanner-pill:hover:not(.active) { transform: translateY(-1px); }
.scanner-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #9ca3af;
  transition: background 0.25s;
}
.scanner-dot.on {
  background: #86efac;
  box-shadow: 0 0 6px #4ade80;
  animation: dot-pulse 1.5s ease infinite;
}
@keyframes dot-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* Input area */
.pos-input-area {
  padding: 16px 24px 12px;
  background: var(--card-bg, #fff);
  border-bottom: 1px solid var(--border-color, #e5e7eb);
  flex-shrink: 0;
}

/* Barcode row */
.barcode-row { margin-bottom: 10px; width: 100%; }
.barcode-field {
  display: flex;
  align-items: center;
  background: var(--hover-bg, #f9fafb);
  border: 1.5px solid rgba(230,59,111,.2);
  border-radius: 12px;
  overflow: hidden;
  position: relative;
  width: 100%;
  height: 44px;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.barcode-field:focus-within {
  border-color: var(--primary, #e63b6f);
  box-shadow: 0 0 0 3px rgba(230,59,111,.1);
  background: var(--card-bg, #fff);
}
.barcode-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  position: relative;
}
.barcode-icon-wrap svg { color: var(--primary, #e63b6f); transition: all .3s; }
.barcode-icon-wrap svg.scanning { color: #f59e0b; animation: pulse-scale .6s ease infinite alternate; }
@keyframes pulse-scale {
  from { transform: scale(1); }
  to   { transform: scale(1.2); }
}
.scan-ripple {
  position: absolute;
  inset: 0;
  border: 2px solid #f59e0b;
  border-radius: 50%;
  animation: ripple 1s ease-out infinite;
}
@keyframes ripple {
  0%   { transform: scale(.6); opacity: 1; }
  100% { transform: scale(1.8); opacity: 0; }
}
.barcode-input {
  flex: 1;
  min-width: 0;
  width: 100%;
  border: none;
  outline: none;
  background: transparent;
  padding: 0 10px;
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--text-main, #111827);
  height: 100%;
}
.barcode-input::placeholder { color: var(--text-light, #9ca3af); }
.barcode-btn {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: linear-gradient(135deg, #e63b6f, #ff6b9d);
  color: white;
  cursor: pointer;
  transition: opacity .2s;
  flex-shrink: 0;
}
.barcode-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.barcode-btn:not(:disabled):hover { opacity: 0.9; }

.scan-toast {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  padding: 9px 14px;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 600;
}
.scan-toast.success { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }
.scan-toast.error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }

/* Search row */
.search-row { display: flex; gap: 10px; align-items: center; width: 100%; }
.search-field {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--hover-bg, #f9fafb);
  border: 1.5px solid var(--border-color, #e5e7eb);
  border-radius: 12px;
  padding: 0 14px;
  height: 42px;
  transition: border-color .2s, box-shadow .2s;
}
.search-field:focus-within {
  border-color: var(--primary, #e63b6f);
  box-shadow: 0 0 0 3px rgba(230,59,111,.1);
  background: var(--card-bg, #fff);
}
.search-icon-svg { color: var(--text-light, #9ca3af); flex-shrink: 0; }
.search-input {
  flex: 1;
  min-width: 0;
  width: 100%;
  border: none;
  outline: none;
  background: transparent;
  padding: 0;
  font-size: 0.9rem;
  color: var(--text-main, #111827);
  height: 100%;
}
.search-input::placeholder { color: var(--text-light, #9ca3af); }
.search-spinner { color: var(--primary, #e63b6f); display: flex; flex-shrink: 0; }

.qr-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 16px;
  height: 42px;
  border: 1.5px solid var(--border-color, #e5e7eb);
  border-radius: 12px;
  background: var(--card-bg, #fff);
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-main, #374151);
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: all .2s;
}
.qr-btn:hover { border-color: var(--primary, #e63b6f); color: var(--primary, #e63b6f); transform: translateY(-1px); }

/* Product area */
.product-area {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
  min-height: 0;
}
.product-area::-webkit-scrollbar { width: 5px; }
.product-area::-webkit-scrollbar-thumb { background: var(--border-color, #d1d5db); border-radius: 10px; }

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  min-height: 260px;
  text-align: center;
  gap: 8px;
}
.empty-illustration { margin-bottom: 12px; color: var(--text-light, #9ca3af); }
.empty-title { font-size: 0.95rem; font-weight: 600; color: var(--text-muted, #6b7280); margin: 0; }
.empty-sub { font-size: 0.8rem; color: var(--text-light, #9ca3af); margin: 0; }

/* Product Grid */
.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 14px;
}
.product-card {
  background: var(--card-bg, #fff);
  border-radius: 12px;
  overflow: hidden;
  border: 1.5px solid var(--border-color, #e5e7eb);
  cursor: pointer;
  transition: all .25s;
}
.product-card:hover {
  border-color: var(--primary, #e63b6f);
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(230,59,111,.12);
}
.product-thumb {
  position: relative;
  padding-top: 85%;
  background: var(--hover-bg, #f9fafb);
  overflow: hidden;
}
.product-thumb img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: 10px;
  transition: transform .3s;
}
.product-card:hover .product-thumb img { transform: scale(1.05); }
.multi-badge {
  position: absolute;
  top: 7px;
  left: 7px;
  background: rgba(124,58,237,.9);
  color: white;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 100px;
  display: flex;
  align-items: center;
  gap: 3px;
}
.card-overlay {
  position: absolute;
  inset: 0;
  background: rgba(230,59,111,.08);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity .2s;
  color: var(--primary, #e63b6f);
}
.product-card:hover .card-overlay { opacity: 1; }
.product-meta { padding: 10px 12px 12px; }
.product-name {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-main, #111827);
  margin: 0 0 5px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.4;
}
.product-price {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--primary, #e63b6f);
}
.price-range { font-weight: 600; color: var(--text-muted, #6b7280); font-size: 0.78rem; }

/* ============================================================
   RIGHT PANEL
   ============================================================ */
.pos-right {
  width: 380px;
  min-width: 360px;
  background: var(--card-bg, #fff);
  border-left: 1px solid var(--border-color, #e5e7eb);
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  flex-shrink: 0;
}

/* Cart header */
.cart-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
  flex-shrink: 0;
  height: 60px;
}
.cart-head-left {
  display: flex;
  align-items: center;
  gap: 9px;
  color: var(--text-main, #111827);
}
.cart-head-left svg { color: var(--primary, #e63b6f); }
.cart-title { font-weight: 700; font-size: 0.95rem; }
.cart-badge {
  background: var(--primary, #e63b6f);
  color: #fff;
  font-size: 0.68rem;
  font-weight: 700;
  min-width: 20px;
  height: 20px;
  border-radius: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
}
.clear-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border: none;
  border-radius: 8px;
  background: #fef2f2;
  color: #ef4444;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: background .2s;
}
.clear-btn:hover:not(:disabled) { background: #fee2e2; }
.clear-btn:disabled { opacity: 0.35; cursor: not-allowed; }

/* Cart list */
.cart-list {
  flex: 1;
  overflow-y: auto;
  padding: 12px 16px;
  min-height: 0;
}
.cart-list::-webkit-scrollbar { width: 4px; }
.cart-list::-webkit-scrollbar-thumb { background: var(--border-color, #d1d5db); border-radius: 10px; }

.cart-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 48px 20px;
  text-align: center;
  color: var(--text-muted, #6b7280);
}
.cart-empty p { font-weight: 600; margin: 0; font-size: 0.9rem; }
.cart-empty small { font-size: 0.75rem; color: var(--text-light, #9ca3af); }

/* REDESIGNED CART ITEM (CLEAN GRID ALIGNMENT) */
.cart-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  margin-bottom: 8px;
  background: var(--hover-bg, #f9fafb);
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 12px;
  transition: all .2s ease;
  width: 100%;
}
.cart-item:hover {
  border-color: rgba(230,59,111,.25);
  background: var(--card-bg, #fff);
  box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.item-thumb {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid var(--border-color, #e5e7eb);
  flex-shrink: 0;
}
.item-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.item-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  width: 100%;
}
.item-name {
  font-weight: 600;
  font-size: 0.83rem;
  color: var(--text-main, #111827);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0;
}
.remove-btn {
  background: transparent;
  border: none;
  color: var(--text-light, #9ca3af);
  cursor: pointer;
  padding: 3px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: all .2s;
  flex-shrink: 0;
}
.remove-btn:hover {
  color: #ef4444;
  background: #fef2f2;
}
.item-tags {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}
.tag {
  font-size: 0.68rem;
  background: var(--card-bg, #fff);
  border: 1px solid var(--border-color, #e5e7eb);
  color: var(--text-muted, #6b7280);
  padding: 1px 6px;
  border-radius: 4px;
}
.item-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 2px;
  width: 100%;
}
.qty-wrap {
  display: flex;
  align-items: center;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 6px;
  overflow: hidden;
  background: var(--card-bg, #fff);
  flex-shrink: 0;
}
.qty-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border: none;
  background: transparent;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  transition: background .15s;
}
.qty-btn:hover { background: var(--hover-bg, #f3f4f6); }
.qty-val {
  min-width: 24px;
  text-align: center;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-main, #111827);
  border-left: 1px solid var(--border-color, #e5e7eb);
  border-right: 1px solid var(--border-color, #e5e7eb);
  line-height: 22px;
  padding: 0 4px;
}
.item-prices {
  display: flex;
  align-items: baseline;
  gap: 6px;
  flex-shrink: 0;
}
.item-unit {
  font-size: 0.7rem;
  color: var(--text-light, #9ca3af);
}
.item-total {
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--primary, #e63b6f);
}

/* List transition */
.list-enter-active, .list-leave-active { transition: all .25s ease; }
.list-enter-from { opacity: 0; transform: translateX(-10px); }
.list-leave-to   { opacity: 0; transform: translateX(10px); }

/* ============================================================
   CHECKOUT PANEL - STRICT INPUT ALIGNMENT
   ============================================================ */
.checkout-panel {
  border-top: 1px solid var(--border-color, #e5e7eb);
  padding: 16px;
  background: var(--hover-bg, #f9fafb);
  flex-shrink: 0;
  width: 100%;
}
.panel-section { margin-bottom: 12px; width: 100%; }
.section-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--text-muted, #6b7280);
  margin-bottom: 8px;
}

/* Customer inputs - FIXED GRID MINMAX OVERFLOW */
.customer-inputs {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  width: 100%;
}
.mt-6 { margin-top: 6px; }

.field-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--card-bg, #fff);
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 8px;
  padding: 0 10px;
  height: 38px;
  width: 100%;
  min-width: 0;
  transition: border-color .2s, box-shadow .2s;
}
.field-wrap:focus-within {
  border-color: var(--primary, #e63b6f);
  box-shadow: 0 0 0 2px rgba(230,59,111,.1);
}
.field-wrap.has-error {
  border-color: #ef4444 !important;
  box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15) !important;
}
.field-wrap svg { color: var(--text-light, #9ca3af); flex-shrink: 0; }
.field-wrap input {
  flex: 1;
  min-width: 0;
  width: 100%;
  border: none;
  outline: none;
  background: transparent;
  font-size: 0.8rem;
  color: var(--text-main, #111827);
  height: 100%;
  padding: 0;
}
.field-wrap input::placeholder { color: var(--text-light, #9ca3af); }
.check-icon { display: flex; color: #10b981; flex-shrink: 0; }

/* Payment methods */
.pay-methods {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  width: 100%;
}
.pay-opt {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 8px 4px;
  height: 54px;
  border: 1.5px solid var(--border-color, #e5e7eb);
  border-radius: 10px;
  cursor: pointer;
  background: var(--card-bg, #fff);
  transition: all .2s;
  text-align: center;
  min-width: 0;
  width: 100%;
}
.pay-opt input[type="radio"] { display: none; }
.pay-opt svg { color: var(--text-muted, #6b7280); transition: color .2s; flex-shrink: 0; }
.pay-opt span { font-size: 0.7rem; font-weight: 600; color: var(--text-muted, #6b7280); transition: color .2s; white-space: nowrap; }
.pay-opt:hover { border-color: rgba(230,59,111,.3); }
.pay-opt.active {
  border-color: var(--primary, #e63b6f);
  background: linear-gradient(135deg, rgba(230,59,111,.06), rgba(230,59,111,.1));
}
.pay-opt.active svg,
.pay-opt.active span { color: var(--primary, #e63b6f); }

/* Coupon row */
.coupon-row { display: flex; gap: 8px; align-items: stretch; width: 100%; height: 38px; }
.coupon-field {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 7px;
  background: var(--card-bg, #fff);
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 8px;
  padding: 0 10px;
  height: 100%;
  transition: border-color .2s;
}
.coupon-field:focus-within { border-color: var(--primary, #e63b6f); }
.coupon-field svg { color: var(--text-light, #9ca3af); flex-shrink: 0; }
.coupon-input {
  flex: 1;
  min-width: 0;
  width: 100%;
  border: none;
  outline: none;
  background: transparent;
  font-size: 0.8rem;
  color: var(--text-main, #111827);
  height: 100%;
  padding: 0;
}
.coupon-input::placeholder { color: var(--text-light, #9ca3af); }
.text-upper { text-transform: uppercase; }
.coupon-apply-btn {
  padding: 0 16px;
  height: 100%;
  background: linear-gradient(135deg, #e63b6f, #ff6b9d);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: opacity .2s;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.coupon-apply-btn:hover { opacity: 0.9; }
.coupon-remove-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 0 12px;
  height: 100%;
  background: #fef2f2;
  color: #ef4444;
  border: none;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: background .2s;
  flex-shrink: 0;
}
.coupon-remove-btn:hover { background: #fee2e2; }
.coupon-chip {
  margin-top: 7px;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  color: #047857;
  font-weight: 500;
}
.coupon-chip svg { color: #10b981; flex-shrink: 0; }

/* Totals */
.totals-block {
  border-top: 1px dashed var(--border-color, #e5e7eb);
  padding-top: 10px;
  margin-bottom: 12px;
  width: 100%;
}
.total-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
  font-size: 0.82rem;
  color: var(--text-muted, #6b7280);
}
.discount-line { align-items: center; }
.discount-field {
  display: flex;
  align-items: center;
  gap: 4px;
  background: var(--card-bg, #fff);
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 6px;
  padding: 2px 8px;
  height: 28px;
}
.d-symbol { font-size: 0.75rem; color: var(--text-light, #9ca3af); }
.discount-field input {
  border: none;
  outline: none;
  background: transparent;
  font-size: 0.8rem;
  color: var(--text-main, #111827);
  width: 80px;
  text-align: right;
  height: 100%;
  padding: 0;
}
.grand-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  background: linear-gradient(135deg, rgba(230,59,111,.06), rgba(230,59,111,.1));
  border: 1px solid rgba(230,59,111,.15);
  border-radius: 10px;
  margin-top: 8px;
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--text-main, #111827);
  width: 100%;
}
.grand-amount { font-size: 1.1rem; font-weight: 800; color: var(--primary, #e63b6f); }

/* Checkout button */
.checkout-btn {
  width: 100%;
  height: 46px;
  background: linear-gradient(135deg, #e63b6f, #ff6b9d);
  color: white;
  border: none;
  border-radius: 12px;
  font-size: 0.9rem;
  font-weight: 800;
  letter-spacing: .03em;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .25s;
  box-shadow: 0 4px 14px rgba(230,59,111,.35);
}
.checkout-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(230,59,111,.45);
}
.checkout-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

/* ============================================================
   MODALS
   ============================================================ */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}
.modal-box {
  background: var(--card-bg, #fff);
  border-radius: 18px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 25px 50px rgba(0,0,0,.25);
  overflow: hidden;
}
.modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 20px 22px 16px;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
}
.modal-eyebrow {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--primary, #e63b6f);
  margin: 0 0 4px;
}
.modal-title { font-size: 1rem; font-weight: 700; color: var(--text-main, #111827); margin: 0; }
.modal-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: var(--hover-bg, #f3f4f6);
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  transition: background .2s;
  flex-shrink: 0;
}
.modal-close:hover { background: var(--border-color, #e5e7eb); }
.modal-body { padding: 16px 22px; max-height: 55vh; overflow-y: auto; }
.modal-body::-webkit-scrollbar { width: 4px; }
.modal-body::-webkit-scrollbar-thumb { background: var(--border-color, #d1d5db); border-radius: 10px; }
.modal-foot {
  display: flex;
  gap: 10px;
  padding: 16px 22px;
  border-top: 1px solid var(--border-color, #e5e7eb);
}
.modal-cancel {
  flex: 1;
  padding: 11px;
  border: 1.5px solid var(--border-color, #e5e7eb);
  border-radius: 10px;
  background: var(--card-bg, #fff);
  color: var(--text-muted, #6b7280);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
.modal-cancel:hover:not(:disabled) { border-color: var(--text-muted, #6b7280); }
.modal-confirm {
  flex: 2;
  padding: 11px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, #e63b6f, #ff6b9d);
  color: white;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  transition: all .2s;
  box-shadow: 0 4px 12px rgba(230,59,111,.25);
}
.modal-confirm:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.modal-confirm:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
.w-full { width: 100%; flex: 1; }

/* Variant list */
.variants-list { display: flex; flex-direction: column; gap: 8px; }
.variant-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border: 1.5px solid var(--border-color, #e5e7eb);
  border-radius: 12px;
  cursor: pointer;
  transition: all .2s;
  position: relative;
}
.variant-row:hover:not(.oos) { border-color: var(--primary, #e63b6f); background: rgba(230,59,111,.03); }
.variant-row.selected { border-color: var(--primary, #e63b6f); background: rgba(230,59,111,.06); }
.variant-row.oos { opacity: 0.5; cursor: not-allowed; }
.var-img { width: 52px; height: 52px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color, #e5e7eb); flex-shrink: 0; }
.var-info { flex: 1; min-width: 0; }
.var-name { font-weight: 600; font-size: 0.85rem; color: var(--text-main, #111827); }
.var-price { font-weight: 700; color: var(--primary, #e63b6f); font-size: 0.85rem; margin-top: 2px; }
.var-stock { font-size: 0.75rem; color: var(--text-muted, #6b7280); margin-top: 2px; }
.var-stock.low { color: #f59e0b; }
.var-check {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--primary, #e63b6f);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
}
.oos-label {
  position: absolute;
  top: 8px;
  right: 8px;
  font-size: 0.65rem;
  font-weight: 700;
  background: #fef2f2;
  color: #ef4444;
  padding: 2px 6px;
  border-radius: 4px;
}

/* Success modal */
.success-modal { max-width: 360px; text-align: center; padding: 32px 24px; }
.success-icon-wrap {
  position: relative;
  width: 72px;
  height: 72px;
  margin: 0 auto 20px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.success-ring {
  position: absolute;
  inset: 0;
  border: 2px solid #6ee7b7;
  border-radius: 50%;
  animation: ring-pulse 2s ease infinite;
}
@keyframes ring-pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.1); opacity: 0.6; }
}
.success-title { font-size: 1.15rem; font-weight: 800; color: var(--text-main, #111827); margin: 0 0 12px; }
.success-code {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-bottom: 24px;
  padding: 12px 20px;
  background: var(--hover-bg, #f9fafb);
  border-radius: 10px;
}
.success-code span { font-size: 0.75rem; color: var(--text-muted, #6b7280); }
.success-code strong { font-size: 1.1rem; color: var(--primary, #e63b6f); font-weight: 800; }
.success-actions { display: flex; gap: 10px; }

/* QR modal */
.qr-modal { max-width: 360px; }
.qr-body { padding: 20px 22px; text-align: center; }
.qr-frame {
  display: inline-block;
  padding: 12px;
  background: #fff;
  border: 2px solid var(--border-color, #e5e7eb);
  border-radius: 14px;
  margin-bottom: 14px;
}
.qr-frame img { display: block; border-radius: 6px; }
.qr-hint { font-size: 0.8rem; color: var(--text-muted, #6b7280); margin: 0; line-height: 1.5; }

/* Modal transitions */
.modal-pop-enter-active { transition: all .25s cubic-bezier(.34,1.56,.64,1); }
.modal-pop-leave-active { transition: all .2s ease; }
.modal-pop-enter-from, .modal-pop-leave-to { opacity: 0; transform: scale(.92); }

/* Slide-down for barcode area */
.slide-down-enter-active, .slide-down-leave-active { transition: all .25s ease; overflow: hidden; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; max-height: 0; margin-bottom: 0; }
.slide-down-enter-to, .slide-down-leave-from { max-height: 120px; }

/* fade-up for scan toast */
.fade-up-enter-active { transition: all .25s ease; }
.fade-up-leave-active { transition: all .2s ease; }
.fade-up-enter-from, .fade-up-leave-to { opacity: 0; transform: translateY(6px); }
</style>
