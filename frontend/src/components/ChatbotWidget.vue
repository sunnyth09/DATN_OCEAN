<template>
  <div
    v-if="!isHiddenPage"
    class="chatbot-wrapper"
    :class="{ 'on-product-detail': isProductDetailPage, 'is-open': isOpen }"
    id="ocean-chatbot"
    @click.self="isOpen = false"
  >
    <!-- Floating Bubble -->
    <button
      class="chatbot-bubble"
      :class="{ 'is-open': isOpen, 'has-unread': hasUnread && !isOpen }"
      @click="toggleChat"
      id="chatbot-toggle-btn"
      aria-label="Mở trợ lý chat"
    >
      <transition name="icon-swap" mode="out-in">
        <svg v-if="!isOpen" key="chat" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
        </svg>
        <svg v-else key="close" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </transition>
      <span v-if="hasUnread && !isOpen" class="unread-dot"></span>
    </button>

    <!-- Chat Window -->
    <transition name="chat-window">
      <div v-if="isOpen" class="chatbot-window" id="chatbot-window">
        <!-- Header -->
        <div class="chat-header">
          <div class="chat-header-info">
            <div class="chat-avatar">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2a7 7 0 017 7v1a7 7 0 01-14 0V9a7 7 0 017-7z"/>
                <path d="M5 22v-1a7 7 0 0114 0v1"/>
                <circle cx="12" cy="10" r="3"/>
              </svg>
            </div>
            <div>
              <h3 class="chat-title">{{ mode === 'live' ? 'Hỗ trợ viên' : 'Ocean Sport AI' }}</h3>
              <p class="chat-subtitle">{{ mode === 'live' ? 'Sẵn sàng hỗ trợ bạn' : 'Trợ lý mua sắm thông minh' }}</p>
            </div>
          </div>
          <button class="chat-close-btn" @click="isOpen = false" aria-label="Đóng chat" title="Đóng">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <!-- Messages -->
        <div class="chat-messages" ref="messagesContainer">
          <!-- Welcome message -->
          <div v-if="messages.length === 0" class="welcome-section">
            <div class="welcome-icon">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12c1.5-3 4.5-3 6 0s4.5 3 6 0 4.5-3 6 0"/>
                <path d="M2 17c1.5-3 4.5-3 6 0s4.5 3 6 0 4.5-3 6 0" opacity="0.5"/>
                <path d="M2 7c1.5-3 4.5-3 6 0s4.5 3 6 0 4.5-3 6 0" opacity="0.5"/>
              </svg>
            </div>
            <h4 class="welcome-title">{{ mode === 'live' ? 'Kết nối thành công!' : 'Xin chào! Tôi là Ocean Sport AI' }}</h4>
            <p class="welcome-desc">{{ mode === 'live' ? 'Vui lòng đặt câu hỏi, chúng tôi sẽ phản hồi trong giây lát.' : 'Tôi có thể giúp bạn tìm sản phẩm, tra đơn hàng, xem khuyến mãi và nhiều hơn nữa!' }}</p>
          </div>

          <!-- Message items -->
          <div v-for="(msg, idx) in messages" :key="idx" class="message-item" :class="msg.role">
            <!-- AI avatar -->
            <div v-if="msg.role === 'assistant'" class="msg-avatar">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12c1.5-3 4.5-3 6 0s4.5 3 6 0 4.5-3 6 0"/>
                <path d="M2 17c1.5-3 4.5-3 6 0s4.5 3 6 0 4.5-3 6 0" opacity="0.5"/>
              </svg>
            </div>
            <div class="msg-bubble" :class="msg.role">
              <div class="msg-text" v-html="formatMessage(msg.content)"></div>
              <button v-if="msg.type === 'requires_login'" class="chatbot-action-btn primary full login-action-btn" @click="goToLogin">
                Đăng nhập để tiếp tục
              </button>

              <!-- Product Cards -->
              <ProductCards
                v-if="msg.data && msg.type === 'search_products'"
                :products="msg.data"
                @go-to-product="goToProduct"
                @add-variant="addVariantToCart"
                @show-variant-picker="showVariantPicker"
                @buy-now="buyNowVariant"
              />

              <!-- Product Detail Card -->
              <div v-if="msg.data && msg.type === 'get_product_detail'" class="product-detail-card">
                <div class="pd-header">
                  <img :src="getProductImage(msg.data.thumbnail)" :alt="msg.data.name" class="pd-img" loading="lazy" />
                  <div class="pd-main-info">
                    <h4 class="pd-name">{{ msg.data.name }}</h4>
                    <p class="pd-price">{{ msg.data.price_range }}</p>
                    <span v-if="msg.data.category" class="product-card-cat">{{ msg.data.category }}</span>
                  </div>
                </div>
                <p v-if="msg.data.short_description" class="pd-desc">{{ msg.data.short_description }}</p>
                <div v-if="msg.data.variants && msg.data.variants.length" class="pd-variants">
                  <p class="pd-variants-title">Phiên bản:</p>
                  <div v-for="(v, vi) in msg.data.variants.slice(0, 8)" :key="vi" class="pd-variant-row">
                    <span class="pd-variant-name">{{ v.variant_name || [v.color, v.size].filter(Boolean).join(' / ') }}</span>
                    <span class="pd-variant-price">{{ v.price }}</span>
                    <span class="pd-variant-status" :class="v.stock > 0 ? 'in-stock' : 'out-stock'">{{ v.status }}</span>
                    <button v-if="v.stock > 0" class="mini-add-btn" @click="addVariantToCart(msg.data, v)">Thêm</button>
                  </div>
                </div>
              </div>

              <!-- Variant Picker -->
              <VariantPickerCard
                v-if="msg.data && msg.type === 'variant_picker'"
                :product="msg.data"
                @add-variant="addVariantToCart"
                @buy-now="buyNowVariant"
              />

              <!-- Order Card -->
              <div v-if="msg.data && msg.type === 'get_order_status'" class="order-cards">
                <div v-for="(order, oi) in (Array.isArray(msg.data) ? msg.data : [msg.data])" :key="oi" class="order-card">
                  <div class="order-card-header">
                    <span class="order-code">{{ order.order_code }}</span>
                    <span class="order-status" :class="'status-' + order.status_raw">{{ order.status }}</span>
                  </div>
                  <div class="order-card-body">
                    <div class="order-items">
                      <div v-for="(item, ii) in order.items?.slice(0, 3)" :key="ii" class="order-item-row">
                        <span class="order-item-name">{{ item.product_name }}</span>
                        <span class="order-item-qty">x{{ item.quantity }}</span>
                      </div>
                      <p v-if="order.items?.length > 3" class="order-more">+{{ order.items.length - 3 }} sản phẩm khác</p>
                    </div>
                    <div class="order-total">
                      <span>Tổng cộng:</span>
                      <strong>{{ order.grand_total }}</strong>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Coupon Cards -->
              <div v-if="msg.data && msg.type === 'get_available_coupons'" class="coupon-cards">
                <div v-for="(coupon, ci) in msg.data" :key="ci" class="coupon-card">
                  <div class="coupon-code">{{ coupon.code }}</div>
                  <div class="coupon-desc">{{ coupon.description }}</div>
                  <div class="coupon-meta">
                    <span>Đơn tối thiểu: {{ coupon.min_order }}</span>
                    <span>HSD: {{ coupon.end_date }}</span>
                  </div>
                </div>
              </div>

              <!-- Category List -->
              <div v-if="msg.data && msg.type === 'get_categories'" class="category-cards">
                <div v-for="(cat, ci) in msg.data" :key="ci" class="category-card" @click="goToCategory(cat.name)">
                  <div class="cat-info">
                    <span class="cat-name">{{ cat.name }}</span>
                    <span class="cat-count">{{ cat.product_count }} sản phẩm</span>
                  </div>
                  <div v-if="cat.children && cat.children.length" class="cat-children">
                    <span v-for="(child, cci) in cat.children" :key="cci" class="cat-child-tag">{{ child.name }}</span>
                  </div>
                </div>
              </div>

              <!-- Cart Summary -->
              <div v-if="msg.data && msg.type === 'cart_summary'" class="order-card chatbot-summary-card">
                <div class="order-card-header">
                  <span class="order-code">Giỏ hàng</span>
                  <strong>{{ msg.data.total_price_formatted }}</strong>
                </div>
                <div class="order-card-body">
                  <div v-for="item in msg.data.items?.slice(0, 4)" :key="item.cart_item_id" class="order-item-row">
                    <span class="order-item-name">{{ item.product?.name }} {{ item.variant?.size ? `(${item.variant.size})` : '' }}</span>
                    <span class="order-item-qty">x{{ item.quantity }}</span>
                  </div>
                  <button class="chatbot-action-btn primary full" @click="getAddressesForOrder">Đặt hàng từ giỏ</button>
                </div>
              </div>

              <!-- Address Choices -->
              <div v-if="msg.data && msg.type === 'get_my_addresses'" class="category-cards">
                <div v-for="address in msg.data" :key="address.address_id" class="category-card">
                  <div class="cat-info">
                    <span class="cat-name">{{ address.recipient_name }} <small v-if="address.is_default">(Mặc định)</small></span>
                    <span class="cat-count">{{ address.phone_masked }}</span>
                  </div>
                  <p class="address-summary">{{ address.summary }}</p>
                  <button class="chatbot-action-btn primary full" @click="prepareOrder(address.address_id)">Giao tới địa chỉ này</button>
                </div>
              </div>

              <!-- Order Preview -->
              <div v-if="msg.data && msg.type === 'order_preview'" class="order-card chatbot-summary-card">
                <div class="order-card-header">
                  <span class="order-code">Xem trước đơn hàng</span>
                  <strong>{{ msg.data.totals?.grand_total_formatted }}</strong>
                </div>
                <div class="order-card-body">
                  <div v-for="item in msg.data.items" :key="item.cart_item_id" class="order-item-row">
                    <span class="order-item-name">{{ item.name }} {{ item.size ? `(${item.size})` : '' }}</span>
                    <span class="order-item-qty">x{{ item.quantity }}</span>
                  </div>
                  <div class="order-total"><span>Phí ship:</span><strong>{{ msg.data.totals?.shipping_fee_formatted }}</strong></div>
                  <div class="order-total"><span>Thanh toán:</span><strong>{{ msg.data.payment_method_label || paymentMethodLabel(msg.data.payment_method) }}</strong></div>
                  <div class="order-total"><span>Tổng:</span><strong>{{ msg.data.totals?.grand_total_formatted }}</strong></div>
                  <p class="address-summary">Giao tới: {{ msg.data.address?.summary }}</p>
                  <button class="chatbot-action-btn primary full" :disabled="confirmingOrder" @click="confirmOrder(msg.data.confirmation_token)">
                    {{ confirmingOrder ? 'Đang xác nhận...' : 'Xác nhận đặt hàng' }}
                  </button>
                </div>
              </div>

              <!-- Order Confirmation -->
              <div v-if="msg.data && msg.type === 'order_confirmation'" class="order-card chatbot-summary-card">
                <div class="order-card-header">
                  <span class="order-code">{{ msg.data.order_code }}</span>
                  <span class="order-status status-confirmed">Đã tạo đơn</span>
                </div>
                <div class="order-card-body">
                  <div class="order-total"><span>Tổng cộng:</span><strong>{{ formatCurrency(msg.data.grand_total) }}</strong></div>
                </div>
              </div>

            </div>
          </div>

          <!-- Typing indicator -->
          <div v-if="isTyping" class="message-item assistant">
            <div class="msg-avatar">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12c1.5-3 4.5-3 6 0s4.5 3 6 0 4.5-3 6 0"/>
                <path d="M2 17c1.5-3 4.5-3 6 0s4.5 3 6 0 4.5-3 6 0" opacity="0.5"/>
              </svg>
            </div>
            <div class="msg-bubble assistant typing-bubble">
              <div class="typing-indicator">
                <span></span><span></span><span></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions (toggleable) -->
        <transition name="quick-slide">
          <div v-show="showQuickActions" class="quick-actions">
            <button v-for="action in quickActions" :key="action.text" class="quick-action-btn" @click="sendQuickAction(action.text)">
              <span class="quick-icon" v-html="action.icon"></span>
              {{ action.text }}
            </button>
          </div>
        </transition>

        <!-- Input -->
        <div class="chat-input-area">
          <button class="quick-toggle-btn" @click="showQuickActions = !showQuickActions" :class="{ active: showQuickActions }" title="Gợi ý nhanh">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
          </button>
          <textarea
            ref="chatInput"
            v-model="inputMessage"
            placeholder="Nhập tin nhắn..."
            class="chat-input"
            @keydown.enter.exact.prevent="sendMessage"
            @input="autoResize"
            id="chatbot-input"
            maxlength="1000"
            rows="1"
          ></textarea>
          <button class="chat-send-btn" @click="sendMessage" :disabled="!inputMessage.trim() || isTyping" id="chatbot-send-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { useCartStore } from '@/stores/cart';
