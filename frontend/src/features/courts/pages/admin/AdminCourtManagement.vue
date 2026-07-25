<script setup>
import { ref, onMounted, computed } from 'vue';
import { useCourtBookingStore } from '@/features/courts/stores/useCourtBookingStore';
import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';
import '@/features/courts/assets/court-management.css';

const toast = {
    success: (msg) => Swal.fire({ icon: 'success', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    error: (msg) => Swal.fire({ icon: 'error', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    warning: (msg) => Swal.fire({ icon: 'warning', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    info: (msg) => Swal.fire({ icon: 'info', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 })
};

const store = useCourtBookingStore();
const activeTab = ref('courts');

// Models for Modals
const courtForm = ref({ court_name: '', court_code: '', description: '', type: 'standard', status: 'active' });
const priceForm = ref({ court_id: '', price_name: '', day_type: 'weekday', from_time: '05:00', to_time: '23:00', price_per_hour: 0, is_active: true });
const serviceForm = ref({ name: '', description: '', price: 0, status: 'active' });
const scheduleForm = ref({ court_id: '', day_of_week: 1, open_time: '05:00', close_time: '22:00', is_active: true });
const maintenanceForm = ref({ court_id: '', title: '', description: '', start_datetime: '', end_datetime: '', status: 'scheduled' });

const editingId = ref(null);

onMounted(async () => {
    loadTabContent();
});

const loadTabContent = async () => {
    if(activeTab.value === 'courts') await store.fetchAdminCourts();
    else if(activeTab.value === 'schedules') {
        await store.fetchAdminCourts();
        await store.fetchSchedules();
    }
    else if(activeTab.value === 'prices') {
        await store.fetchAdminCourts();
        await store.fetchPrices();
    }
    else if(activeTab.value === 'services') await store.fetchServices();
    else if(activeTab.value === 'maintenances') {
        await store.fetchAdminCourts();
        await store.fetchMaintenances();
    }
};

const switchTab = (tab) => {
    activeTab.value = tab;
    loadTabContent();
};

const formatCurrency = (val) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);

const getStatusClass = (status) => {
    const map = { active: 'active', inactive: 'inactive', maintenance: 'maintenance', closed: 'closed' };
    return map[status] || 'inactive';
};

const getStatusLabel = (status) => {
    const map = { active: 'Hoạt động', inactive: 'Không hoạt động', maintenance: 'Bảo trì', closed: 'Đóng cửa' };
    return map[status] || status;
};

const getTypeLabel = (type) => {
    const map = { standard: 'Tiêu chuẩn', vip: 'VIP', outdoor: 'Ngoài trời', indoor: 'Trong nhà' };
    return map[type] || type;
};

const getTypeIcon = (type) => {
    const map = { standard: 'bi-layers', vip: 'bi-star-fill', outdoor: 'bi-sun', indoor: 'bi-house-door' };
    return map[type] || 'bi-layers';
};

const getDayTypeLabel = (type) => {
    const map = { weekday: 'Ngày thường', weekend: 'Cuối tuần', holiday: 'Ngày lễ', all: 'Tất cả' };
    return map[type] || type;
};

const getDayOfWeekLabel = (day) => {
    const map = { 0: 'Chủ nhật', 1: 'Thứ hai', 2: 'Thứ ba', 3: 'Thứ tư', 4: 'Thứ năm', 5: 'Thứ sáu', 6: 'Thứ bảy' };
    return map[day] ?? `Ngày ${day}`;
};

const getMaintenanceStatusLabel = (s) => {
    const map = { scheduled: 'Đã lên lịch', in_progress: 'Đang tiến hành', completed: 'Hoàn thành', cancelled: 'Đã hủy' };
    return map[s] || s;
};

const getMaintenanceStatusClass = (s) => {
    const map = { scheduled: 'maintenance', in_progress: 'active', completed: 'closed', cancelled: 'inactive' };
    return map[s] || 'inactive';
};

const getCourtName = (courtId) => {
    const court = store.courts.find(c => (c.court_id || c.id) == courtId);
    return court ? court.court_name || court.name : `Sân #${courtId}`;
};

const formatDateTime = (dt) => {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

// Court stats
const courtStats = () => {
    const courts = store.courts || [];
    return {
        total: courts.length,
        active: courts.filter(c => c.status === 'active').length,
        maintenance: courts.filter(c => c.status === 'maintenance').length,
        closed: courts.filter(c => c.status === 'closed' || c.status === 'inactive').length
    };
};

// ==== COURTS CRUD ====
const openCourtModal = (court = null) => {
    if(court) {
        editingId.value = court.court_id || court.id;
        courtForm.value = { ...court };
    } else {
        editingId.value = null;
        courtForm.value = { court_name: '', court_code: '', description: '', type: 'standard', status: 'active' };
    }
    new Modal(document.getElementById('courtModal')).show();
};

const saveCourt = async () => {
    try {
        if(editingId.value) {
            await store.updateAdminCourt(editingId.value, courtForm.value);
            toast.success('Cập nhật sân thành công');
        } else {
            await store.createAdminCourt(courtForm.value);
            toast.success('Thêm sân thành công');
        }
        Modal.getInstance(document.getElementById('courtModal')).hide();
        loadTabContent();
    } catch (e) {}
};

const deleteCourt = async (id) => {
    const result = await Swal.fire({
        title: 'Xác nhận xóa sân?',
        text: 'Sẽ xóa toàn bộ dữ liệu liên quan (bảng giá, lịch đặt...)',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if(result.isConfirmed) {
        try {
            await store.deleteAdminCourt(id);
            toast.success('Đã xóa sân');
            loadTabContent();
        } catch(e) {}
    }
};

// ==== SCHEDULES CRUD ====
const openScheduleModal = (schedule = null) => {
    if(schedule) {
        editingId.value = schedule.schedule_id || schedule.id;
        scheduleForm.value = { 
            court_id: schedule.court_id,
            day_of_week: schedule.day_of_week,
            open_time: schedule.open_time?.substring(0, 5),
            close_time: schedule.close_time?.substring(0, 5),
            is_active: schedule.is_active ?? true
        };
    } else {
        editingId.value = null;
        scheduleForm.value = { court_id: '', day_of_week: 1, open_time: '05:00', close_time: '22:00', is_active: true };
    }
    new Modal(document.getElementById('scheduleModal')).show();
};

const saveSchedule = async () => {
    try {
        if(editingId.value) {
            await store.updateSchedule(editingId.value, scheduleForm.value);
            toast.success('Cập nhật lịch thành công');
        } else {
            await store.createSchedule(scheduleForm.value);
            toast.success('Thêm lịch thành công');
        }
        Modal.getInstance(document.getElementById('scheduleModal')).hide();
        loadTabContent();
    } catch (e) {}
};

const deleteSchedule = async (id) => {
    const result = await Swal.fire({
        title: 'Xóa lịch hoạt động?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if(result.isConfirmed) {
        try {
            await store.deleteSchedule(id);
            toast.success('Đã xóa lịch hoạt động');
            loadTabContent();
        } catch(e) {}
    }
};

// ==== PRICES CRUD ====
const openPriceModal = (price = null) => {
    if(price) {
        editingId.value = price.price_id || price.id;
        priceForm.value = { 
            court_id: price.court_id,
            price_name: price.price_name || '',
            day_type: price.day_type || 'weekday',
            from_time: (price.from_time || price.start_time)?.substring(0, 5) || '05:00',
            to_time: (price.to_time || price.end_time)?.substring(0, 5) || '23:00',
            price_per_hour: price.price_per_hour || 0,
            is_active: price.is_active ?? true
        };
    } else {
        editingId.value = null;
        priceForm.value = { court_id: '', price_name: '', day_type: 'weekday', from_time: '05:00', to_time: '23:00', price_per_hour: 0, is_active: true };
    }
    new Modal(document.getElementById('priceModal')).show();
};

const savePrice = async () => {
    try {
        if(editingId.value) {
            await store.updatePrice(editingId.value, priceForm.value);
            toast.success('Cập nhật bảng giá thành công');
        } else {
            await store.createPrice(priceForm.value);
            toast.success('Thêm bảng giá thành công');
        }
        Modal.getInstance(document.getElementById('priceModal')).hide();
        loadTabContent();
    } catch (e) {}
};

const deletePrice = async (id) => {
    const result = await Swal.fire({
        title: 'Xóa cấu hình giá?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if(result.isConfirmed) {
        try {
            await store.deletePrice(id);
            toast.success('Đã xóa cấu hình giá');
            loadTabContent();
        } catch(e) {}
    }
};

// ==== SERVICES CRUD ====
const openServiceModal = (service = null) => {
    if(service) {
        editingId.value = service.service_id || service.id;
        serviceForm.value = { ...service };
    } else {
        editingId.value = null;
        serviceForm.value = { name: '', description: '', price: 0, status: 'active' };
    }
    new Modal(document.getElementById('serviceModal')).show();
};

const saveService = async () => {
    try {
        if(editingId.value) {
            await store.updateService(editingId.value, serviceForm.value);
            toast.success('Cập nhật dịch vụ thành công');
        } else {
            await store.createService(serviceForm.value);
            toast.success('Thêm dịch vụ thành công');
        }
        Modal.getInstance(document.getElementById('serviceModal')).hide();
        loadTabContent();
    } catch (e) {}
};

const deleteService = async (id) => {
    const result = await Swal.fire({
        title: 'Xóa dịch vụ này?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if(result.isConfirmed) {
        try {
            await store.deleteService(id);
            toast.success('Đã xóa dịch vụ');
            loadTabContent();
        } catch(e) {}
    }
};

// ==== MAINTENANCES CRUD ====
const openMaintenanceModal = (m = null) => {
    if(m) {
        editingId.value = m.maintenance_id || m.id;
        maintenanceForm.value = {
            court_id: m.court_id,
            title: m.title,
            description: m.description || '',
            start_datetime: m.start_datetime?.substring(0, 16) || '',
            end_datetime: m.end_datetime?.substring(0, 16) || '',
            status: m.status || 'scheduled',
        };
    } else {
        editingId.value = null;
        maintenanceForm.value = { court_id: '', title: '', description: '', start_datetime: '', end_datetime: '', status: 'scheduled' };
    }
    new Modal(document.getElementById('maintenanceModal')).show();
};

const saveMaintenance = async () => {
    try {
        if(editingId.value) {
            await store.updateMaintenance(editingId.value, maintenanceForm.value);
            toast.success('Cập nhật lịch bảo trì thành công');
        } else {
            await store.createMaintenance(maintenanceForm.value);
            toast.success('Thêm lịch bảo trì thành công');
        }
        Modal.getInstance(document.getElementById('maintenanceModal')).hide();
        loadTabContent();
    } catch (e) {}
};

const deleteMaintenance = async (id) => {
    const result = await Swal.fire({
        title: 'Xóa lịch bảo trì?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if(result.isConfirmed) {
        try {
            await store.deleteMaintenance(id);
            toast.success('Đã xóa lịch bảo trì');
            loadTabContent();
        } catch(e) {}
    }
};
</script>

<template>
    <div class="court-management-page">
        <!-- Page Header -->
        <div class="court-section-header">
            <div>
                <h2 class="court-section-title">
                    <i class="bi bi-buildings"></i>
                    Cấu Hình & Quản Lý Sân
                </h2>
                <p class="court-section-subtitle">Quản lý hệ thống 7 sân cầu lông, lịch hoạt động, bảng giá, dịch vụ và bảo trì</p>
            </div>
            <button v-if="activeTab === 'courts'" class="court-action-btn court-action-btn--primary" @click="openCourtModal()">
                <i class="bi bi-plus-lg"></i> Thêm Sân Mới
            </button>
            <button v-if="activeTab === 'schedules'" class="court-action-btn court-action-btn--primary" @click="openScheduleModal()">
                <i class="bi bi-plus-lg"></i> Thêm Lịch
            </button>
            <button v-if="activeTab === 'prices'" class="court-action-btn court-action-btn--primary" @click="openPriceModal()">
                <i class="bi bi-plus-lg"></i> Thêm Bảng Giá
            </button>
            <button v-if="activeTab === 'services'" class="court-action-btn court-action-btn--primary" @click="openServiceModal()">
                <i class="bi bi-plus-lg"></i> Thêm Dịch Vụ
            </button>
            <button v-if="activeTab === 'maintenances'" class="court-action-btn court-action-btn--primary" @click="openMaintenanceModal()">
                <i class="bi bi-plus-lg"></i> Thêm Lịch Bảo Trì
            </button>
        </div>

        <!-- Tab Navigation -->
        <div class="court-tabs">
            <button class="court-tab" :class="{ 'court-tab--active': activeTab === 'courts' }" @click="switchTab('courts')">
                <i class="bi bi-grid-3x3-gap me-1"></i> Danh Sách Sân
            </button>
            <button class="court-tab" :class="{ 'court-tab--active': activeTab === 'schedules' }" @click="switchTab('schedules')">
                <i class="bi bi-calendar-week me-1"></i> Lịch Hoạt Động
            </button>
            <button class="court-tab" :class="{ 'court-tab--active': activeTab === 'prices' }" @click="switchTab('prices')">
                <i class="bi bi-currency-dollar me-1"></i> Bảng Giá
            </button>
            <button class="court-tab" :class="{ 'court-tab--active': activeTab === 'services' }" @click="switchTab('services')">
                <i class="bi bi-bag-plus me-1"></i> Dịch Vụ
            </button>
            <button class="court-tab" :class="{ 'court-tab--active': activeTab === 'maintenances' }" @click="switchTab('maintenances')">
                <i class="bi bi-tools me-1"></i> Bảo Trì
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="store.loading" class="court-card-grid">
            <div v-for="n in 4" :key="n" class="court-card">
                <div class="court-skeleton" style="height: 20px; width: 60%; margin-bottom: 12px;"></div>
                <div class="court-skeleton" style="height: 14px; width: 40%; margin-bottom: 20px;"></div>
                <div class="court-skeleton" style="height: 36px; width: 100%;"></div>
            </div>
        </div>

        <!-- ═══════════ COURTS TAB ═══════════ -->
        <template v-if="activeTab === 'courts' && !store.loading">
            <!-- Stats Bar -->
            <div class="court-stats-bar">
                <div class="court-stat-card">
                    <div class="court-stat-card__value" style="color: var(--court-primary);">{{ courtStats().total }}</div>
                    <div class="court-stat-card__label">Tổng số sân</div>
                </div>
                <div class="court-stat-card">
                    <div class="court-stat-card__value" style="color: var(--court-available);">{{ courtStats().active }}</div>
                    <div class="court-stat-card__label">Đang hoạt động</div>
                </div>
                <div class="court-stat-card">
                    <div class="court-stat-card__value" style="color: var(--court-maintenance);">{{ courtStats().maintenance }}</div>
                    <div class="court-stat-card__label">Đang bảo trì</div>
                </div>
                <div class="court-stat-card">
                    <div class="court-stat-card__value" style="color: var(--court-closed);">{{ courtStats().closed }}</div>
                    <div class="court-stat-card__label">Đóng cửa</div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!store.courts || store.courts.length === 0" class="court-empty-state">
                <div class="court-empty-state__icon"><i class="bi bi-building-slash"></i></div>
                <div class="court-empty-state__title">Chưa có sân nào</div>
                <div class="court-empty-state__text">Hãy thêm sân đầu tiên để bắt đầu quản lý hệ thống</div>
                <button class="court-action-btn court-action-btn--primary mt-3" @click="openCourtModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Sân Mới
                </button>
            </div>

            <!-- Court Cards Grid -->
            <div v-else class="court-card-grid">
                <div
                    v-for="court in store.courts"
                    :key="court.court_id || court.id"
                    class="court-card"
                    :class="'court-card--' + getStatusClass(court.status)"
                >
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="fw-bold mb-1" style="font-size: 1.1rem;">{{ court.court_name }}</h5>
                            <span class="status-badge" :class="'status-badge--' + getStatusClass(court.status)">
                                <span class="pulse-dot" :class="'pulse-dot--' + getStatusClass(court.status)"></span>
                                {{ getStatusLabel(court.status) }}
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-1 px-2 py-1 rounded-pill" style="background: var(--court-section-bg); font-size: 0.75rem; font-weight: 600;">
                            <i :class="getTypeIcon(court.type)" style="font-size: 0.7rem;"></i>
                            {{ getTypeLabel(court.type) }}
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2 mb-3 px-3 py-2 rounded-3" style="background: var(--court-section-bg); border: 1px dashed var(--border-color, rgba(0,0,0,0.08));">
                        <i class="bi bi-qr-code text-muted" style="font-size: 0.85rem;"></i>
                        <span style="font-size: 0.8rem; font-weight: 600; letter-spacing: 0.03em;" class="text-muted">{{ court.court_code }}</span>
                    </div>

                    <p v-if="court.description" class="text-muted mb-0 flex-grow-1" style="font-size: 0.85rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        {{ court.description }}
                    </p>
                    <p v-else class="text-muted mb-0 flex-grow-1 fst-italic" style="font-size: 0.85rem;">Chưa có mô tả</p>

                    <div class="d-flex gap-2 mt-3 pt-3" style="border-top: 1px solid var(--border-color, rgba(0,0,0,0.06));">
                        <button class="court-action-btn court-action-btn--outline flex-fill" @click="openCourtModal(court)">
                            <i class="bi bi-pencil"></i> Sửa
                        </button>
                        <button class="court-action-btn court-action-btn--danger" @click="deleteCourt(court.court_id || court.id)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ═══════════ SCHEDULES TAB ═══════════ -->
        <template v-if="activeTab === 'schedules' && !store.loading">
            <div v-if="!store.schedules || store.schedules.length === 0" class="court-empty-state">
                <div class="court-empty-state__icon"><i class="bi bi-calendar-x"></i></div>
                <div class="court-empty-state__title">Chưa có lịch hoạt động</div>
                <div class="court-empty-state__text">Thiết lập lịch mở/đóng cửa cho từng sân theo ngày trong tuần</div>
                <button class="court-action-btn court-action-btn--primary mt-3" @click="openScheduleModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Lịch
                </button>
            </div>

            <div v-else class="court-table-wrap">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4">Sân</th>
                            <th>Ngày Trong Tuần</th>
                            <th>Giờ Mở Cửa</th>
                            <th>Giờ Đóng Cửa</th>
                            <th>Trạng Thái</th>
                            <th class="text-end pe-4">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="schedule in store.schedules" :key="schedule.schedule_id || schedule.id">
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center rounded-2" style="width: 32px; height: 32px; background: var(--court-primary-soft);">
                                        <i class="bi bi-building" style="color: var(--court-primary); font-size: 0.85rem;"></i>
                                    </div>
                                    <span class="fw-bold" style="font-size: 0.9rem;">{{ getCourtName(schedule.court_id) }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-badge--active">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    {{ getDayOfWeekLabel(schedule.day_of_week) }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold" style="font-size: 0.9rem;">
                                    <i class="bi bi-sunrise me-1 text-muted"></i>
                                    {{ schedule.open_time?.substring(0, 5) }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold" style="font-size: 0.9rem;">
                                    <i class="bi bi-sunset me-1 text-muted"></i>
                                    {{ schedule.close_time?.substring(0, 5) }}
                                </span>
                            </td>
                            <td>
                                <span class="status-badge" :class="schedule.is_active ? 'status-badge--active' : 'status-badge--inactive'">
                                    {{ schedule.is_active ? 'Đang hoạt động' : 'Tạm dừng' }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <button class="court-action-btn court-action-btn--outline me-1" @click="openScheduleModal(schedule)"><i class="bi bi-pencil"></i></button>
                                <button class="court-action-btn court-action-btn--danger" @click="deleteSchedule(schedule.schedule_id || schedule.id)"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- ═══════════ PRICES TAB ═══════════ -->
        <template v-if="activeTab === 'prices' && !store.loading">
            <div v-if="!store.prices || store.prices.length === 0" class="court-empty-state">
                <div class="court-empty-state__icon"><i class="bi bi-cash-coin"></i></div>
                <div class="court-empty-state__title">Chưa có bảng giá</div>
                <div class="court-empty-state__text">Thiết lập bảng giá cho từng sân theo khung giờ và ngày trong tuần</div>
                <button class="court-action-btn court-action-btn--primary mt-3" @click="openPriceModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Bảng Giá
                </button>
            </div>

            <div v-else class="court-table-wrap">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4">Sân Áp Dụng</th>
                            <th>Tên Giá</th>
                            <th>Ngày Trong Tuần</th>
                            <th>Khung Giờ</th>
                            <th>Giá / Giờ</th>
                            <th>Trạng Thái</th>
                            <th class="text-end pe-4">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="price in store.prices" :key="price.price_id || price.id">
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center rounded-2" style="width: 32px; height: 32px; background: var(--court-primary-soft);">
                                        <i class="bi bi-building" style="color: var(--court-primary); font-size: 0.85rem;"></i>
                                    </div>
                                    <span class="fw-bold" style="font-size: 0.9rem;">{{ getCourtName(price.court_id) }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="fw-semibold" style="font-size: 0.85rem;">{{ price.price_name || '—' }}</span>
                            </td>
                            <td>
                                <span class="status-badge" :class="price.day_type === 'weekend' ? 'status-badge--maintenance' : (price.day_type === 'holiday' ? 'status-badge--closed' : 'status-badge--active')">
                                    {{ getDayTypeLabel(price.day_type) }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold" style="font-size: 0.9rem;">
                                    <i class="bi bi-clock me-1 text-muted"></i>
                                    {{ (price.from_time || price.start_time)?.substring(0, 5) }} – {{ (price.to_time || price.end_time)?.substring(0, 5) }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold" style="color: var(--court-primary); font-size: 0.95rem;">{{ formatCurrency(price.price_per_hour) }}</span>
                            </td>
                            <td>
                                <span class="status-badge" :class="price.is_active !== false ? 'status-badge--active' : 'status-badge--inactive'">
                                    {{ price.is_active !== false ? 'Đang áp dụng' : 'Tạm dừng' }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <button class="court-action-btn court-action-btn--outline me-1" @click="openPriceModal(price)"><i class="bi bi-pencil"></i></button>
                                <button class="court-action-btn court-action-btn--danger" @click="deletePrice(price.price_id || price.id)"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- ═══════════ SERVICES TAB ═══════════ -->
        <template v-if="activeTab === 'services' && !store.loading">
            <div v-if="!store.services || store.services.length === 0" class="court-empty-state">
                <div class="court-empty-state__icon"><i class="bi bi-bag-x"></i></div>
                <div class="court-empty-state__title">Chưa có dịch vụ</div>
                <div class="court-empty-state__text">Thêm dịch vụ bổ sung như nước uống, cầu lông, khăn...</div>
                <button class="court-action-btn court-action-btn--primary mt-3" @click="openServiceModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Dịch Vụ
                </button>
            </div>

            <div v-else class="court-card-grid">
                <div v-for="service in store.services" :key="service.service_id || service.id" class="court-card" style="border-top-color: var(--court-playing);">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 44px; height: 44px; background: var(--court-playing-bg); color: var(--court-playing);">
                                <i class="bi bi-box-seam" style="font-size: 1.2rem;"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1" style="font-size: 1rem;">{{ service.name || service.service_name }}</h6>
                                <span class="fw-bold" style="color: var(--court-primary); font-size: 0.95rem;">{{ formatCurrency(service.price || service.unit_price) }}</span>
                            </div>
                        </div>
                        <span class="status-badge" :class="service.status === 'active' ? 'status-badge--active' : 'status-badge--inactive'">
                            {{ service.status === 'active' ? 'Hoạt động' : 'Tạm dừng' }}
                        </span>
                    </div>
                    <p v-if="service.description" class="text-muted mb-0 flex-grow-1" style="font-size: 0.85rem;" v-html="service.description"></p>
                    <p v-else class="text-muted mb-0 flex-grow-1 fst-italic" style="font-size: 0.85rem;">Chưa có mô tả</p>
                    <div class="d-flex gap-2 mt-3 pt-3" style="border-top: 1px solid var(--border-color, rgba(0,0,0,0.06));">
                        <button class="court-action-btn court-action-btn--outline flex-fill justify-content-center" @click="openServiceModal(service)">
                            <i class="bi bi-pencil"></i> Sửa
                        </button>
                        <button class="court-action-btn flex-fill justify-content-center" style="background: var(--court-closed-bg); color: var(--court-closed); border: 1px solid var(--court-closed-border);" @click="deleteService(service.service_id || service.id)">
                            <i class="bi bi-trash"></i> Xóa
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ═══════════ MAINTENANCES TAB ═══════════ -->
        <template v-if="activeTab === 'maintenances' && !store.loading">
            <div v-if="!store.maintenances || store.maintenances.length === 0" class="court-empty-state">
                <div class="court-empty-state__icon"><i class="bi bi-tools"></i></div>
                <div class="court-empty-state__title">Chưa có lịch bảo trì</div>
                <div class="court-empty-state__text">Lên lịch bảo trì để đảm bảo sân luôn trong trạng thái tốt nhất</div>
                <button class="court-action-btn court-action-btn--primary mt-3" @click="openMaintenanceModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Lịch Bảo Trì
                </button>
            </div>

            <div v-else class="court-card-grid">
                <div v-for="m in store.maintenances" :key="m.maintenance_id || m.id" class="court-card" :class="'court-card--' + getMaintenanceStatusClass(m.status)">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="fw-bold mb-1" style="font-size: 1.05rem;">{{ m.title }}</h5>
                            <div class="d-flex align-items-center gap-2">
                                <span class="status-badge" :class="'status-badge--' + getMaintenanceStatusClass(m.status)">
                                    <span class="pulse-dot" :class="'pulse-dot--' + getMaintenanceStatusClass(m.status)"></span>
                                    {{ getMaintenanceStatusLabel(m.status) }}
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1 px-2 py-1 rounded-pill" style="background: var(--court-section-bg); font-size: 0.75rem; font-weight: 600;">
                            <i class="bi bi-building" style="font-size: 0.7rem;"></i>
                            {{ getCourtName(m.court_id) }}
                        </div>
                    </div>

                    <!-- Time Range -->
                    <div class="d-flex gap-3 mb-3 px-3 py-2 rounded-3" style="background: var(--court-section-bg);">
                        <div>
                            <small class="text-muted d-block" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;">Bắt đầu</small>
                            <span class="fw-semibold" style="font-size: 0.85rem;">{{ formatDateTime(m.start_datetime) }}</span>
                        </div>
                        <div style="border-left: 1px solid var(--border-color, rgba(0,0,0,0.08));"></div>
                        <div>
                            <small class="text-muted d-block" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;">Kết thúc</small>
                            <span class="fw-semibold" style="font-size: 0.85rem;">{{ formatDateTime(m.end_datetime) }}</span>
                        </div>
                    </div>

                    <!-- Description -->
                    <p v-if="m.description" class="text-muted mb-0 flex-grow-1" style="font-size: 0.85rem; line-height: 1.5;">{{ m.description }}</p>
                    <p v-else class="text-muted mb-0 flex-grow-1 fst-italic" style="font-size: 0.85rem;">Không có mô tả</p>

                    <!-- Created by -->
                    <div v-if="m.created_by_info || m.created_by" class="mt-2">
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-person me-1"></i>
                            Tạo bởi: {{ m.created_by_info?.full_name || m.created_by?.full_name || `Admin #${m.created_by}` }}
                        </small>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2 mt-3 pt-3" style="border-top: 1px solid var(--border-color, rgba(0,0,0,0.06));">
                        <button class="court-action-btn court-action-btn--outline flex-fill" @click="openMaintenanceModal(m)">
                            <i class="bi bi-pencil"></i> Sửa
                        </button>
                        <button class="court-action-btn court-action-btn--danger" @click="deleteMaintenance(m.maintenance_id || m.id)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ═══════════ MODALS ═══════════ -->
        
        <!-- Court Modal -->
        <div class="modal fade court-modal" id="courtModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingId ? 'Sửa Sân' : 'Thêm Sân Mới' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Tên Sân</label>
                            <input type="text" class="form-control" v-model="courtForm.court_name" placeholder="VD: Sân 01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mã Sân</label>
                            <input type="text" class="form-control" v-model="courtForm.court_code" placeholder="VD: SAN01">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Loại</label>
                                <select class="form-select" v-model="courtForm.type">
                                    <option value="standard">Tiêu chuẩn</option>
                                    <option value="vip">VIP</option>
                                    <option value="outdoor">Ngoài trời</option>
                                    <option value="indoor">Trong nhà</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" v-model="courtForm.status">
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Không hoạt động</option>
                                    <option value="maintenance">Bảo trì</option>
                                    <option value="closed">Đóng cửa</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" v-model="courtForm.description" rows="3" placeholder="Mô tả chi tiết về sân..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="court-action-btn court-action-btn--outline" data-bs-dismiss="modal">Hủy</button>
                        <button type="button" class="court-action-btn court-action-btn--primary" @click="saveCourt">
                            <i class="bi bi-check-lg"></i> {{ editingId ? 'Cập Nhật' : 'Tạo Mới' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Modal -->
        <div class="modal fade court-modal" id="scheduleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingId ? 'Sửa Lịch Hoạt Động' : 'Thêm Lịch Hoạt Động' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Sân Áp Dụng</label>
                            <select class="form-select" v-model="scheduleForm.court_id">
                                <option value="">-- Chọn sân --</option>
                                <option v-for="court in store.courts" :key="court.court_id || court.id" :value="court.court_id || court.id">
                                    {{ court.court_name || court.name }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ngày Trong Tuần</label>
                            <select class="form-select" v-model="scheduleForm.day_of_week">
                                <option :value="1">Thứ hai</option>
                                <option :value="2">Thứ ba</option>
                                <option :value="3">Thứ tư</option>
                                <option :value="4">Thứ năm</option>
                                <option :value="5">Thứ sáu</option>
                                <option :value="6">Thứ bảy</option>
                                <option :value="0">Chủ nhật</option>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Giờ Mở Cửa</label>
                                <input type="time" class="form-control" v-model="scheduleForm.open_time">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Giờ Đóng Cửa</label>
                                <input type="time" class="form-control" v-model="scheduleForm.close_time">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" v-model="scheduleForm.is_active" id="scheduleActiveSwitch">
                                <label class="form-check-label" for="scheduleActiveSwitch">Đang hoạt động</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="court-action-btn court-action-btn--outline" data-bs-dismiss="modal">Hủy</button>
                        <button type="button" class="court-action-btn court-action-btn--primary" @click="saveSchedule">
                            <i class="bi bi-check-lg"></i> {{ editingId ? 'Cập Nhật' : 'Tạo Mới' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Price Modal -->
        <div class="modal fade court-modal" id="priceModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingId ? 'Sửa Bảng Giá' : 'Thêm Bảng Giá' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Sân Áp Dụng</label>
                            <select class="form-select" v-model="priceForm.court_id">
                                <option value="">-- Chọn sân --</option>
                                <option v-for="court in store.courts" :key="court.court_id || court.id" :value="court.court_id || court.id">
                                    {{ court.court_name || court.name }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tên Giá (Tùy chọn)</label>
                            <input type="text" class="form-control" v-model="priceForm.price_name" placeholder="VD: Giá giờ vàng buổi sáng">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Loại Ngày</label>
                            <select class="form-select" v-model="priceForm.day_type">
                                <option value="weekday">Ngày thường</option>
                                <option value="weekend">Cuối tuần</option>
                                <option value="holiday">Ngày lễ</option>
                                <option value="all">Tất cả</option>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Giờ Bắt Đầu</label>
                                <input type="time" class="form-control" v-model="priceForm.from_time">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Giờ Kết Thúc</label>
                                <input type="time" class="form-control" v-model="priceForm.to_time">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Giá / Giờ (VNĐ)</label>
                            <input type="number" class="form-control" v-model="priceForm.price_per_hour" min="0" step="10000">
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" v-model="priceForm.is_active" id="priceActiveSwitch">
                                <label class="form-check-label" for="priceActiveSwitch">Đang áp dụng</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="court-action-btn court-action-btn--outline" data-bs-dismiss="modal">Hủy</button>
                        <button type="button" class="court-action-btn court-action-btn--primary" @click="savePrice">
                            <i class="bi bi-check-lg"></i> {{ editingId ? 'Cập Nhật' : 'Tạo Mới' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service Modal -->
        <div class="modal fade court-modal" id="serviceModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingId ? 'Sửa Dịch Vụ' : 'Thêm Dịch Vụ' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Tên Dịch Vụ</label>
                            <input type="text" class="form-control" v-model="serviceForm.name" placeholder="VD: Cầu lông RSL">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Giá (VNĐ)</label>
                                <input type="number" class="form-control" v-model="serviceForm.price" min="0" step="1000">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" v-model="serviceForm.status">
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Tạm dừng</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" v-model="serviceForm.description" rows="2" placeholder="Mô tả dịch vụ..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="court-action-btn court-action-btn--outline" data-bs-dismiss="modal">Hủy</button>
                        <button type="button" class="court-action-btn court-action-btn--primary" @click="saveService">
                            <i class="bi bi-check-lg"></i> {{ editingId ? 'Cập Nhật' : 'Tạo Mới' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Modal -->
        <div class="modal fade court-modal" id="maintenanceModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingId ? 'Sửa Lịch Bảo Trì' : 'Thêm Lịch Bảo Trì' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Sân Bảo Trì</label>
                            <select class="form-select" v-model="maintenanceForm.court_id">
                                <option value="">-- Chọn sân --</option>
                                <option v-for="court in store.courts" :key="court.court_id || court.id" :value="court.court_id || court.id">
                                    {{ court.court_name || court.name }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tiêu Đề</label>
                            <input type="text" class="form-control" v-model="maintenanceForm.title" placeholder="VD: Thay lưới sân, sơn lại vạch kẻ...">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Bắt Đầu</label>
                                <input type="datetime-local" class="form-control" v-model="maintenanceForm.start_datetime">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Kết Thúc</label>
                                <input type="datetime-local" class="form-control" v-model="maintenanceForm.end_datetime">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Trạng Thái</label>
                            <select class="form-select" v-model="maintenanceForm.status">
                                <option value="scheduled">Đã lên lịch</option>
                                <option value="in_progress">Đang tiến hành</option>
                                <option value="completed">Hoàn thành</option>
                                <option value="cancelled">Đã hủy</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mô Tả</label>
                            <textarea class="form-control" v-model="maintenanceForm.description" rows="3" placeholder="Chi tiết công việc bảo trì..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="court-action-btn court-action-btn--outline" data-bs-dismiss="modal">Hủy</button>
                        <button type="button" class="court-action-btn court-action-btn--primary" @click="saveMaintenance">
                            <i class="bi bi-check-lg"></i> {{ editingId ? 'Cập Nhật' : 'Tạo Mới' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.court-management-page {
    max-width: 100%;
}
</style>
