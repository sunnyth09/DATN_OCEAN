<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import api from '@/axios';
import { Toast } from 'bootstrap';

const toastData = ref({ message: '', type: 'success' });
const showToastNotify = (message, type = 'success') => {
  toastData.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('posToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 3000 }).show();
  });
};
const toast = {
  success: (msg) => showToastNotify(msg, 'success'),
  error: (msg) => showToastNotify(msg, 'danger'),
};

const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleString('vi-VN', { hour: '2-digit', minute:'2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getImageUrl = (path) => {
  if (!path) return '/placeholder.jpg';
  if (path.startsWith('http')) return path;
  return `${import.meta.env.VITE_BASE_URL || 'http://localhost:8383'}/storage/${path}`;
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
    // Only one variant, add directly
    addToCart(product.variants[0], product);
  } else {
    // Show variant selector
    selectedProduct.value = product;
    selectedVariant.value = null; // Reset selection
    showVariantModal.value = true;
  }
};

const closeVariantModal = () => {
    showVariantModal.value = false;
    selectedProduct.value = null;
    selectedVariant.value = null;
};

const confirmVariantSelect = () => {
    if(!selectedVariant.value) {
        return;
    }
    addToCart(selectedVariant.value, selectedProduct.value);
    closeVariantModal();
}

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

const clearCart = () => {
  cartItems.value = [];
  removeCoupon();
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

// ================== CUSTOMER SEARCH (LOYALTY) ==================
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
       // match absolute phone
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
}

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

// Checkout success modal state
const checkoutOrder = ref(null);
const showCheckoutSuccess = ref(false);

