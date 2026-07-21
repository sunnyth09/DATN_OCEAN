import os, re

path = 'src/Pages/Client/Profile/ProfileLoyalty.vue'
with open(path, 'r', encoding='utf-8') as f:
    text = f.read()

# 1. Add states
script_state = '''
const rewards = ref([]);
const loadingRewards = ref(false);
const isRedeeming = ref(null);
'''
text = re.sub(r'(const currentPage = ref\(1\);)', r'\1\n' + script_state, text)

# 2. Add fetchRewards and redeemReward methods
script_methods = '''
const fetchRewards = async () => {
  loadingRewards.value = true;
  try {
    const res = await loyaltyService.getRewards();
    if (res.data?.status === 'success') {
      rewards.value = res.data.data;
    }
  } catch (e) {
    console.error('Fetch rewards error:', e);
  } finally {
    loadingRewards.value = false;
  }
};

const redeemReward = async (id) => {
  if (confirm('Bạn có chắc muốn đổi quà này?')) {
    isRedeeming.value = id;
    try {
      const res = await loyaltyService.redeem(id);
      if (res.data?.status === 'success') {
        showToast('Đổi quà thành công!', 'success');
        fetchSummary(); // reload points
        fetchHistory(1); // reload history
      } else {
        showToast(res.data?.message || 'Đổi quà thất bại', 'error');
      }
    } catch (e) {
      showToast(e.response?.data?.message || 'Có lỗi xảy ra', 'error');
    } finally {
      isRedeeming.value = null;
    }
  }
};
'''
text = re.sub(r'(const fetchSummary = async \(\) => \{)', script_methods + r'\n\1', text)

# 3. Call fetchRewards in onMounted
text = re.sub(r'(await Promise\.all\(\[fetchSummary\(\), fetchHistory\(\)\]\);)', r'await Promise.all([fetchSummary(), fetchHistory(), fetchRewards()]);', text)

# 4. Inject template
template = '''
      <!-- ── ĐỔI QUÀ (REWARDS) ─────────────────────────────────── -->
      <div class="lp-section mt-4">
        <div class="lp-section-header">
          <span class="lp-section-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6"/><path d="M22 7v5H2V7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
          </span>
          <h3 class="lp-section-title">Đổi Quà Tặng</h3>
        </div>
        
        <div class="lp-rewards-grid">
          <div v-if="loadingRewards" class="lp-loading">
            <div class="lp-spinner"></div>
          </div>
          <div v-else-if="rewards.length === 0" class="text-center text-muted p-4 w-100">
            Hiện chưa có quà tặng nào để đổi.
          </div>
          <div v-else class="lp-reward-card" v-for="reward in rewards" :key="reward.id">
            <div class="lp-reward-icon" :style="{ background: reward.type === 'voucher' ? '#10b98118' : '#3b82f618', color: reward.type === 'voucher' ? '#10b981' : '#3b82f6' }">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            </div>
            <div class="lp-reward-info">
              <h4>{{ reward.name }}</h4>
              <p>{{ reward.description || 'Quà tặng từ hệ thống' }}</p>
              <div class="lp-reward-bottom">
                <span class="lp-reward-points">{{ formatPoints(reward.points_required) }} điểm</span>
                <button 
                  class="lp-btn lp-btn-primary lp-btn-sm" 
                  :disabled="currentBalance < reward.points_required || isRedeeming === reward.id"
                  @click="redeemReward(reward.id)"
                >
                  <span v-if="isRedeeming === reward.id" class="spinner-border spinner-border-sm me-1"></span>
                  Đổi ngay
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

'''
text = re.sub(r'(<!-- ── LỊCH SỬ GIAO DỊCH ─────────────────────────────────── -->)', template + r'\1', text)

# 5. Add CSS
css = '''
.lp-rewards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
  margin-top: 15px;
}
.lp-reward-card {
  display: flex;
  align-items: center;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px;
  transition: all 0.2s ease;
}
.lp-reward-card:hover {
  border-color: #cbd5e1;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.lp-reward-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 16px;
  flex-shrink: 0;
}
.lp-reward-info {
  flex: 1;
}
.lp-reward-info h4 {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
}
.lp-reward-info p {
  margin: 0 0 10px;
  font-size: 13px;
  color: #64748b;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.lp-reward-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.lp-reward-points {
  font-weight: 700;
  color: #ef4444;
  font-size: 14px;
}
.lp-btn-primary {
  background: #E63B6F;
  color: white;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 13px;
  cursor: pointer;
}
.lp-btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
'''
text = re.sub(r'(</style>)', css + r'\n\1', text)

with open(path, 'w', encoding='utf-8') as f:
    f.write(text)

print('Success!')
