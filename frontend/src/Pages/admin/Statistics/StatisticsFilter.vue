<template>
  <div class="statistics-filter ocean-card">
    <div class="filter-group">
      <label>Thời gian</label>
      <div class="preset-buttons">
        <button 
          v-for="preset in presets" 
          :key="preset.value"
          class="btn-preset"
          :class="{ active: currentPreset === preset.value }"
          @click="selectPreset(preset.value)"
        >
          {{ preset.label }}
        </button>
      </div>
    </div>
    
    <div class="filter-group custom-range" v-if="currentPreset === 'custom'">
      <div>
        <label>Từ ngày</label>
        <input type="date" v-model="localFilters.start_date" class="form-input" @change="onCustomChange" />
      </div>
      <div>
        <label>Đến ngày</label>
        <input type="date" v-model="localFilters.end_date" class="form-input" @change="onCustomChange" />
      </div>
    </div>



  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps(['modelValue']);
const emit = defineEmits(['update:modelValue', 'apply']);

const currentPreset = ref('30days');
const localFilters = ref({
  preset: '30days',
  start_date: '',
  end_date: ''
});

const presets = [
  { label: 'Hôm nay', value: 'today' },
  { label: '7 Ngày', value: '7days' },
  { label: '30 Ngày', value: '30days' },
  { label: 'Tháng này', value: 'this_month' },
  { label: 'Năm nay', value: 'this_year' },
  { label: 'Tùy chỉnh', value: 'custom' },
];

const selectPreset = (val) => {
  currentPreset.value = val;
  localFilters.value.preset = val;
  if (val !== 'custom') {
    localFilters.value.start_date = '';
    localFilters.value.end_date = '';
    applyFilters();
  }
};

const onCustomChange = () => {
  // Option: wait for explicit apply click
};

const applyFilters = () => {
  emit('update:modelValue', { ...localFilters.value });
  emit('apply');
};

const resetFilters = () => {
  selectPreset('30days');
};
</script>

<style scoped>
.statistics-filter {
  padding: 20px 24px;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 20px;
  background: var(--card-bg);
  margin-bottom: 24px;
}

.filter-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-muted);
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.preset-buttons {
  display: flex;
  gap: 8px;
}

.btn-preset {
  padding: 8px 16px;
  border: 1px solid var(--border-color);
  background: var(--surface-container-low);
  color: var(--text-main);
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-preset:hover {
  background: var(--hover-bg);
  border-color: rgba(230, 59, 111, 0.3);
}

.btn-preset.active {
  background: #E63B6F;
  color: white;
  border-color: #E63B6F;
  box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2);
}

.custom-range {
  display: flex;
  gap: 12px;
}

.form-input {
  padding: 8px 12px;
  border: 1px solid var(--border-color);
  background: var(--background);
  color: var(--text-main);
  border-radius: 8px;
  font-family: inherit;
  font-size: 0.9rem;
  outline: none;
  transition: all 0.2s;
}
.form-input:focus {
  border-color: #E63B6F;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

.filter-actions {
  display: flex;
  gap: 12px;
  margin-left: auto;
}

.btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #E63B6F;
  color: white;
  border: none;
}
.btn-primary:hover {
  background: var(--ocean-bright);
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.25);
  transform: translateY(-1px);
}

.btn-outline {
  background: transparent;
  color: var(--text-muted);
  border: 1px solid var(--border-color);
}
.btn-outline:hover {
  background: var(--hover-bg);
  color: var(--text-main);
}
</style>
