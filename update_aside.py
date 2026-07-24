import sys
import re

file_path = 'frontend/src/components/AdminAside.vue'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace variables
new_vars = '''
const userRoleRaw = ref('');

const openMenus = reactive({
  business: false,
  inventory: false,
  court: false,
  marketing: false,
  finance: false,
  care: false,
  content: false,
  staff: false
});

const userInitial = computed(() => (userName.value?.[0] || 'A').toUpperCase());

const toggleSidebar = () => {
  uiStore.toggleBackofficeSidebar();
};

const handleSubmenuClick = (menu) => {
  if (props.collapsed) {
    uiStore.toggleBackofficeSidebar();
    openMenus[menu] = true;
  } else {
    openMenus[menu] = !openMenus[menu];
  }
};
'''

content = re.sub(
    r'const userRoleRaw = ref\(\'\'\);.*?const handleSubmenuClick = \(menu\) => {.*?};', 
    new_vars.strip(), 
    content, 
    flags=re.DOTALL
)

if 'reactive' not in content:
    content = content.replace('computed, onMounted, ref', 'computed, onMounted, reactive, ref')

nav_match = re.search(r'<nav class=\"sidebar-nav\">(.*?)</nav>', content, flags=re.DOTALL)
if nav_match:
    new_nav = '''<nav class="sidebar-nav">
      <!-- Dashboard -->
      <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin" class="nav-item" exact-active-class="nav-item--active">
        <div class="nav-icon">
          <AppIcon name="dashboard" />
        </div>
        <span>Dashboard</span>
      </router-link>

      <!-- Kinh doanh -->
      <div v-if="['admin', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('business')" :class="{ 'nav-item--open': openMenus.business }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        </div>
        <span>Kinh doanh</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.business }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.business && ['admin', 'seller'].includes(userRoleRaw)" class="nav-submenu">
          <router-link to="/admin/pos" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Bán hàng (POS)</span>
          </router-link>
          <router-link to="/admin/order" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Ðon hàng</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/return-requests" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Hoàn hàng</span>
          </router-link>
        </div>
      </transition>

      <!-- Kho & S?n ph?m -->
      <div v-if="['admin', 'staff', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('inventory')" :class="{ 'nav-item--open': openMenus.inventory }">
        <div class="nav-icon">
          <AppIcon name="store" />
        </div>
        <span>Kho & S?n ph?m</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.inventory }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.inventory" class="nav-submenu">
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/product" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>S?n ph?m</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/category" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot\"></span><span>Danh m?c</span>
          </router-link>
        </div>
      </transition>

      <!-- Sân C?u Lông -->
      <div v-if="['admin', 'staff', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('court')" :class="{ 'nav-item--open': openMenus.court }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <path d="M12 3v18"></path>
          </svg>
        </div>
        <span>Sân C?u Lông</span>
        <svg class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.court }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.court" class="nav-submenu">
          <router-link to="/admin/court-dashboard" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Dashboard L? Tân</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/courts" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>H? th?ng sân</span>
          </router-link>
          <router-link to="/admin/court-bookings" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Qu?n lý Ð?t Sân</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/court-reports" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Báo Cáo Th?ng Kê</span>
          </router-link>
        </div>
      </transition>

      <!-- Marketing -->
      <div v-if="['admin', 'staff', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('marketing')" :class="{ 'nav-item--open': openMenus.marketing }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
        </div>
        <span>Marketing</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.marketing }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.marketing" class="nav-submenu">
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/coupon" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Mã gi?m giá</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/rewards" class=\"submenu-item\" active-class=\"submenu-item--active\">
            <span class=\"submenu-dot\"></span><span>Quà t?ng Loyalty</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/user-rewards" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>L?ch s? d?i quà</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/flash-sale" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Flash Sale</span>
          </router-link>
        </div>
      </transition>

      <!-- Tài chính -->
      <div v-if="['admin'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('finance')" :class="{ 'nav-item--open': openMenus.finance }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
        </div>
        <span>Tài chính</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.finance }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.finance" class="nav-submenu">
          <router-link to="/admin/wallet-deposits" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Duy?t n?p ti?n</span>
          </router-link>
          <router-link to="/admin/wallet-withdrawals" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Duy?t rút ti?n</span>
          </router-link>
          <router-link to="/admin/stats" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Th?ng kê</span>
          </router-link>
        </div>
      </transition>

      <!-- Cham sóc Khách hàng -->
      <div v-if="['admin', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('care')" :class="{ 'nav-item--open': openMenus.care }">
        <div class="nav-icon">
          <AppIcon name="chat" />
        </div>
        <span>Cham sóc Khách hàng</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.care }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.care" class="nav-submenu">
          <router-link to="/admin/users" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Khách hàng</span>
          </router-link>
          <router-link to="/admin/review" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Ðánh giá</span>
          </router-link>
          <router-link to="/admin/tickets" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Khi?u n?i</span>
          </router-link>
          <router-link to="/admin/chat" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Chat</span>
          </router-link>
        </div>
      </transition>

      <!-- N?i dung -->
      <div v-if="['admin'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('content')" :class="{ 'nav-item--open': openMenus.content }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        </div>
        <span>N?i dung</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.content }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.content" class="nav-submenu">
          <router-link to="/admin/post" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Bài vi?t</span>
          </router-link>
          <router-link to="/admin/post-category" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Danh m?c bài vi?t</span>
          </router-link>
        </div>
      </transition>

      <!-- Nhân s? -->
      <div v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('staff')" :class="{ 'nav-item--open': openMenus.staff }">
        <div class="nav-icon">
          <AppIcon name="users" />
        </div>
        <span>Nhân s?</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.staff }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.staff" class="nav-submenu">
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/staff" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Danh sách nhân s?</span>
          </router-link>
          <router-link to="/admin/attendance" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Ch?m công</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/attendance-list" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>L?ch s? ch?m công</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/work-locations" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Chi nhánh</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/work-shifts" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Ca làm vi?c & Phân ca</span>
          </router-link>
          <router-link to="/admin/face-register" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Ðang ký khuôn m?t</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/face-management" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Qu?n lý khuôn m?t</span>
          </router-link>
        </div>
      </transition>
    </nav>'''
    content = content[:nav_match.start()] + new_nav + content[nav_match.end():]

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('SUCCESS')