const handleCheckout = async () => {
  if (cartItems.value.length === 0) {
    toast.error('Giỏ hàng trống!');
    return;
  }
  
  isCheckingOut.value = true;
  
  try {
    const payload = {
      items: cartItems.value.map(item => ({
        variant_id: item.variant_id,
        quantity: item.quantity
      })),
      user_id: customerId.value,
      customer_name: customerName.value,
      customer_phone: customerPhone.value,
      payment_method: paymentMethod.value,
      note: note.value,
      discount_amount: discountAmount.value
    };
    
    const res = await api.post('/admin/pos/checkout', payload);
    
    if (res.data.status === 'success') {
      const createdOrder = res.data.data;
      
      // Show checkout success modal
      checkoutOrder.value = createdOrder;
      showCheckoutSuccess.value = true;

      // Reset form
      cartItems.value = [];
      customerName.value = '';
      customerPhone.value = '';
      customerId.value = null;
      customerFound.value = false;
      note.value = '';
      removeCoupon();
      searchQuery.value = '';
      searchResults.value = [];
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
const scanResult = ref(null); // { type: 'success' | 'error', message: '' }
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

// ================== MOBILE SCANNER SESSION ==================
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
      
      // Tự động thêm vào giỏ hàng
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

// Lắng nghe sự kiện keyboard global để hỗ trợ scanner hardware
let barcodeBuffer = '';
let barcodeTimeout = null;

const handleGlobalKeydown = (e) => {
  // Nếu đang focus vào input khác (search, form...) thì bỏ qua
  const activeEl = document.activeElement;
  const isOtherInput = activeEl && 
    (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA') && 
    activeEl !== barcodeInputRef.value;
  
  if (isOtherInput || !isScannerActive.value) return;
  
  // Scanner thường gửi ký tự rất nhanh, kết thúc bằng Enter
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
    }, 200); // Scanner gửi toàn bộ trong vòng ~100ms
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
  <div class="pos-container">
    <div class="row m-0 h-100">
      
      <!-- ================== CỘT TRÁI: TÌM KIẾM SẢN PHẨM ================== -->
      <div class="col-lg-7 col-xl-8 pos-left">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h4 class="mb-0 fw-bold">POS - Bán hàng</h4>
          <button 
            class="btn scanner-toggle-btn" 
            :class="{ 'active': isScannerActive }"
            @click="toggleScanner"
          >
            <i class="fas fa-barcode me-2"></i>
            <span>{{ isScannerActive ? 'Scanner: BẬT' : 'Scanner: TẮT' }}</span>
          </button>
        </div>

        <!-- Barcode Scanner Input -->
        <div v-if="isScannerActive" class="barcode-scanner-box mb-3">
          <div class="scanner-inner">
            <div class="scanner-icon-wrapper">
              <i class="fas fa-barcode scanner-icon" :class="{ 'scanning': isScanning }"></i>
              <div v-if="isScanning" class="scanner-pulse"></div>
            </div>
            <div class="scanner-input-group">
              <input
                ref="barcodeInputRef"
                type="text"
                v-model="barcodeValue"
                @keyup.enter="onBarcodeEnter"
                class="form-control barcode-input"
                placeholder="Quét barcode hoặc nhập mã sản phẩm..."
                autocomplete="off"
                :disabled="isScanning"
              >
              <button 
                class="btn btn-primary barcode-submit-btn" 
                @click="onBarcodeEnter" 
                :disabled="isScanning || !barcodeValue.trim()"
              >
                <i v-if="isScanning" class="fas fa-spinner fa-spin"></i>
                <i v-else class="fas fa-search"></i>
              </button>
            </div>
            <small class="scanner-hint">
              <i class="fas fa-mobile-alt me-1"></i>
              Dùng điện thoại quét mã hoặc nhập barcode rồi nhấn Enter
            </small>
          </div>
          <!-- Scan Result Feedback -->
          <transition name="slide-fade">
            <div v-if="scanResult" class="scan-result" :class="scanResult.type">
              <i :class="scanResult.type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
              <span>{{ scanResult.message }}</span>
            </div>
          </transition>
        </div>
        
        <!-- Thanh Tìm kiếm, Di chuyển nút QR lên đây chung hàng với Search -->
        <div class="d-flex align-items-center gap-2 mb-4">
          <div class="search-box position-relative flex-grow-1">
            <i class="fas fa-search search-icon"></i>
            <input 
              type="text" 
              v-model="searchQuery" 
              @input="onSearchInput"
              class="form-control form-control-lg ps-5" 
              placeholder="Tìm kiếm sản phẩm theo tên, SKU..."
            >
            <div v-if="isSearching" class="spinner-border spinner-border-sm text-primary position-absolute top-50 end-0 translate-middle-y me-3" role="status"></div>
          </div>
          <button class="btn btn-outline-primary" style="height: 48px; border-radius: 12px; font-weight: 600" @click="showMobileScannerModal = true">
             <i class="fas fa-qrcode me-1"></i> QR App
          </button>
        </div>
        
        <!-- Kết quả tìm kiếm -->
        <div class="search-results">
            <div v-if="searchResults.length === 0 && searchQuery.length > 0 && !isSearching" class="text-center py-5 text-muted">
                <i class="fas fa-box-open fa-3x mb-3 text-light"></i>
                <p>Không tìm thấy sản phẩm nào</p>
            </div>
            
            <div v-else-if="searchResults.length > 0" class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3">
              <div v-for="product in searchResults" :key="product.product_id" class="col">
                <div class="product-card" @click="selectProduct(product)">
                  <div class="product-img-wrapper">
                      <img :src="getImageUrl(product.thumbnail)" :alt="product.name" class="product-img" onerror="this.src='/placeholder.jpg'">
                      <span v-if="product.variants.length > 1" class="variant-badge">Nhiều loại</span>
                  </div>
                  <div class="product-info">
                    <h6 class="product-title" :title="product.name">{{ product.name }}</h6>
                    <div class="product-price text-primary fw-bold">
                        <template v-if="product.variants.length === 1">
                            {{ formatPrice(product.variants[0].price) }}
                        </template>
                        <template v-else>
                            Xem tuỳ chọn
                        </template>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div v-else class="empty-state">
                <i class="fas fa-shopping-basket fa-4x text-light mb-3"></i>
                <h5 class="text-muted">Nhập từ khóa tìm kiếm hoặc quét SKU để thêm sản phẩm</h5>
            </div>
        </div>
      </div>
      
      <!-- ================== CỘT PHẢI: GIỎ HÀNG & THANH TOÁN ================== -->
      <div class="col-lg-5 col-xl-4 pos-right">
        
        <!-- Cart Header -->
        <div class="cart-header">
          <div class="cart-header-left">
            <i class="fas fa-shopping-bag cart-header-icon"></i>
            <h5 class="cart-header-title">Đơn hiện tại</h5>
            <span v-if="cartItems.length" class="cart-count-badge">{{ cartItems.length }}</span>
          </div>
          <button class="btn-clear-cart" @click="clearCart" :disabled="cartItems.length === 0">
            <i class="fas fa-trash-alt me-1"></i> Xóa hết
          </button>
        </div>
        
        <!-- Cart Items -->
        <div class="cart-items-scroll">
          <div v-if="cartItems.length === 0" class="empty-cart-state">
            <i class="fas fa-shopping-cart"></i>
            <p>Giỏ hàng trống</p>
            <small>Quét barcode hoặc tìm sản phẩm để thêm</small>
          </div>

          <div 
            v-for="item in cartItems" 
            :key="item.variant_id"
            class="cart-item-card"
          >
            <img :src="getImageUrl(item.image_url)" class="cart-item-img" onerror="this.src='/placeholder.jpg'">
            <div class="cart-item-info">
              <div class="cart-item-name" :title="item.product_name">{{ item.product_name }}</div>
              <div class="cart-item-variant">
                <span class="variant-tag">{{ item.color }} - {{ item.size }}</span>
                <span class="unit-price">{{ formatPrice(item.price) }}</span>
              </div>
            </div>
            <div class="cart-item-actions">
              <div class="qty-control">
                <button class="qty-btn" @click="decreaseQuantity(item)">−</button>
                <input type="text" readonly :value="item.quantity" class="qty-input">
                <button class="qty-btn" @click="increaseQuantity(item)">+</button>
              </div>
              <div class="cart-item-bottom">
                <span class="line-total">{{ formatPrice(item.price * item.quantity) }}</span>
                <button class="btn-remove-item" @click="removeFromCart(item)" title="Xoá">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Checkout Panel -->
        <div class="checkout-panel">
          <!-- Customer Info -->
          <div class="checkout-section">
            <div class="customer-row">
              <div class="input-icon-wrapper">
                <i class="fas fa-user"></i>
                <input type="text" v-model="customerName" placeholder="Tên khách hàng">
              </div>
              <div class="input-icon-wrapper">
                <i class="fas fa-phone-alt"></i>
                <input type="text" v-model="customerPhone" @input="onPhoneInput" placeholder="Số điện thoại">
                <span v-if="customerFound" class="verified-badge"><i class="fas fa-check-circle"></i></span>
                <span v-if="isSearchingCustomer" class="spinner-border spinner-border-sm text-primary loading-spinner" role="status"></span>
              </div>
            </div>
            <div class="input-icon-wrapper full-w">
              <i class="fas fa-sticky-note"></i>
              <input type="text" v-model="note" placeholder="Ghi chú đơn hàng...">
            </div>
          </div>

          <!-- Payment Methods -->
          <div class="checkout-section">
            <div class="payment-methods">
              <label class="pay-method" :class="{'active': paymentMethod === 'pos_cash'}">
                <input type="radio" value="pos_cash" v-model="paymentMethod">
                <i class="fas fa-money-bill-wave"></i>
                <span>Tiền mặt</span>
              </label>
              <label class="pay-method" :class="{'active': paymentMethod === 'pos_transfer'}">
                <input type="radio" value="pos_transfer" v-model="paymentMethod">
                <i class="fas fa-university"></i>
                <span>Chuyển khoản</span>
              </label>
              <label class="pay-method" :class="{'active': paymentMethod === 'pos_card'}">
                <input type="radio" value="pos_card" v-model="paymentMethod">
                <i class="fas fa-credit-card"></i>
                <span>Quẹt thẻ</span>
              </label>
            </div>
          </div>

          <!-- Coupon -->
          <div class="checkout-section">
            <div class="coupon-row">
              <div class="coupon-input-wrap">
                <i class="fas fa-tag"></i>
                <input type="text" class="text-uppercase" placeholder="Nhập mã giảm giá..." v-model="couponCode" @keyup.enter="applyCoupon">
              </div>
              <button class="btn-coupon apply" @click="applyCoupon" v-if="!appliedCoupon">Áp dụng</button>
              <button class="btn-coupon remove" @click="removeCoupon" v-else>
                <i class="fas fa-times me-1"></i>Huỷ
              </button>
            </div>
            <div v-if="appliedCoupon" class="coupon-applied">
              <i class="fas fa-check-circle"></i> Mã <strong>{{ appliedCoupon.code }}</strong> đã áp dụng
            </div>
          </div>

          <!-- Totals -->
          <div class="checkout-totals">
            <div class="total-row">
              <span>Tạm tính</span>
              <span class="total-value">{{ formatPrice(subtotal) }}</span>
            </div>
            <div class="total-row discount">
              <span>Giảm giá</span>
              <div class="discount-input-wrap">
                <span class="discount-currency">₫</span>
                <input type="number" v-model.number="discountAmount" min="0" placeholder="0">
              </div>
            </div>
            <div class="total-row grand">
              <span>Khách phải trả</span>
              <span class="grand-total-value">{{ formatPrice(grandTotal) }}</span>
            </div>
          </div>

          <!-- Checkout Button -->
          <button class="btn-checkout" @click="handleCheckout" :disabled="isCheckingOut || cartItems.length === 0">
            <span v-if="isCheckingOut" class="spinner-border spinner-border-sm me-2" role="status"></span>
            <i v-else class="fas fa-cash-register me-2"></i>
            Thanh toán {{ cartItems.length > 0 ? formatPrice(grandTotal) : '' }}
          </button>
        </div>
      </div>
      
    </div>
  </div>

  <!-- MODAL CHỌN BIẾN THỂ (VUE DRIVEN) -->
  <div v-if="showVariantModal" class="vue-modal-backdrop">
    <div class="vue-modal-container">
      <div class="vue-modal-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0 text-dark">{{ selectedProduct?.name }}</h5>
          <button type="button" class="btn-close" @click="closeVariantModal"></button>
        </div>
        
        <div class="vue-modal-body">
            <p class="text-muted mb-3">Vui lòng chọn kiểu phân loại:</p>
            <div class="variants-grid">
                <div 
                    v-for="variant in selectedProduct?.variants" 
                    :key="variant.variant_id"
                    class="variant-item"
                    :class="{ 
                        'is-selected': selectedVariant?.variant_id === variant.variant_id,
                        'is-out-of-stock': variant.stock <= 0
                    }"
                    @click="selectVariant(variant)"
                >
                    <div class="d-flex align-items-center">
                        <img :src="getImageUrl(variant.image_url || selectedProduct?.thumbnail)" alt="" class="variant-img me-3" onerror="this.src='/placeholder.jpg'">
                        <div>
                            <div class="fw-bold mb-1 text-dark">{{ variant.color }} <span v-if="variant.size">- {{ variant.size }}</span></div>
                            <div class="text-primary fw-bold">{{ formatPrice(variant.price) }}</div>
                            <div class="text-muted small">Kho: {{ variant.stock }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
          <button type="button" class="btn btn-light me-2" @click="closeVariantModal">Hủy</button>
          <button type="button" class="btn btn-primary px-4" :disabled="!selectedVariant" @click="confirmVariantSelect">Thêm vào giỏ</button>
        </div>
      </div>
    </div>
</div>

  <!-- Checkout Success Modal -->
  <div v-if="showCheckoutSuccess" class="vue-modal-backdrop">
    <div class="vue-modal-container">
      <div class="vue-modal-content" style="max-width:400px;text-align:center;">
        <div style="margin-bottom:20px;">
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h5 class="fw-bold mb-2">Thanh toán thành công!</h5>
        <p class="text-muted mb-3">Mã đơn hàng: <strong class="text-primary">{{ checkoutOrder?.order_code }}</strong></p>
        <div class="d-flex justify-content-center gap-2">
          <button class="btn btn-outline-primary" @click="downloadReceiptPdf(checkoutOrder)" :disabled="isDownloadingPdf">
            <i v-if="isDownloadingPdf" class="fas fa-spinner fa-spin me-1"></i>
            <i v-else class="fas fa-file-pdf me-1"></i>
            Xuất PDF
          </button>
          <button class="btn btn-primary" @click="showCheckoutSuccess = false">Đóng</button>
        </div>
      </div>
    </div>
  </div>



  <!-- Mobile APP QR Modal -->
  <div v-if="showMobileScannerModal" class="vue-modal-backdrop">
    <div class="vue-modal-container">
      <div class="vue-modal-content" style="max-width:350px;text-align:center;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0 text-dark">QUÉT BẰNG APP ĐIỆN THOẠI</h6>
          <button type="button" class="btn-close" @click="showMobileScannerModal = false"></button>
        </div>
        <div class="p-3">
          <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent('pos_session:' + sessionId)}`" />
          <p class="small text-muted mt-3 mb-0">Dùng App nhân viên quét mã này để liên kết làm máy quét barcode</p>
        </div>
        <div class="mt-3">
          <button class="btn btn-outline-primary w-100" @click="showMobileScannerModal = false">Đóng</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap Toast -->
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
    <div class="toast align-items-center border-0" :class="toastData.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="posToast" role="alert">
      <div class="d-flex">
        <div class="toast-body">{{ toastData.message }}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  </div>

</template>

<style scoped>
.pos-container {
  height: calc(100vh - 70px);
  background-color: var(--ocean-deepest, #f4f6f9);
  overflow: hidden;
  margin: -24px; /* remove padding from AdminLayout */
}

/* LEFT COLUMN */
.pos-left {
  background-color: var(--ocean-deepest, #f4f6f9);
  padding: 24px;
  display: flex;
  flex-direction: column;
  height: 100%;
}

/* Scanner Toggle Button */
.scanner-toggle-btn {
  background: var(--card-bg, white);
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--border-color, #e2e8f0);
  color: var(--text-muted, #64748b);
  font-size: 0.85rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  transition: all 0.3s ease;
}
.scanner-toggle-btn.active {
  background: linear-gradient(135deg, #FF6B9D, #E63B6F);
  color: white;
  border-color: transparent;
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.35);
}
.scanner-toggle-btn:hover {
  transform: translateY(-1px);
}

/* Barcode Scanner Box */
.barcode-scanner-box {
  background: linear-gradient(135deg, #FFF0F3, rgba(230, 59, 111, 0.08));
  border: 2px solid #bae6fd;
  border-radius: 14px;
  padding: 16px 20px;
  position: relative;
  overflow: hidden;
}
.barcode-scanner-box::before {
  content: '';
  position: absolute;
  top: -2px;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, #FF6B9D, #E63B6F, #FF6B9D);
  background-size: 200% 100%;
  animation: scannerLine 2s linear infinite;
}
@keyframes scannerLine {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.scanner-inner {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.scanner-icon-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  flex-shrink: 0;
}
.scanner-icon {
  font-size: 1.8rem;
  color: #E63B6F;
  transition: all 0.3s;
}
.scanner-icon.scanning {
  color: #f59e0b;
  animation: pulse-scale 0.6s ease infinite alternate;
}
@keyframes pulse-scale {
  from { transform: scale(1); }
  to { transform: scale(1.15); }
}
.scanner-pulse {
  position: absolute;
  width: 100%;
  height: 100%;
  border: 2px solid #f59e0b;
  border-radius: 50%;
  animation: ripple 1s ease-out infinite;
}
@keyframes ripple {
  0% { transform: scale(0.8); opacity: 1; }
  100% { transform: scale(1.8); opacity: 0; }
}

.scanner-input-group {
  flex: 1;
  display: flex;
  min-width: 200px;
}
.barcode-input {
  border-radius: 10px 0 0 10px !important;
  border: 2px solid #94a3b8;
  border-right: none;
  font-size: 1.05rem;
  font-weight: 500;
  padding: 10px 14px;
  letter-spacing: 0.5px;
  transition: border-color 0.3s;
}
.barcode-input:focus {
  border-color: #E63B6F;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
}
.barcode-submit-btn {
  border-radius: 0 10px 10px 0 !important;
  padding: 10px 16px;
  font-size: 1.1rem;
}

.scanner-hint {
  color: #64748b;
  font-size: 0.78rem;
  width: 100%;
  padding-left: 62px;
  margin-top: -4px;
}

/* Scan Result Feedback */
.scan-result {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  padding: 10px 16px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.9rem;
}
.scan-result.success {
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
  color: #047857;
  border: 1px solid #6ee7b7;
}
.scan-result.error {
  background: linear-gradient(135deg, #fef2f2, #fee2e2);
  color: #b91c1c;
  border: 1px solid #fca5a5;
}
.scan-result i {
  font-size: 1.1rem;
}

/* Slide-fade transition */
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}
.slide-fade-leave-active {
  transition: all 0.2s ease-in;
}
.slide-fade-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}
.slide-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.search-icon {
  position: absolute;
  top: 50%;
  left: 20px;
  transform: translateY(-50%);
  color: #a0aec0;
  font-size: 1.2rem;
  z-index: 10;
}

.form-control-lg {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.search-results {
  flex: 1;
  overflow-y: auto;
  padding-right: 8px;
  min-height: 0;
}

/* Tùy chỉnh thanh scroll */
.search-results::-webkit-scrollbar { width: 6px; }
.search-results::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

.product-card {
  background: var(--card-bg, white);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0,0,0,0.04);
  cursor: pointer;
  transition: all 0.2s ease;
  height: 100%;
  border: 2px solid transparent;
}

.product-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
  border-color: var(--border-color, rgba(230, 59, 111, 0.08));
}

.product-img-wrapper {
  position: relative;
  padding-top: 100%; /* 1:1 Aspect ratio */
  background: var(--hover-bg, #f8fafc);
}

.product-img {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: 10px;
}

.variant-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(14, 165, 233, 0.9);
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 600;
}

.product-info {
  padding: 12px;
}

.product-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 6px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.4;
  height: 2.8em;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    opacity: 0.6;
}

/* RIGHT COLUMN */
.pos-right {
  background-color: var(--card-bg, white);
  border-left: 1px solid var(--border-color, #e2e8f0);
  display: flex;
  flex-direction: column;
  height: 100%;
}

/* Cart Header */
.cart-header {
  padding: 16px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  flex-shrink: 0;
}
.cart-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.cart-header-icon {
  font-size: 1.2rem;
  color: #E63B6F;
}
.cart-header-title {
  margin: 0;
  font-weight: 700;
  font-size: 1.05rem;
  color: var(--text-main, #102a43);
}
.cart-count-badge {
  background: #E63B6F;
  color: white;
  font-size: 0.7rem;
  font-weight: 700;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-clear-cart {
  background: #fef2f2;
  color: #ef4444;
  border: none;
  font-size: 0.8rem;
  font-weight: 600;
  padding: 6px 14px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-clear-cart:hover:not(:disabled) {
  background: #fee2e2;
}
.btn-clear-cart:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Cart Items Scroll */
.cart-items-scroll {
  flex: 1;
  overflow-y: auto;
  padding: 12px 16px;
  min-height: 0;
}
.cart-items-scroll::-webkit-scrollbar { width: 4px; }
.cart-items-scroll::-webkit-scrollbar-thumb { background: var(--ocean-mid, #cbd5e1); border-radius: 10px; }

.empty-cart-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 20px;
  text-align: center;
}
.empty-cart-state i {
  font-size: 2.5rem;
  color: var(--ocean-mid, #b3e0f2);
  margin-bottom: 12px;
}
.empty-cart-state p {
  font-weight: 600;
  color: var(--text-muted, #627d98);
  margin-bottom: 4px;
}
.empty-cart-state small {
  color: var(--text-light, #9fb3c8);
  font-size: 0.8rem;
}

/* Cart Item Card */
.cart-item-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  margin-bottom: 8px;
  background: var(--hover-bg, #f8fafc);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 10px;
  transition: all 0.2s;
}
.cart-item-card:hover {
  border-color: var(--ocean-mid, #b3e0f2);
}
.cart-item-img {
  width: 44px;
  height: 44px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid var(--border-color, #e2e8f0);
  flex-shrink: 0;
}
.cart-item-info {
  flex: 1;
  min-width: 0;
}
.cart-item-name {
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--text-main, #102a43);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.3;
}
.cart-item-variant {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 2px;
}
.variant-tag {
  font-size: 0.7rem;
  color: var(--text-muted, #627d98);
  background: var(--card-bg, white);
  border: 1px solid var(--border-color, #e2e8f0);
  padding: 1px 6px;
  border-radius: 4px;
}
.unit-price {
  font-size: 0.72rem;
  color: var(--text-light, #9fb3c8);
}
.cart-item-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  flex-shrink: 0;
}
.cart-item-bottom {
  display: flex;
  align-items: center;
  gap: 8px;
}
.line-total {
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--coral, #ef5350);
}
.btn-remove-item {
  background: none;
  border: none;
  color: var(--text-light, #9fb3c8);
  cursor: pointer;
  padding: 2px;
  font-size: 0.75rem;
  transition: color 0.2s;
}
.btn-remove-item:hover {
  color: var(--coral, #ef5350);
}

/* Qty Control */
.qty-control {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--border-color, #cbd5e1);
  border-radius: 8px;
  overflow: hidden;
  background: var(--card-bg, white);
}
.qty-btn {
  background: transparent;
  border: none;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1rem;
  color: var(--text-muted, #475569);
  cursor: pointer;
  transition: background 0.15s;
}
.qty-btn:hover {
  background: var(--hover-bg, #e6f4fa);
}
.qty-input {
  width: 32px;
  height: 28px;
  border: none;
  border-left: 1px solid var(--border-color, #cbd5e1);
  border-right: 1px solid var(--border-color, #cbd5e1);
  text-align: center;
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--text-main, #0f172a);
  background: transparent;
}

/* Checkout Panel */
.checkout-panel {
  border-top: 1px solid var(--border-color, #e2e8f0);
  padding: 14px 16px 16px;
  background: var(--hover-bg, #f8fafc);
  flex-shrink: 0;
}

.checkout-section {
  margin-bottom: 12px;
}
.checkout-section:last-of-type {
  margin-bottom: 0;
}

/* Customer Input Row */
.customer-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-bottom: 8px;
}

.input-icon-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}
.input-icon-wrapper.full-w {
  width: 100%;
}
.input-icon-wrapper i {
  position: absolute;
  left: 10px;
  color: var(--text-light, #9fb3c8);
  font-size: 0.8rem;
  z-index: 1;
}
.input-icon-wrapper input {
  width: 100%;
  padding: 8px 10px 8px 32px;
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 8px;
  font-size: 0.82rem;
  color: var(--text-main, #102a43);
  background: var(--card-bg, white);
  transition: border-color 0.2s;
  outline: none;
}
.input-icon-wrapper input:focus {
  border-color: #E63B6F;
  box-shadow: 0 0 0 2px rgba(230, 59, 111, 0.1);
}
.input-icon-wrapper input::placeholder {
  color: var(--text-light, #9fb3c8);
}

.verified-badge {
  position: absolute;
  right: 10px;
  color: #10b981;
  font-size: 0.9rem;
}
.loading-spinner {
  position: absolute;
  right: 10px;
}

/* Payment Methods */
.payment-methods {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.pay-method {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 10px 6px;
  border: 1.5px solid var(--border-color, #e2e8f0);
  border-radius: 10px;
  cursor: pointer;
  background: var(--card-bg, white);
  transition: all 0.2s;
  text-align: center;
}
.pay-method input[type="radio"] {
  display: none;
}
.pay-method i {
  font-size: 1.1rem;
  color: var(--text-muted, #627d98);
  transition: color 0.2s;
}
.pay-method span {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-muted, #627d98);
  transition: color 0.2s;
}
.pay-method:hover {
  border-color: var(--ocean-mid, #b3e0f2);
}
.pay-method.active {
  border-color: #E63B6F;
  background: linear-gradient(135deg, rgba(230, 59, 111, 0.06), rgba(230, 59, 111, 0.1));
}
.pay-method.active i,
.pay-method.active span {
  color: #E63B6F;
}

/* Coupon */
.coupon-row {
  display: flex;
  gap: 8px;
}
.coupon-input-wrap {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
}
.coupon-input-wrap i {
  position: absolute;
  left: 10px;
  color: var(--text-light, #9fb3c8);
  font-size: 0.8rem;
}
.coupon-input-wrap input {
  width: 100%;
  padding: 7px 10px 7px 30px;
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 8px;
  font-size: 0.8rem;
  color: var(--text-main, #102a43);
  background: var(--card-bg, white);
  outline: none;
}
.coupon-input-wrap input:focus {
  border-color: #E63B6F;
}
.btn-coupon {
  padding: 7px 14px;
  border: none;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}
.btn-coupon.apply {
  background: #E63B6F;
  color: white;
}
.btn-coupon.apply:hover {
  background: var(--ocean-bright, #03a9f4);
}
.btn-coupon.remove {
  background: #fef2f2;
  color: #ef4444;
}
.btn-coupon.remove:hover {
  background: #fee2e2;
}
.coupon-applied {
  margin-top: 6px;
  font-size: 0.78rem;
  color: #10b981;
  font-weight: 500;
}
.coupon-applied i {
  margin-right: 4px;
}

/* Checkout Totals */
.checkout-totals {
  padding: 12px 0 14px;
  border-top: 1px dashed var(--border-color, #e2e8f0);
  margin-top: 12px;
}
.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
  font-size: 0.88rem;
  color: var(--text-muted, #627d98);
}
.total-value {
  font-weight: 600;
  color: var(--text-main, #102a43);
}
.total-row.discount {
  margin-top: 2px;
}
.discount-input-wrap {
  display: flex;
  align-items: center;
  gap: 2px;
  background: var(--card-bg, white);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 6px;
  padding: 2px 8px;
}
.discount-currency {
  font-size: 0.78rem;
  color: var(--text-light, #9fb3c8);
  font-weight: 600;
}
.discount-input-wrap input {
  width: 80px;
  border: none;
  outline: none;
  text-align: right;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-main, #102a43);
  background: transparent;
  padding: 3px 0;
}
.discount-input-wrap input::-webkit-inner-spin-button {
  display: none;
}
.total-row.grand {
  margin-top: 8px;
  padding-top: 10px;
  border-top: 2px solid var(--border-color, #e2e8f0);
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-main, #102a43);
}
.grand-total-value {
  font-size: 1.2rem;
  font-weight: 800;
  color: var(--coral, #ef5350);
}

/* Checkout Button */
.btn-checkout {
  width: 100%;
  padding: 14px;
  border: none;
  border-radius: 12px;
  background: linear-gradient(135deg, #FF6B9D, #E63B6F);
  color: white;
  font-size: 1rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 4px 14px rgba(230, 59, 111, 0.3);
  margin-top: 4px;
}
.btn-checkout:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(230, 59, 111, 0.4);
}
.btn-checkout:active:not(:disabled) {
  transform: translateY(0);
}
.btn-checkout:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}

/* MODAL */
.variants-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    max-height: 400px;
    overflow-y: auto;
}

.variant-item {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.variant-item:hover:not(.is-out-of-stock) {
    border-color: #94a3b8;
}

.variant-item.is-selected {
    border-color: #E63B6F;
    background-color: #FFF0F3;
}

.variant-item.is-out-of-stock {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f8fafc;
}

.variant-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #eee;
}

/* VUE DRIVEN MODAL STYLES */
.vue-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vue-modal-container {
    width: 100%;
    max-width: 500px;
    margin: 1.75rem auto;
}

.vue-modal-content {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
}

.vue-modal-body {
    flex: 1 1 auto;
}
</style>