const cartStore = useCartStore();
import { ref, nextTick, onMounted, watch, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '../axios.js';
import { getAppBaseUrl } from '@/utils/url';
import ProductCards from './chatbot/ProductCards.vue';
import VariantPickerCard from './chatbot/VariantPickerCard.vue';

const router = useRouter();
const route = useRoute();
const BASE_URL = getAppBaseUrl();

const isHiddenPage = computed(() => {
  return ['cart', 'checkout'].includes(route?.name);
});

const isProductDetailPage = computed(() => {
  return route?.name === 'product-detail';
});

const isOpen = ref(false);
const hasUnread = ref(false);
const inputMessage = ref('');
const isTyping = ref(false);
const showQuickActions = ref(true);
const messages = ref([]);
const messagesContainer = ref(null);
const chatInput = ref(null);

const mode = ref('ai'); // 'ai' or 'live'
const sessionToken = ref(localStorage.getItem('ocean_live_chat_token') || '');
const isConnecting = ref(false);
const confirmingOrder = ref(false);
let isConnectedLiveChat = false;

/** Conversation history cho Gemini (role/parts format) */
const conversationHistory = ref([]);

const quickActions = computed(() => {
  if (mode.value === 'live') {
    return [
      { icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>', text: 'Trở lại Chatbot AI' },
      { icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>', text: 'Đơn hàng của tôi đang ở đâu?' },
      { icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>', text: 'Xin chào, tôi cần hỗ trợ!' }
    ];
  }
  return [
    { icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>', text: 'Gợi ý sản phẩm bán chạy' },
    { icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>', text: 'Xem đơn hàng của tôi' },
    { icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>', text: 'Có mã giảm giá nào không?' },
    { icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>', text: 'Chính sách đổi trả' },
    { icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>', text: 'Liên hệ nhân viên hỗ trợ' },
  ];
});

// ==================== LIFECYCLE ====================
onMounted(() => {
  if (sessionToken.value) {
    mode.value = 'live';

    // Restore live messages from sessionStorage if available
    const savedLive = sessionStorage.getItem('ocean_chatbot_messages_live');
    if (savedLive) {
      try {
        messages.value = JSON.parse(savedLive);
      } catch (e) { /* ignore */ }
    }

    // Gọi API để lấy tin nhắn mới nhất (tránh mất tin nhắn sau khi F5)
    const token = sessionStorage.getItem('auth_token');
    const headers = token ? { 'Authorization': `Bearer ${token}` } : {};
    api.post('/live-chat/init', { session_token: sessionToken.value }, { headers })
      .then(response => {
        if (response.data && response.data.session) {
          sessionToken.value = response.data.session.session_token;
          localStorage.setItem('ocean_live_chat_token', sessionToken.value);

          if (response.data.messages && response.data.messages.length > 0) {
            messages.value = response.data.messages.map(m => ({
              id: m.id,
              role: m.sender_type === 'user' ? 'user' : 'assistant',
              content: m.message
            }));
            scrollToBottom();
          }
          connectLiveChat();
        } else {
          // Session không hợp lệ hoặc đã đóng
          sessionToken.value = '';
          localStorage.removeItem('ocean_live_chat_token');
          mode.value = 'ai';
          restoreAiMessages();
        }
      })
      .catch(err => {
        console.error('Lỗi tải lịch sử live chat:', err);
        connectLiveChat();
      });
  } else {
    restoreAiMessages();
  }

  // Lắng nghe khi tab active lại hoặc WebSocket kết nối lại để đảm bảo bind lại channel
  window.addEventListener('pageshow', () => {
    if (mode.value === 'live' && sessionToken.value) {
      connectLiveChat();
    }
  });
});

function restoreAiMessages() {
  const saved = sessionStorage.getItem('ocean_chatbot_messages_ai');
  const savedHistory = sessionStorage.getItem('ocean_chatbot_history_ai');
  if (saved) {
    try {
      messages.value = JSON.parse(saved);
    } catch (e) { /* ignore */ }
  } else {
    messages.value = [
      {
        role: 'assistant',
        content: 'Xin chào! Tôi là trợ lý AI của Ocean Sport. Tôi có thể giúp gì cho bạn hôm nay?',
      }
    ];
  }
  if (savedHistory) {
    try {
      conversationHistory.value = JSON.parse(savedHistory);
    } catch (e) { /* ignore */ }
  }
}

// Save messages on change depending on mode
watch(messages, (val) => {
  if (mode.value === 'live') {
    sessionStorage.setItem('ocean_chatbot_messages_live', JSON.stringify(val));
  } else {
    sessionStorage.setItem('ocean_chatbot_messages_ai', JSON.stringify(val));
  }
}, { deep: true });

watch(conversationHistory, (val) => {
  if (mode.value === 'ai') {
    sessionStorage.setItem('ocean_chatbot_history_ai', JSON.stringify(val));
  }
}, { deep: true });

// ==================== METHODS ====================
function toggleChat() {
  isOpen.value = !isOpen.value;
  hasUnread.value = false;
  if (isOpen.value) {
    nextTick(() => {
      chatInput.value?.focus();
      scrollToBottom();
    });
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
  });
}

function getProductImage(thumbnail) {
  if (!thumbnail) return '';
  if (thumbnail.startsWith('http')) return thumbnail;
  // Normalize path — remove leading slashes and 'storage/' prefix if present
  const cleaned = thumbnail.replace(/^\/+/, '').replace(/^storage\//, '');
  return `${BASE_URL}/storage/${cleaned}`;
}

function goToProduct(slug) {
  if (slug) {
    router.push(`/product/${slug}`);
    isOpen.value = false;
  }
}

function goToCategory(categoryName) {
  if (categoryName) {
    router.push({ path: '/products', query: { category: categoryName } });
    isOpen.value = false;
  }
}

function formatMessage(text) {
  if (!text) return '';
  const escaped = String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
  // Basic markdown-like formatting sau khi escape HTML để tránh AI/user chèn script
  return escaped
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/\n/g, '<br/>');
}

function formatCurrency(value) {
  const amount = Number(value || 0);
  return amount.toLocaleString('vi-VN') + 'đ';
}

function paymentMethodLabel(method) {
  if (method === 'bank_transfer') return 'Chuyển khoản ngân hàng';
  return 'Thanh toán khi nhận hàng (COD)';
}

function pushAssistantMessage(content, type = 'text', data = null) {
  messages.value.push({ role: 'assistant', content, type, data });
  scrollToBottom();
}

function showVariantPicker(product) {
  pushAssistantMessage(`Bạn chọn màu/size cho ${product.name} nhé.`, 'variant_picker', product);
}

function getAuthFriendlyMessage(error, fallback) {
  if (error.response?.status === 401 || error.response?.data?.message === 'Unauthenticated.') {
    return 'Bạn cần đăng nhập tài khoản khách hàng để thêm sản phẩm vào giỏ hàng hoặc đặt hàng.';
  }
  return error.response?.data?.message || fallback;
}

function goToLogin() {
  isOpen.value = false;
  router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
}

async function addVariantToCart(product, variant) {
  if (!variant?.variant_id || isTyping.value) return;
  isTyping.value = true;
  try {
    const response = await api.post('/chatbot/cart/add', {
      product_id: product.product_id,
      variant_id: variant.variant_id,
      quantity: 1,
    });
    const data = response.data;
    pushAssistantMessage(data.message || 'Đã thêm sản phẩm vào giỏ hàng.', data.type || 'cart_summary', data.data);
    cartStore.fetchCount()
  } catch (error) {
    const message = getAuthFriendlyMessage(error, 'Sản phẩm hiện không khả dụng hoặc không thể thêm vào giỏ hàng.');
    const type = error.response?.status === 401 ? 'requires_login' : (error.response?.data?.type || 'error');
    pushAssistantMessage(message, type, null);
  } finally {
    isTyping.value = false;
  }
}

/**
 * Mua ngay: thêm variant vào giỏ hàng rồi chuyển thẳng đến trang thanh toán
 */
async function buyNowVariant(product, variant) {
  if (!variant?.variant_id || isTyping.value) return;
  isTyping.value = true;
  try {
    await api.post('/chatbot/cart/add', {
      product_id: product.product_id,
      variant_id: variant.variant_id,
      quantity: 1,
    });
    cartStore.fetchCount();
    isOpen.value = false;
    router.push('/checkout');
  } catch (error) {
    const message = getAuthFriendlyMessage(error, 'Không thể thêm sản phẩm vào giỏ hàng.');
    const type = error.response?.status === 401 ? 'requires_login' : 'error';
    pushAssistantMessage(message, type, null);
  } finally {
    isTyping.value = false;
  }
}

async function getAddressesForOrder() {
  if (isTyping.value) return;
  isTyping.value = true;
  try {
    const response = await api.get('/chatbot/addresses');
    const data = response.data;
    pushAssistantMessage(data.message, data.type || 'get_my_addresses', data.data);
  } catch (error) {
    const message = getAuthFriendlyMessage(error, 'Giỏ hàng của bạn đang trống hoặc chưa có sản phẩm được chọn để thanh toán.');
    const type = error.response?.status === 401 ? 'requires_login' : 'error';
    pushAssistantMessage(message, type, null);
  } finally {
    isTyping.value = false;
  }
}

async function prepareOrder(addressId) {
  if (isTyping.value) return;
  isTyping.value = true;
  try {
    const response = await api.post('/chatbot/order/prepare', {
      address_id: addressId,
      payment_method: 'cod',
    });
    const data = response.data;
    pushAssistantMessage(data.message || 'Vui lòng kiểm tra đơn hàng trước khi xác nhận.', data.type || 'order_preview', data.data);
  } catch (error) {
    const message = getAuthFriendlyMessage(error, 'Không thể chuẩn bị đơn hàng. Vui lòng kiểm tra giỏ hàng và địa chỉ.');
    pushAssistantMessage(message, error.response?.status === 401 ? 'requires_login' : 'error', null);
  } finally {
    isTyping.value = false;
  }
}

async function confirmOrder(token) {
  if (!token || confirmingOrder.value) return;
  confirmingOrder.value = true;
  try {
    const response = await api.post('/chatbot/order/confirm', { confirmation_token: token });
    const data = response.data;
    pushAssistantMessage(data.message || 'Đặt hàng thành công!', data.type || 'order_confirmation', data.data);
    cartStore.fetchCount();
  } catch (error) {
    const message = getAuthFriendlyMessage(error, 'Không thể xác nhận đơn hàng. Vui lòng tạo lại bản xem trước.');
    pushAssistantMessage(message, error.response?.status === 401 ? 'requires_login' : 'error', null);
  } finally {
    confirmingOrder.value = false;
  }
}

function sendQuickAction(text) {
  if (isTyping.value || isConnecting.value) return;

  if (text === 'Trở lại Chatbot AI') {
    if (window.Echo && sessionToken.value) {
      try {
        window.Echo.leave(`chat.${sessionToken.value}`);
      } catch (e) {}
    }
    currentLiveChannel = null;
    mode.value = 'ai';
    showQuickActions.value = true;
    restoreAiMessages();
    scrollToBottom();
    return;
  }
  if (text === 'Liên hệ nhân viên hỗ trợ') {
    startLiveChat();
    return;
  }
  inputMessage.value = text;
  sendMessage();
}

async function startLiveChat() {
  if (isConnecting.value) return;
  mode.value = 'live';
  isConnecting.value = true;
  showQuickActions.value = false;

  // Restore cached live messages or clear for fresh load
  const savedLive = sessionStorage.getItem('ocean_chatbot_messages_live');
  if (savedLive) {
    try {
      messages.value = JSON.parse(savedLive);
    } catch (e) {
      messages.value = [];
    }
  } else {
    messages.value = [];
  }

  try {
    const token = sessionStorage.getItem('auth_token');
    const headers = token ? { 'Authorization': `Bearer ${token}` } : {};

    // Gọi API init session
    const response = await api.post('/live-chat/init', {
      session_token: sessionToken.value
    }, { headers });

    if (response.data && response.data.session) {
       sessionToken.value = response.data.session.session_token;
       localStorage.setItem('ocean_live_chat_token', sessionToken.value);

       // Hiển thị mảng lịch sử mới nhất từ database
       if (response.data.messages && response.data.messages.length > 0) {
          messages.value = response.data.messages.map(m => ({
             id: m.id,
             role: m.sender_type === 'user' ? 'user' : 'assistant',
             content: m.message
          }));
          scrollToBottom();
       }

       connectLiveChat();
    }
  } catch (error) {
    console.error("Lỗi khi kết nối Live Chat", error);
    mode.value = 'ai'; // Fallback back to AI
    restoreAiMessages();
  } finally {
    isConnecting.value = false;
  }
}

function ensureEcho(callback, maxAttempts = 30) {
  if (window.Echo) {
    callback(window.Echo);
  } else if (maxAttempts > 0) {
    setTimeout(() => ensureEcho(callback, maxAttempts - 1), 200);
  }
}

let currentLiveChannel = null;

function connectLiveChat() {
  if (!sessionToken.value) return;

  ensureEcho((echo) => {
    const channelName = `chat.${sessionToken.value}`;

    // Rời kênh cũ nếu khác tên
    if (currentLiveChannel && currentLiveChannel !== channelName) {
      try {
        echo.leave(currentLiveChannel);
      } catch (e) {}
    }
    currentLiveChannel = channelName;

    const handleMessage = (e) => {
      if (e.senderType === 'admin') {
         if (e.message?.message === 'SYSTEM_SESSION_CLOSED') {
            messages.value.push({ role: 'assistant', content: 'Phiên hỗ trợ đã kết thúc.' });
            mode.value = 'ai';
            showQuickActions.value = true;
            try {
              echo.leave(channelName);
            } catch (err) {}
            currentLiveChannel = null;
            sessionToken.value = '';
            localStorage.removeItem('ocean_live_chat_token');
            scrollToBottom();
            return;
         }

         if (e.message?.id) {
           const exists = messages.value.some(m => m.id && String(m.id) === String(e.message.id));
           if (exists) return;
         }

         messages.value.push({
           id: e.message?.id,
           role: 'assistant',
           content: e.message?.message || ''
         });
         scrollToBottom();
         if (!isOpen.value) hasUnread.value = true;
      } else if (e.senderType === 'user') {
         // Trường hợp broadcast user message (tránh trùng lặp với optimistic UI)
         if (e.message?.id) {
           const exists = messages.value.some(m => m.id && String(m.id) === String(e.message.id));
           if (exists) return;

           const pendingIdx = messages.value.findIndex(m => m._tempId && m.content === e.message.message);
           if (pendingIdx !== -1) {
             messages.value[pendingIdx].id = e.message.id;
             delete messages.value[pendingIdx]._tempId;
             return;
           }

           messages.value.push({
             id: e.message.id,
             role: 'user',
             content: e.message.message
           });
           scrollToBottom();
         }
      }
    };

    echo.channel(channelName)
      .stopListening('.message.sent')
      .listen('.message.sent', handleMessage)
      .stopListening('MessageSent')
      .listen('MessageSent', handleMessage);
  });
}

function autoResize() {
  const el = chatInput.value;
  if (!el) return;
  el.style.height = 'auto';
  el.style.height = el.scrollHeight + 'px';
}

async function sendMessage() {
  const msg = inputMessage.value.trim();
  if (!msg || isTyping.value || isConnecting.value) return;

  // Reset textarea height
  if (chatInput.value) {
    chatInput.value.style.height = 'auto';
  }

  inputMessage.value = '';
  isTyping.value = true;

  if (mode.value === 'live') {
    // Optimistic UI cho Live Chat kèm tempId
    const tempId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
    messages.value.push({
      _tempId: tempId,
      role: 'user',
      content: msg,
    });
    scrollToBottom();

    try {
      const token = sessionStorage.getItem('auth_token');
      const headers = token ? { 'Authorization': `Bearer ${token}` } : {};

      const response = await api.post('/live-chat/message', {
        message: msg,
        session_token: sessionToken.value
      }, { headers });

      const data = response.data;
      if (data.success && data.message?.id) {
        const idx = messages.value.findIndex(m => m._tempId === tempId);
        if (idx !== -1) {
          messages.value[idx].id = data.message.id;
          delete messages.value[idx]._tempId;
        }
      } else if (data.is_closed) {
        messages.value.push({
          role: 'assistant',
          content: data.message || 'Phiên hỗ trợ đã kết thúc.'
        });
        mode.value = 'ai';
        showQuickActions.value = true;
        // Store token BEFORE clearing so we can leave the correct channel
        const closedToken = sessionToken.value;
        sessionToken.value = '';
        localStorage.removeItem('ocean_live_chat_token');
        if (window.Echo && closedToken) {
          try { window.Echo.leave(`chat.${closedToken}`); } catch (e) {}
        }
        currentLiveChannel = null;
        restoreAiMessages();
        scrollToBottom();
      }
    } catch (error) {
      console.error('Lỗi gửi tin nhắn live chat:', error);
      messages.value.push({
        role: 'assistant',
        content: 'Không thể gửi tin nhắn. Vui lòng kiểm tra lại kết nối mạng!',
      });
    } finally {
      isTyping.value = false;
    }
    return;
  }

  // Chế độ Chatbot AI
  messages.value.push({
    role: 'user',
    content: msg,
  });
  conversationHistory.value.push({
    role: 'user',
    parts: [{ text: msg }],
  });
  scrollToBottom();

  try {
    const token = sessionStorage.getItem('auth_token');
    const headers = token ? { 'Authorization': `Bearer ${token}` } : {};

    const response = await api.post(
      '/chatbot/message',
      {
        message: msg,
        history: conversationHistory.value.slice(0, -1),
      },
      { headers }
    );

    const data = response.data;
    if (data.success) {
      const assistantMsg = {
        role: 'assistant',
        content: data.message,
        data: data.data,
        type: data.type,
      };
      messages.value.push(assistantMsg);

      // Add to conversation history
      conversationHistory.value.push({
        role: 'model',
        parts: [{ text: data.message }],
      });

      if (!isOpen.value) {
        hasUnread.value = true;
      }
    } else {
      messages.value.push({
        role: 'assistant',
        content: data.message || 'Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại!',
      });
      conversationHistory.value.pop();
    }
  } catch (error) {
    console.error('Chat error:', error);
    messages.value.push({
      role: 'assistant',
      content: 'Xin lỗi, kết nối bị gián đoạn. Vui lòng thử lại sau!',
    });
    conversationHistory.value.pop();
  } finally {
    isTyping.value = false;
    scrollToBottom();
    nextTick(() => {
      chatInput.value?.focus();
    });
  }
}
</script>

<style scoped>
/* ==================== CHATBOT WRAPPER ==================== */
.chatbot-wrapper {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

/* ==================== FLOATING BUBBLE ==================== */
.chatbot-bubble {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary) 0%, #ff6b8b 50%, var(--primary-dark) 100%);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  box-shadow: 0 4px 14px rgba(230, 59, 111, 0.3);
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  z-index: 1;
}

.chatbot-bubble:hover {
  transform: scale(1.06);
  box-shadow: 0 6px 18px rgba(230, 59, 111, 0.4);
}

.chatbot-bubble.is-open {
  animation: none;
  background: linear-gradient(135deg, #374151 0%, #4b5563 100%);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.chatbot-bubble svg {
  width: 22px;
  height: 22px;
}

.unread-dot {
  position: absolute;
  top: -2px;
  right: -2px;
  width: 16px;
  height: 16px;
  background: #ef4444;
  border-radius: 50%;
  border: 3px solid #fff;
  animation: unread-blink 1s ease-in-out infinite;
}

@keyframes unread-blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* ==================== ICON TRANSITION ==================== */
.icon-swap-enter-active, .icon-swap-leave-active {
  transition: all 0.2s ease;
}
.icon-swap-enter-from { opacity: 0; transform: rotate(-90deg) scale(0.5); }
.icon-swap-leave-to { opacity: 0; transform: rotate(90deg) scale(0.5); }

/* ==================== CHAT WINDOW ==================== */
.chatbot-widget {
  font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.chatbot-window {
  position: absolute;
  bottom: 72px;
  right: 0;
  width: 400px;
  height: 580px;
  background: var(--card-bg);
  border-radius: 20px;
  box-shadow:
    0 24px 80px rgba(0, 0, 0, 0.15),
    0 8px 32px rgba(0, 0, 0, 0.08);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid rgba(229, 231, 235, 0.6);
  font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Window transition */
.chat-window-enter-active {
  animation: window-in 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.chat-window-leave-active {
  animation: window-out 0.25s ease-in;
}

@keyframes window-in {
  0% { opacity: 0; transform: translateY(20px) scale(0.9); }
  100% { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes window-out {
  0% { opacity: 1; transform: translateY(0) scale(1); }
  100% { opacity: 0; transform: translateY(20px) scale(0.9); }
}

/* ==================== HEADER ==================== */
.chat-header {
  background: linear-gradient(135deg, var(--primary) 0%, #ff6b8b 60%, var(--primary-dark) 100%);
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}

.chat-header-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.chat-avatar {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}

.chat-title {
  font-size: 1rem;
  font-weight: 700;
  color: #fff;
  margin: 0;
}

.chat-subtitle {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.8);
  margin: 2px 0 0;
}

.chat-close-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}
.chat-close-btn:hover { background: rgba(255, 255, 255, 0.3); }

/* ==================== MESSAGES ==================== */
.chat-messages {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: #f8fafc;
  scroll-behavior: smooth;
  width: 100%;
  box-sizing: border-box;
}

.chat-messages::-webkit-scrollbar { width: 4px; }
.chat-messages::-webkit-scrollbar-track { background: transparent; }
.chat-messages::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }

/* Welcome */
.welcome-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 32px 16px;
  gap: 8px;
}
.welcome-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, #dbeafe, rgba(230, 59, 111, 0.08));
  display: flex;
  align-items: center;
  justify-content: center;
}
.welcome-title { font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0; }
.welcome-desc { font-size: 0.85rem; color: #6b7280; margin: 0; line-height: 1.5; }

/* Message items */
.message-item {
  display: flex;
  gap: 8px;
  max-width: 90%;
  min-width: 0;
  box-sizing: border-box;
}
.message-item.user {
  align-self: flex-end;
  flex-direction: row-reverse;
}
.message-item.assistant {
  align-self: flex-start;
}

.msg-avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: linear-gradient(135deg, #dbeafe, rgba(230, 59, 111, 0.08));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  flex-shrink: 0;
  margin-top: 2px;
}

.msg-bubble {
  padding: 10px 14px;
  border-radius: 16px;
  font-size: 0.86rem;
  line-height: 1.5;
  word-break: break-word;
  overflow-wrap: anywhere;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.msg-bubble.user {
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff;
  border-bottom-right-radius: 4px;
}

.msg-bubble.assistant {
  background: var(--card-bg);
  color: #1f2937;
  border: 1px solid #e5e7eb;
  border-bottom-left-radius: 4px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.msg-text {
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: anywhere;
  max-width: 100%;
}

/* ==================== PRODUCT CARDS ==================== */
.product-cards {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
}

.product-card {
  display: flex;
  gap: 10px;
  padding: 10px;
  border-radius: 12px;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  cursor: pointer;
  transition: all 0.2s;
}
.product-card:hover {
  background: #fff0f3;
  border-color: #ffb2bf;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.08);
}

.product-card-img {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  object-fit: cover;
  background: #f1f5f9;
  flex-shrink: 0;
}

.product-card-info {
  flex: 1;
  min-width: 0;
}

.product-card-name {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-main);
  margin: 0 0 4px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-card-price {
  font-size: 0.85rem;
  font-weight: 700;
  color: #dc2626;
  margin: 0;
}

.product-card-cat {
  font-size: 0.7rem;
  color: #6b7280;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
  display: inline-block;
  margin-top: 4px;
}

.chatbot-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.chatbot-action-btn,
.mini-add-btn {
  border: 1px solid #fecdd3;
  background: var(--card-bg);
  color: var(--primary);
  border-radius: 999px;
  padding: 5px 10px;
  font-size: 0.72rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.chatbot-action-btn:hover,
.mini-add-btn:hover {
  background: #fff0f3;
  border-color: #fb7185;
}

.chatbot-action-btn.primary {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}

.chatbot-action-btn.full {
  width: 100%;
  justify-content: center;
  border-radius: 10px;
  padding: 8px 12px;
}

.chatbot-action-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.mini-add-btn {
  flex-shrink: 0;
  padding: 4px 8px;
}

.address-summary {
  margin: 6px 0 8px;
  font-size: 0.76rem;
  color: #4b5563;
  line-height: 1.4;
}

.chatbot-summary-card {
  margin-top: 10px;
}

/* ==================== ORDER CARDS ==================== */
.order-cards {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
}

.order-card {
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  background: #f8fafc;
  overflow: hidden;
}

.order-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  background: #f1f5f9;
  border-bottom: 1px solid #e5e7eb;
}

.order-code {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--primary);
}

.order-status {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 20px;
  white-space: nowrap;
}
.status-pending { background: #fef3c7; color: #92400e; }
.status-confirmed { background: #d1fae5; color: #065f46; }
.status-shipping { background: #dbeafe; color: #1e40af; }
.status-delivered { background: #e0e7ff; color: #3730a3; }
.status-completed { background: #d1fae5; color: #065f46; }
.status-cancelled { background: #fee2e2; color: #991b1b; }

.order-card-body { padding: 10px 12px; }

.order-item-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.78rem;
  color: #4b5563;
  padding: 2px 0;
}
.order-item-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
  margin-right: 8px;
}
.order-item-qty { font-weight: 600; flex-shrink: 0; }
.order-more { font-size: 0.72rem; color: #9ca3af; margin: 4px 0 0; }

.order-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px dashed #e5e7eb;
  font-size: 0.82rem;
  color: #374151;
}
.order-total strong { color: #dc2626; font-size: 0.9rem; }

/* ==================== COUPON CARDS ==================== */
.coupon-cards {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 10px;
}

.coupon-card {
  padding: 10px 12px;
  border-radius: 10px;
  background: linear-gradient(135deg, #fff7ed, #fffbeb);
  border: 1px dashed #fdba74;
}

.coupon-code {
  font-size: 0.85rem;
  font-weight: 800;
  color: #c2410c;
  letter-spacing: 0.5px;
}

.coupon-desc {
  font-size: 0.78rem;
  color: #78350f;
  margin-top: 2px;
  font-weight: 500;
}

.coupon-meta {
  display: flex;
  justify-content: space-between;
  font-size: 0.68rem;
  color: #92400e;
  margin-top: 6px;
  opacity: 0.8;
}

/* ==================== TYPING INDICATOR ==================== */
.typing-bubble {
  padding: 12px 18px !important;
}

.typing-indicator {
  display: flex;
  gap: 4px;
  align-items: center;
}

.typing-indicator span {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #9ca3af;
  animation: typing-dot 1.4s ease-in-out infinite;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing-dot {
  0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
  30% { transform: translateY(-6px); opacity: 1; }
}

/* ==================== QUICK ACTIONS ==================== */
.quick-actions {
  display: flex;
  flex-wrap: nowrap;
  overflow-x: auto;
  gap: 6px;
  padding: 8px 12px;
  background: #f8fafc;
  border-top: 1px solid #f0f0f0;
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.quick-actions::-webkit-scrollbar {
  display: none;
}

.quick-slide-enter-active, .quick-slide-leave-active {
  transition: all 0.25s ease;
  max-height: 50px;
  overflow: hidden;
}
.quick-slide-enter-from, .quick-slide-leave-to {
  max-height: 0;
  padding-top: 0;
  padding-bottom: 0;
  opacity: 0;
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 20px;
  border: 1px solid #e5e7eb;
  background: var(--card-bg);
  font-size: 0.76rem;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
  white-space: nowrap;
  flex-shrink: 0;
}

/* Toggle button */
.quick-toggle-btn {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 1.5px solid #e5e7eb;
  background: #f8fafc;
  color: #9ca3af;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s;
}
.quick-toggle-btn:hover {
  border-color: #93c5fd;
  color: var(--primary);
  background: #eff6ff;
}
.quick-toggle-btn.active {
  border-color: var(--primary);
  color: var(--primary);
  background: #eff6ff;
}

.quick-action-btn:hover {
  background: #eff6ff;
  border-color: #93c5fd;
  color: var(--primary);
}

.quick-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 14px;
  height: 14px;
}

/* ==================== INPUT AREA ==================== */
.chat-input-area {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid #e5e7eb;
  background: var(--card-bg);
  flex-shrink: 0;
}

.chat-input {
  flex: 1;
  padding: 10px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  font-size: 0.88rem;
  font-family: inherit;
  color: #1f2937;
  background: #f8fafc;
  outline: none;
  transition: border-color 0.2s;
  resize: none;
  overflow-y: auto;
  min-height: 40px;
  max-height: 120px;
  line-height: 1.4;
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none; /* IE / Edge */
}

.chat-input::-webkit-scrollbar {
  display: none; /* Chrome, Safari, Opera */
  width: 0;
  height: 0;
}

.chat-input:focus {
  border-color: var(--primary);
  background: var(--card-bg);
}

.chat-input::placeholder { color: #9ca3af; }
.chat-input:disabled { opacity: 0.6; cursor: not-allowed; }

.chat-send-btn {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  border: none;
  background: var(--primary);
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s;
}

.chat-send-btn:hover:not(:disabled) {
  background: #C4305D;
  transform: scale(1.05);
}

.chat-send-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* ==================== PRODUCT DETAIL CARD ==================== */
.product-detail-card {
  margin-top: 10px;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  background: #f8fafc;
  overflow: hidden;
}

.pd-header {
  display: flex;
  gap: 12px;
  padding: 12px;
}

.pd-img {
  width: 72px;
  height: 72px;
  border-radius: 10px;
  object-fit: cover;
  background: #f1f5f9;
  flex-shrink: 0;
}

.pd-main-info {
  flex: 1;
  min-width: 0;
}

.pd-name {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text-main);
  margin: 0 0 4px;
  line-height: 1.3;
}

.pd-price {
  font-size: 0.88rem;
  font-weight: 700;
  color: #dc2626;
  margin: 0 0 4px;
}

.pd-desc {
  font-size: 0.78rem;
  color: #4b5563;
  padding: 0 12px 8px;
  margin: 0;
  line-height: 1.45;
}

.pd-variants {
  padding: 0 12px 8px;
}

.pd-variants-title {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  margin: 0 0 6px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.pd-variant-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  font-size: 0.78rem;
  border-bottom: 1px solid #f0f0f0;
}
.pd-variant-row:last-child { border-bottom: none; }

.pd-variant-name {
  flex: 1;
  color: #374151;
  font-weight: 500;
}

.pd-variant-price {
  color: #dc2626;
  font-weight: 600;
}

.pd-variant-status {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
}
.pd-variant-status.in-stock { background: #d1fae5; color: #065f46; }
.pd-variant-status.out-stock { background: #fee2e2; color: #991b1b; }

.pd-view-btn {
  display: block;
  width: 100%;
  padding: 10px;
  border: none;
  background: var(--primary);
  color: #fff;
  font-size: 0.82rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.2s;
}
.pd-view-btn:hover {
  background: #C4305D;
}

/* ==================== CATEGORY CARDS ==================== */
.category-cards {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 10px;
}

.category-card {
  padding: 10px 12px;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  cursor: pointer;
  transition: all 0.2s;
}
.category-card:hover {
  background: #eff6ff;
  border-color: #93c5fd;
}

.cat-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.cat-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-main);
}

.cat-count {
  font-size: 0.72rem;
  color: #6b7280;
  background: #f1f5f9;
  padding: 2px 8px;
  border-radius: 12px;
}

.cat-children {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 6px;
}

.cat-child-tag {
  font-size: 0.7rem;
  color: var(--primary);
  background: #dbeafe;
  padding: 2px 8px;
  border-radius: 10px;
}

/* ==================== RESPONSIVE ==================== */
@media (max-width: 1024px) and (min-width: 769px) {
  .chatbot-wrapper {
    bottom: 20px;
    right: 20px;
  }
  .chatbot-bubble {
    width: 44px;
    height: 44px;
  }
  .chatbot-bubble svg {
    width: 20px;
    height: 20px;
  }
  .chatbot-window {
    width: 380px;
    height: 540px;
    bottom: 60px;
    right: 0;
  }
}

@media (max-width: 768px) {
  .chatbot-wrapper {
    bottom: 20px;
    right: 16px;
    left: auto;
  }
  .chatbot-wrapper.on-product-detail {
    bottom: 78px;
    right: 14px;
  }
  .chatbot-bubble {
    width: 40px;
    height: 40px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  }
  .chatbot-bubble svg {
    width: 19px;
    height: 19px;
  }

  /* Khi Chatbot mở trên Mobile: Full Bottom Sheet Drawer Modal chuẩn app */
  .chatbot-wrapper.is-open {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100dvh;
    bottom: 0;
    left: 0;
    right: 0;
    top: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    align-items: stretch;
    z-index: 100000;
  }

  .chatbot-wrapper.is-open .chatbot-bubble {
    display: none !important;
  }

  .chatbot-window {
    position: relative;
    width: 100vw;
    height: 88dvh;
    max-height: 88dvh;
    bottom: 0;
    left: 0;
    right: 0;
    border-radius: 20px 20px 0 0;
    border-left: none;
    border-right: none;
    border-bottom: none;
    box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
  }

  .chatbot-wrapper.on-product-detail .chatbot-window {
    bottom: 0;
    height: 88dvh;
  }

  .chat-header {
    border-radius: 20px 20px 0 0;
    padding: 14px 16px;
  }

  .chat-messages {
    flex: 1;
    padding: 12px 14px;
  }

  .chat-input-area {
    padding: 10px 12px calc(10px + env(safe-area-inset-bottom, 0px));
  }
}
</style>
