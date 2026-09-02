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
const uploadingImage = ref(false);

// Filter & Search states
const courtSearch = ref('');
const courtTypeFilter = ref('all');
const scheduleCourtFilter = ref('all');
const priceCourtFilter = ref('all');
const serviceCategoryFilter = ref('all');
const maintenanceStatusFilter = ref('all');

// Preset Gallery Images for Badminton Courts
const courtPresetImages = [
    { title: 'Sân Thảm Xanh Yonex', url: 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?q=80&w=800&auto=format&fit=crop' },
    { title: 'Sân Thảm Đỏ VIP', url: 'https://images.unsplash.com/photo-1544717305-2782549b5136?q=80&w=800&auto=format&fit=crop' },
    { title: 'Sân Thi Đấu Trong Nhà', url: 'https://images.unsplash.com/photo-1521537634581-0dced2fee2ef?q=80&w=800&auto=format&fit=crop' },
    { title: 'Sân Sàn Gỗ Chuyên Dụng', url: 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?q=80&w=800&auto=format&fit=crop' },
];

// Preset Gallery Images for Services
const servicePresetImages = [
    { title: 'Nước Suối Aquafina 500ml', url: 'https://images.unsplash.com/photo-1523362628745-0c100150b504?q=80&w=600&auto=format&fit=crop', icon: 'bi-cup-straw', unit: 'bottle' },
    { title: 'Nước Tăng Lực Revive Chanh Muối', url: 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?q=80&w=600&auto=format&fit=crop', icon: 'bi-cup-straw', unit: 'bottle' },
    { title: 'Ống Cầu Lông RSL 12 Quả', url: 'https://images.unsplash.com/photo-1613918108466-292b78a8ef95?q=80&w=600&auto=format&fit=crop', icon: 'bi-bullseye', unit: 'set' },
    { title: 'Thuê Vợt Yonex Nanoflare', url: 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?q=80&w=600&auto=format&fit=crop', icon: 'bi-activity', unit: 'hour' },
];

// Form Models
const courtForm = ref({
    court_name: '',
    court_code: '',
    description: '',
    type: 'standard',
    surface: 'Thảm PVC Yonex',
    max_players: 4,
    status: 'active',
    image_url: '',
    sort_order: 0
});

const priceForm = ref({
    court_id: '',
    price_name: '',
    day_type: 'weekday',
    from_time: '05:00',
    to_time: '23:00',
    price_per_hour: 50000,
    is_active: true
});

const serviceForm = ref({
    service_name: '',
    service_code: '',
    unit: 'piece',
    unit_price: 10000,
    description: '',
    image_url: '',
    is_active: true,
    sort_order: 0
});

const scheduleForm = ref({
    court_id: '',
    day_of_week: 1,
    open_time: '05:00',
    close_time: '22:00',
    is_active: true,
    apply_all_days: false
});

const maintenanceForm = ref({
    court_id: '',
    title: '',
    description: '',
    start_datetime: '',
    end_datetime: '',
    status: 'scheduled'
});

const editingId = ref(null);

onMounted(async () => {
    loadTabContent();
});

const loadTabContent = async () => {
    if (activeTab.value === 'courts') {
        await store.fetchAdminCourts();
    } else if (activeTab.value === 'schedules') {
        await store.fetchAdminCourts();
        await store.fetchSchedules();
    } else if (activeTab.value === 'prices') {
        await store.fetchAdminCourts();
        await store.fetchPrices();
    } else if (activeTab.value === 'services') {
        await store.fetchServices();
    } else if (activeTab.value === 'maintenances') {
        await store.fetchAdminCourts();
        await store.fetchMaintenances();
    }
};

const switchTab = (tab) => {
    activeTab.value = tab;
    loadTabContent();
};

const formatCurrency = (val) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);

// Tab Counts
const courtCount = computed(() => store.courts?.length || 0);
const scheduleCount = computed(() => store.schedules?.length || 0);
const priceCount = computed(() => store.prices?.length || 0);
const serviceCount = computed(() => store.services?.length || 0);
const maintenanceCount = computed(() => store.maintenances?.length || 0);

// Filtered Lists
const filteredCourts = computed(() => {
    let list = store.courts || [];
    if (courtSearch.value.trim()) {
        const q = courtSearch.value.toLowerCase();
        list = list.filter(c => (c.court_name || c.name || '').toLowerCase().includes(q) || (c.court_code || '').toLowerCase().includes(q));
    }
    if (courtTypeFilter.value !== 'all') {
        list = list.filter(c => c.type === courtTypeFilter.value);
    }
    return list;
});

const filteredSchedules = computed(() => {
    let list = store.schedules || [];
    if (scheduleCourtFilter.value !== 'all') {
        list = list.filter(s => String(s.court_id) === String(scheduleCourtFilter.value));
    }
    return list;
});

const filteredPrices = computed(() => {
    let list = store.prices || [];
    if (priceCourtFilter.value !== 'all') {
        list = list.filter(p => String(p.court_id) === String(priceCourtFilter.value));
    }
    return list;
});

const filteredServices = computed(() => {
    let list = store.services || [];
    if (serviceCategoryFilter.value !== 'all') {
        if (serviceCategoryFilter.value === 'drinks') {
            list = list.filter(s => s.unit === 'bottle' || (s.service_name || s.name || '').toLowerCase().includes('nước'));
        } else if (serviceCategoryFilter.value === 'gear') {
            list = list.filter(s => s.unit === 'set' || s.unit === 'piece' || (s.service_name || s.name || '').toLowerCase().includes('cầu') || (s.service_name || s.name || '').toLowerCase().includes('vợt'));
        } else if (serviceCategoryFilter.value === 'coach') {
            list = list.filter(s => s.unit === 'hour' || (s.service_name || s.name || '').toLowerCase().includes('huấn luyện'));
        }
    }
    return list;
});

const filteredMaintenances = computed(() => {
    let list = store.maintenances || [];
    if (maintenanceStatusFilter.value !== 'all') {
        list = list.filter(m => m.status === maintenanceStatusFilter.value);
    }
    return list;
});

// Helpers
const stripHtml = (html) => {
    if (!html) return '';
    return String(html).replace(/<[^>]*>?/gm, '').replace(/&nbsp;/g, ' ').trim();
};

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
    const map = { weekday: 'Ngày thường (T2-T6)', weekend: 'Cuối tuần (T7-CN)', holiday: 'Ngày lễ', all: 'Tất cả các ngày' };
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
    const court = store.courts?.find(c => (c.court_id || c.id) == courtId);
    return court ? (court.court_name || court.name) : `Sân #${courtId}`;
};

const getUnitLabel = (unit) => {
    const map = {
        piece: 'Chiếc / Quả / Đôi',
        bottle: 'Chai / Lon',
        set: 'Hộp / Bộ',
        hour: 'Giờ thuê',
        other: 'Khác'
    };
    return map[unit] || unit || 'Chiếc';
};

const getServiceIcon = (unit, name = '') => {
    const lower = (name || '').toLowerCase();
    if (lower.includes('nước') || lower.includes('aquafina') || lower.includes('revive') || lower.includes('sting') || unit === 'bottle') return 'bi-cup-straw';
    if (lower.includes('cầu') || lower.includes('rsl') || lower.includes('hộp') || unit === 'set') return 'bi-bullseye';
    if (lower.includes('vợt') || lower.includes('thuê vợt')) return 'bi-activity';
    if (lower.includes('giày') || lower.includes('thuê giày')) return 'bi-smartwatch';
    if (lower.includes('khăn') || lower.includes('lạnh')) return 'bi-droplet';
    if (lower.includes('huấn luyện') || lower.includes('hướng dẫn') || unit === 'hour') return 'bi-person-arms-up';
    return 'bi-box-seam';
};

const formatDateTime = (dt) => {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

// Court stats
const courtStats = computed(() => {
    const courts = store.courts || [];
    return {
        total: courts.length,
        active: courts.filter(c => c.status === 'active').length,
        maintenance: courts.filter(c => c.status === 'maintenance').length,
        closed: courts.filter(c => c.status === 'closed' || c.status === 'inactive').length
    };
});

// ==== IMAGE UPLOAD HANDLERS ====
const handleCourtImageUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('image', file);
    try {
        uploadingImage.value = true;
        const res = await store.uploadCourtImage(formData);
        if (res?.data?.url) {
            courtForm.value.image_url = res.data.url;
            toast.success('Tải ảnh sân lên thành công');
        }
    } catch (e) {
        toast.error('Không thể tải ảnh sân lên');
    } finally {
        uploadingImage.value = false;
    }
};

const handleServiceImageUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('image', file);
    try {
        uploadingImage.value = true;
        const res = await store.uploadCourtServiceImage(formData);
        if (res?.data?.url) {
            serviceForm.value.image_url = res.data.url;
            toast.success('Tải ảnh dịch vụ lên thành công');
        }
    } catch (e) {
        toast.error('Không thể tải ảnh dịch vụ lên');
    } finally {
        uploadingImage.value = false;
    }
};

// ==== COURTS CRUD ====
const openCourtModal = (court = null) => {
    if (court) {
        editingId.value = court.court_id || court.id;
        courtForm.value = {
            court_name: court.court_name || court.name || '',
            court_code: court.court_code || '',
            description: stripHtml(court.description) || '',
            type: court.type || 'standard',
            surface: court.surface || 'Thảm PVC Yonex',
            max_players: court.max_players || 4,
            status: court.status || 'active',
            image_url: court.image_url || '',
            sort_order: court.sort_order || 0
        };
    } else {
        editingId.value = null;
        courtForm.value = {
            court_name: '',
            court_code: `SAN-0${(store.courts?.length || 0) + 1}`,
            description: '',
            type: 'standard',
            surface: 'Thảm PVC Yonex',
            max_players: 4,
            status: 'active',
            image_url: '',
            sort_order: (store.courts?.length || 0) + 1
        };
    }
    new Modal(document.getElementById('courtModal')).show();
};

const saveCourt = async () => {
    try {
        if (!courtForm.value.court_name.trim()) {
            toast.warning('Vui lòng nhập tên sân');
            return;
        }
        if (editingId.value) {
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

const toggleCourtStatus = async (court) => {
    const newStatus = court.status === 'active' ? 'inactive' : 'active';
    try {
        await store.updateAdminCourt(court.court_id || court.id, {
            ...court,
            status: newStatus
        });
        court.status = newStatus;
        toast.success(`Đã chuyển sân sang trạng thái ${getStatusLabel(newStatus)}`);
    } catch (e) {
        toast.error('Không thể cập nhật trạng thái sân');
    }
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
    if (result.isConfirmed) {
        try {
            await store.deleteAdminCourt(id);
            toast.success('Đã xóa sân');
            loadTabContent();
        } catch (e) {}
    }
};

// ==== SCHEDULES CRUD ====
const openScheduleModal = (schedule = null) => {
    if (schedule) {
        editingId.value = schedule.schedule_id || schedule.id;
        scheduleForm.value = {
            court_id: schedule.court_id,
            day_of_week: schedule.day_of_week,
            open_time: schedule.open_time?.substring(0, 5) || '05:00',
            close_time: schedule.close_time?.substring(0, 5) || '22:00',
            is_active: schedule.is_active ?? true,
            apply_all_days: false
        };
    } else {
        editingId.value = null;
        scheduleForm.value = {
            court_id: store.courts?.[0]?.court_id || store.courts?.[0]?.id || '',
            day_of_week: 1,
            open_time: '05:00',
            close_time: '22:00',
            is_active: true,
            apply_all_days: false
        };
    }
    new Modal(document.getElementById('scheduleModal')).show();
};

const setSchedulePreset = (open, close) => {
    scheduleForm.value.open_time = open;
    scheduleForm.value.close_time = close;
};

const saveSchedule = async () => {
    try {
        if (!scheduleForm.value.court_id) {
            toast.warning('Vui lòng chọn sân áp dụng');
            return;
        }
        if (editingId.value) {
            await store.updateSchedule(editingId.value, scheduleForm.value);
            toast.success('Cập nhật lịch hoạt động thành công');
        } else {
            if (scheduleForm.value.apply_all_days) {
                for (let d = 0; d <= 6; d++) {
                    await store.createSchedule({
                        court_id: scheduleForm.value.court_id,
                        day_of_week: d,
                        open_time: scheduleForm.value.open_time,
                        close_time: scheduleForm.value.close_time,
                        is_active: scheduleForm.value.is_active
                    });
                }
                toast.success('Đã áp dụng lịch cho toàn bộ 7 ngày trong tuần');
            } else {
                await store.createSchedule(scheduleForm.value);
                toast.success('Thêm lịch hoạt động thành công');
            }
        }
        Modal.getInstance(document.getElementById('scheduleModal')).hide();
        loadTabContent();
    } catch (e) {}
};

const toggleScheduleStatus = async (schedule) => {
    const newStatus = !schedule.is_active;
    try {
        await store.updateSchedule(schedule.schedule_id || schedule.id, {
            ...schedule,
            is_active: newStatus
        });
        schedule.is_active = newStatus;
        toast.success(`Đã ${newStatus ? 'kích hoạt' : 'tạm dừng'} lịch hoạt động`);
    } catch (e) {
        toast.error('Không thể cập nhật lịch hoạt động');
    }
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
    if (result.isConfirmed) {
        try {
            await store.deleteSchedule(id);
            toast.success('Đã xóa lịch hoạt động');
            loadTabContent();
        } catch (e) {}
    }
};

// ==== PRICES CRUD ====
const openPriceModal = (price = null) => {
    if (price) {
        editingId.value = price.price_id || price.id;
        priceForm.value = {
            court_id: price.court_id,
            price_name: price.price_name || '',
            day_type: price.day_type || 'weekday',
            from_time: (price.from_time || price.start_time)?.substring(0, 5) || '05:00',
            to_time: (price.to_time || price.end_time)?.substring(0, 5) || '23:00',
            price_per_hour: price.price_per_hour || 50000,
            is_active: price.is_active ?? true
        };
    } else {
        editingId.value = null;
        priceForm.value = {
            court_id: store.courts?.[0]?.court_id || store.courts?.[0]?.id || '',
            price_name: 'Khung Giờ Tiêu Chuẩn',
            day_type: 'weekday',
            from_time: '05:00',
            to_time: '23:00',
            price_per_hour: 50000,
            is_active: true
        };
    }
    new Modal(document.getElementById('priceModal')).show();
};

const setPricePreset = (val) => {
    priceForm.value.price_per_hour = val;
};

const savePrice = async () => {
    try {
        if (!priceForm.value.court_id) {
            toast.warning('Vui lòng chọn sân áp dụng');
            return;
        }
        if (editingId.value) {
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

const togglePriceStatus = async (price) => {
    const newStatus = !price.is_active;
    try {
        await store.updatePrice(price.price_id || price.id, {
            ...price,
            is_active: newStatus
        });
        price.is_active = newStatus;
        toast.success(`Đã ${newStatus ? 'áp dụng' : 'tạm ngưng'} bảng giá`);
    } catch (e) {
        toast.error('Không thể cập nhật bảng giá');
    }
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
    if (result.isConfirmed) {
        try {
            await store.deletePrice(id);
            toast.success('Đã xóa cấu hình giá');
            loadTabContent();
        } catch (e) {}
    }
};

// ==== SERVICES CRUD ====
const openServiceModal = (service = null) => {
    if (service) {
        editingId.value = service.service_id || service.id;
        serviceForm.value = {
            service_name: service.service_name || service.name || '',
            service_code: service.service_code || '',
            unit: service.unit || 'piece',
            unit_price: service.unit_price ?? service.price ?? 0,
            description: stripHtml(service.description) || '',
            image_url: service.image_url || '',
            is_active: service.is_active !== undefined ? Boolean(service.is_active) : (service.status === 'active'),
            sort_order: service.sort_order || 0
        };
    } else {
        editingId.value = null;
        serviceForm.value = {
            service_name: '',
            service_code: `SVC-${Math.floor(100 + Math.random() * 900)}`,
            unit: 'piece',
            unit_price: 10000,
            description: '',
            image_url: '',
            is_active: true,
            sort_order: (store.services?.length || 0) + 1
        };
    }
    new Modal(document.getElementById('serviceModal')).show();
};

const applyServicePreset = (preset) => {
    serviceForm.value.service_name = preset.title;
    serviceForm.value.image_url = preset.url;
    serviceForm.value.unit = preset.unit;
};

const saveService = async () => {
    try {
        if (!serviceForm.value.service_name.trim()) {
            toast.warning('Vui lòng nhập tên dịch vụ');
            return;
        }
        if (editingId.value) {
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

const toggleServiceStatus = async (service) => {
    const newStatus = !(service.is_active !== undefined ? service.is_active : service.status === 'active');
    try {
        await store.updateService(service.service_id || service.id, {
            ...service,
            is_active: newStatus
        });
        service.is_active = newStatus;
        toast.success(`Đã ${newStatus ? 'mở bán' : 'tạm dừng'} dịch vụ`);
    } catch (e) {
        toast.error('Không thể cập nhật trạng thái dịch vụ');
    }
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
    if (result.isConfirmed) {
        try {
            await store.deleteService(id);
            toast.success('Đã xóa dịch vụ');
            loadTabContent();
        } catch (e) {}
    }
};

// ==== MAINTENANCES CRUD ====
const openMaintenanceModal = (m = null) => {
    if (m) {
        editingId.value = m.maintenance_id || m.id;
        maintenanceForm.value = {
            court_id: m.court_id,
            title: m.title || '',
            description: stripHtml(m.description) || '',
            start_datetime: m.start_datetime?.substring(0, 16) || '',
            end_datetime: m.end_datetime?.substring(0, 16) || '',
            status: m.status || 'scheduled'
        };
    } else {
        editingId.value = null;
        const now = new Date();
        const nowStr = now.toISOString().substring(0, 16);
        maintenanceForm.value = {
            court_id: store.courts?.[0]?.court_id || store.courts?.[0]?.id || '',
            title: '',
            description: '',
            start_datetime: nowStr,
            end_datetime: nowStr,
            status: 'scheduled'
        };
    }
    new Modal(document.getElementById('maintenanceModal')).show();
};

const setMaintenanceReason = (reason) => {
    maintenanceForm.value.title = reason;
};

const saveMaintenance = async () => {
    try {
        if (!maintenanceForm.value.court_id) {
            toast.warning('Vui lòng chọn sân cần bảo trì');
            return;
        }
        if (!maintenanceForm.value.title.trim()) {
            toast.warning('Vui lòng nhập tiêu đề bảo trì');
            return;
        }
        if (editingId.value) {
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
    if (result.isConfirmed) {
        try {
            await store.deleteMaintenance(id);
            toast.success('Đã xóa lịch bảo trì');
            loadTabContent();
        } catch (e) {}
    }
};
</script>

<template>
    <div class="court-management-page">
        <!-- Ocean Standard Page Header -->
        <div class="page-header">
            <div class="header-info">
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="12" y1="3" x2="12" y2="21"/>
                    </svg>
                    Cấu Hình &amp; Quản Lý Sân Bãi
                </h1>
                <p class="page-subtitle">Hệ thống quản lý cụm sân cầu lông, bảng giá giờ vàng, lịch hoạt động và dịch vụ bán kèm</p>
            </div>
            <div class="header-btns">
                <button v-if="activeTab === 'courts'" class="btn-primary" @click="openCourtModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Sân Mới
                </button>
                <button v-if="activeTab === 'schedules'" class="btn-primary" @click="openScheduleModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Lịch Hoạt Động
                </button>
                <button v-if="activeTab === 'prices'" class="btn-primary" @click="openPriceModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Bảng Giá
                </button>
                <button v-if="activeTab === 'services'" class="btn-primary" @click="openServiceModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Dịch Vụ Mới
                </button>
                <button v-if="activeTab === 'maintenances'" class="btn-primary" @click="openMaintenanceModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Lịch Bảo Trì
                </button>
            </div>
        </div>

        <!-- Compact KPI Stats Row -->
        <div class="court-kpi-row">
            <div class="court-kpi-card">
                <div class="court-kpi-icon primary">
                    <i class="bi bi-buildings"></i>
                </div>
                <div>
                    <div class="court-kpi-value">{{ courtStats.total }}</div>
                    <div class="court-kpi-label">Tổng số sân</div>
                </div>
            </div>
            <div class="court-kpi-card">
                <div class="court-kpi-icon success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="court-kpi-value">{{ courtStats.active }}</div>
                    <div class="court-kpi-label">Đang hoạt động</div>
                </div>
            </div>
            <div class="court-kpi-card">
                <div class="court-kpi-icon warning">
                    <i class="bi bi-tools"></i>
                </div>
                <div>
                    <div class="court-kpi-value">{{ courtStats.maintenance }}</div>
                    <div class="court-kpi-label">Đang bảo trì</div>
                </div>
            </div>
            <div class="court-kpi-card">
                <div class="court-kpi-icon danger">
                    <i class="bi bi-slash-circle"></i>
                </div>
                <div>
                    <div class="court-kpi-value">{{ courtStats.closed }}</div>
                    <div class="court-kpi-label">Tạm ngưng / Đóng</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar with Status Tabs -->
        <div class="filters-bar ocean-card">
            <!-- Status Tabs -->
            <div class="status-tabs-container">
                <div class="status-pills">
                    <button class="status-pill" :class="{ active: activeTab === 'courts' }" @click="switchTab('courts')">
                        <i class="bi bi-grid-3x3-gap"></i>
                        <span>Danh Sách Sân</span>
                        <span class="pill-count-badge">{{ courtCount }}</span>
                    </button>
                    <button class="status-pill" :class="{ active: activeTab === 'schedules' }" @click="switchTab('schedules')">
                        <i class="bi bi-calendar-week"></i>
                        <span>Lịch Hoạt Động</span>
                        <span class="pill-count-badge">{{ scheduleCount }}</span>
                    </button>
                    <button class="status-pill" :class="{ active: activeTab === 'prices' }" @click="switchTab('prices')">
                        <i class="bi bi-currency-dollar"></i>
                        <span>Bảng Giá &amp; Giờ Vàng</span>
                        <span class="pill-count-badge">{{ priceCount }}</span>
                    </button>
                    <button class="status-pill" :class="{ active: activeTab === 'services' }" @click="switchTab('services')">
                        <i class="bi bi-bag-plus"></i>
                        <span>Dịch Vụ Bán Kèm</span>
                        <span class="pill-count-badge">{{ serviceCount }}</span>
                    </button>
                    <button class="status-pill" :class="{ active: activeTab === 'maintenances' }" @click="switchTab('maintenances')">
                        <i class="bi bi-tools"></i>
                        <span>Bảo Trì Sân</span>
                        <span class="pill-count-badge">{{ maintenanceCount }}</span>
                    </button>
                </div>
            </div>

            <!-- Search & Sub Filter Toolbar -->
            <div class="filters-inner">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" class="search-input" v-model="courtSearch" placeholder="Tìm kiếm tên sân, mã sân...">
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <!-- Sub-filter for Courts tab -->
                        <select v-if="activeTab === 'courts'" class="form-select w-auto fw-semibold" v-model="courtTypeFilter" style="border-radius: 10px; font-size: 0.85rem;">
                            <option value="all">Tất cả loại sân</option>
                            <option value="standard">Tiêu chuẩn</option>
                            <option value="vip">VIP</option>
                            <option value="indoor">Trong nhà</option>
                            <option value="outdoor">Ngoài trời</option>
                        </select>

                        <!-- Sub-filter for Schedules tab -->
                        <select v-if="activeTab === 'schedules'" class="form-select w-auto fw-semibold" v-model="scheduleCourtFilter" style="border-radius: 10px; font-size: 0.85rem;">
                            <option value="all">Tất cả các sân</option>
                            <option v-for="c in store.courts" :key="c.court_id || c.id" :value="c.court_id || c.id">{{ c.court_name || c.name }}</option>
                        </select>

                        <!-- Sub-filter for Prices tab -->
                        <select v-if="activeTab === 'prices'" class="form-select w-auto fw-semibold" v-model="priceCourtFilter" style="border-radius: 10px; font-size: 0.85rem;">
                            <option value="all">Tất cả các sân</option>
                            <option v-for="c in store.courts" :key="c.court_id || c.id" :value="c.court_id || c.id">{{ c.court_name || c.name }}</option>
                        </select>

                        <!-- Sub-filter for Services tab -->
                        <div v-if="activeTab === 'services'" class="d-flex gap-1">
                            <button class="btn btn-sm rounded-pill px-3" :class="serviceCategoryFilter === 'all' ? 'btn-primary' : 'btn-outline'" @click="serviceCategoryFilter = 'all'">Tất cả</button>
                            <button class="btn btn-sm rounded-pill px-3" :class="serviceCategoryFilter === 'drinks' ? 'btn-primary' : 'btn-outline'" @click="serviceCategoryFilter = 'drinks'">Nước uống</button>
                            <button class="btn btn-sm rounded-pill px-3" :class="serviceCategoryFilter === 'gear' ? 'btn-primary' : 'btn-outline'" @click="serviceCategoryFilter = 'gear'">Cầu lông &amp; Vợt</button>
                        </div>

                        <!-- Sub-filter for Maintenances tab -->
                        <select v-if="activeTab === 'maintenances'" class="form-select w-auto fw-semibold" v-model="maintenanceStatusFilter" style="border-radius: 10px; font-size: 0.85rem;">
                            <option value="all">Tất cả trạng thái</option>
                            <option value="scheduled">Đã lên lịch</option>
                            <option value="in_progress">Đang tiến hành</option>
                            <option value="completed">Hoàn thành</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════ 1. COURTS TAB ═══════════ -->
        <template v-if="activeTab === 'courts'">
            <div v-if="filteredCourts.length === 0" class="court-empty-state">
                <div class="court-empty-state__icon"><i class="bi bi-building-slash"></i></div>
                <div class="court-empty-state__title">Không tìm thấy sân nào</div>
                <div class="court-empty-state__text">Thử thay đổi từ khóa tìm kiếm hoặc thêm sân mới vào hệ thống</div>
                <button class="btn-primary mt-3" @click="openCourtModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Sân Mới
                </button>
            </div>

            <!-- Court Cards Grid -->
            <div v-else class="court-card-grid">
                <div
                    v-for="court in filteredCourts"
                    :key="court.court_id || court.id"
                    class="court-card"
                    :class="'court-card--' + getStatusClass(court.status)"
                >
                    <!-- Banner Header -->
                    <div class="court-card-banner">
                        <img :src="court.image_url || 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?q=80&w=800&auto=format&fit=crop'" class="court-card-banner__bg-img" alt="Court">
                        <span class="court-card-banner__badge">
                            <i :class="getTypeIcon(court.type)" class="me-1"></i>
                            {{ getTypeLabel(court.type) }}
                        </span>
                        <h4 class="court-card-banner__title">{{ court.court_name || court.name }}</h4>
                    </div>

                    <!-- Status Toggle & Code -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="status-badge" :class="'status-badge--' + getStatusClass(court.status)">
                                <span class="pulse-dot" :class="'pulse-dot--' + getStatusClass(court.status)"></span>
                                {{ getStatusLabel(court.status) }}
                            </span>
                            <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.72rem; font-family: monospace;">
                                {{ court.court_code }}
                            </span>
                        </div>
                        <label class="quick-switch" title="Bật/Tắt hoạt động sân">
                            <input type="checkbox" :checked="court.status === 'active'" @change="toggleCourtStatus(court)">
                            <span class="quick-switch-slider"></span>
                        </label>
                    </div>

                    <!-- Specs Tags -->
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="court-spec-tag">
                            <i class="bi bi-square-half text-primary"></i>
                            {{ court.surface || 'Thảm PVC Yonex' }}
                        </span>
                        <span class="court-spec-tag">
                            <i class="bi bi-people text-info"></i>
                            Tối đa {{ court.max_players || 4 }} người
                        </span>
                    </div>

                    <!-- Description -->
                    <p v-if="court.description" class="text-muted mb-0 flex-grow-1" style="font-size: 0.84rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        {{ stripHtml(court.description) }}
                    </p>
                    <p v-else class="text-muted mb-0 flex-grow-1 fst-italic" style="font-size: 0.84rem;">Chưa có mô tả chi tiết</p>

                    <!-- Actions -->
                    <div class="d-flex gap-2 mt-3 pt-3" style="border-top: 1px solid var(--border-color, rgba(0,0,0,0.06));">
                        <button class="btn-outline flex-fill justify-content-center" @click="openCourtModal(court)">
                            <i class="bi bi-pencil"></i> Chỉnh sửa
                        </button>
                        <button class="btn-icon-danger" @click="deleteCourt(court.court_id || court.id)" title="Xóa sân">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ═══════════ 2. SCHEDULES TAB ═══════════ -->
        <template v-if="activeTab === 'schedules'">
            <div v-if="filteredSchedules.length === 0" class="court-empty-state">
                <div class="court-empty-state__icon"><i class="bi bi-calendar-x"></i></div>
                <div class="court-empty-state__title">Chưa có lịch hoạt động</div>
                <div class="court-empty-state__text">Thiết lập lịch mở/đóng cửa cho từng sân theo ngày trong tuần</div>
                <button class="btn-primary mt-3" @click="openScheduleModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Lịch Hoạt Động
                </button>
            </div>

            <div v-else class="court-table-wrap ocean-card">
                <table class="ocean-table">
                    <thead>
                        <tr>
                            <th>Sân Áp Dụng</th>
                            <th>Ngày Trong Tuần</th>
                            <th>Giờ Hoạt Động</th>
                            <th>Trạng Thái</th>
                            <th class="text-end">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="schedule in filteredSchedules" :key="schedule.schedule_id || schedule.id">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center rounded-2" style="width: 32px; height: 32px; background: var(--court-primary-soft);">
                                        <i class="bi bi-building" style="color: var(--primary, #E63B6F); font-size: 0.85rem;"></i>
                                    </div>
                                    <span class="fw-bold">{{ getCourtName(schedule.court_id) }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.8rem; font-weight: 600;">
                                    <i class="bi bi-calendar-day me-1 text-muted"></i>
                                    {{ getDayOfWeekLabel(schedule.day_of_week) }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold" style="font-size: 0.88rem;">
                                    <i class="bi bi-clock me-1 text-muted"></i>
                                    {{ schedule.open_time?.substring(0, 5) }} – {{ schedule.close_time?.substring(0, 5) }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <label class="quick-switch">
                                        <input type="checkbox" :checked="schedule.is_active !== false" @change="toggleScheduleStatus(schedule)">
                                        <span class="quick-switch-slider"></span>
                                    </label>
                                    <span class="status-badge" :class="schedule.is_active !== false ? 'status-badge--active' : 'status-badge--inactive'">
                                        {{ schedule.is_active !== false ? 'Hoạt động' : 'Tạm dừng' }}
                                    </span>
                                </div>
                            </td>
                            <td class="text-end">
                                <button class="btn-outline btn-sm me-1" @click="openScheduleModal(schedule)" title="Chỉnh sửa">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-icon-danger btn-sm" @click="deleteSchedule(schedule.schedule_id || schedule.id)" title="Xóa">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- ═══════════ 3. PRICES TAB ═══════════ -->
        <template v-if="activeTab === 'prices'">
            <div v-if="filteredPrices.length === 0" class="court-empty-state">
                <div class="court-empty-state__icon"><i class="bi bi-cash-stack"></i></div>
                <div class="court-empty-state__title">Chưa có cấu hình giá</div>
                <div class="court-empty-state__text">Thiết lập bảng giá thuê sân theo khung giờ và loại ngày</div>
                <button class="btn-primary mt-3" @click="openPriceModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Bảng Giá
                </button>
            </div>

            <div v-else class="court-table-wrap ocean-card">
                <table class="ocean-table">
                    <thead>
                        <tr>
                            <th>Sân &amp; Tên Cấu Hình</th>
                            <th>Loại Ngày</th>
                            <th>Khung Giờ</th>
                            <th>Giá Thuê / Giờ</th>
                            <th>Trạng Thái</th>
                            <th class="text-end">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="price in filteredPrices" :key="price.price_id || price.id">
                            <td>
                                <div class="fw-bold">{{ getCourtName(price.court_id) }}</div>
                                <div class="text-muted" style="font-size: 0.8rem;">
                                    <i class="bi bi-tag me-1"></i>{{ price.price_name || 'Khung giá chuẩn' }}
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.78rem;">
                                    {{ getDayTypeLabel(price.day_type) }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold" style="font-size: 0.88rem;">
                                    <i class="bi bi-clock me-1 text-muted"></i>
                                    {{ (price.from_time || price.start_time)?.substring(0, 5) }} – {{ (price.to_time || price.end_time)?.substring(0, 5) }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold" style="color: var(--primary, #E63B6F); font-size: 1rem;">
                                    {{ formatCurrency(price.price_per_hour) }}
                                </span>
                                <small class="text-muted d-block" style="font-size: 0.72rem;">
                                    ~ {{ formatCurrency(Math.round(price.price_per_hour / 2)) }} / slot 30p
                                </small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <label class="quick-switch">
                                        <input type="checkbox" :checked="price.is_active !== false" @change="togglePriceStatus(price)">
                                        <span class="quick-switch-slider"></span>
                                    </label>
                                    <span class="status-badge" :class="price.is_active !== false ? 'status-badge--active' : 'status-badge--inactive'">
                                        {{ price.is_active !== false ? 'Áp dụng' : 'Tạm dừng' }}
                                    </span>
                                </div>
                            </td>
                            <td class="text-end">
                                <button class="btn-outline btn-sm me-1" @click="openPriceModal(price)" title="Chỉnh sửa">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-icon-danger btn-sm" @click="deletePrice(price.price_id || price.id)" title="Xóa">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- ═══════════ 4. SERVICES TAB ═══════════ -->
        <template v-if="activeTab === 'services'">
            <div v-if="filteredServices.length === 0" class="court-empty-state">
                <div class="court-empty-state__icon"><i class="bi bi-bag-x"></i></div>
                <div class="court-empty-state__title">Chưa có dịch vụ nào</div>
                <div class="court-empty-state__text">Thêm dịch vụ bán kèm như nước uống, cầu lông RSL, thuê vợt...</div>
                <button class="btn-primary mt-3" @click="openServiceModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Dịch Vụ Mới
                </button>
            </div>

            <!-- Service Cards Grid -->
            <div v-else class="court-card-grid">
                <div
                    v-for="service in filteredServices"
                    :key="service.service_id || service.id"
                    class="court-card"
                    :class="(service.is_active !== undefined ? service.is_active : service.status === 'active') ? 'court-card--active' : 'court-card--closed'"
                >
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div v-if="service.image_url" class="rounded-3 overflow-hidden" style="width: 52px; height: 52px; flex-shrink: 0; border: 1px solid rgba(0,0,0,0.06);">
                                <img :src="service.image_url" class="w-100 h-100 object-fit-cover" alt="Service">
                            </div>
                            <div v-else class="service-card-icon-wrap">
                                <i :class="getServiceIcon(service.unit, service.service_name || service.name)"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1" style="font-size: 1.02rem;">
                                    {{ service.service_name || service.name }}
                                </h5>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold" style="color: var(--primary, #E63B6F); font-size: 1.05rem;">
                                        {{ formatCurrency(service.unit_price ?? service.price) }}
                                    </span>
                                    <span class="service-unit-badge">
                                        / {{ getUnitLabel(service.unit) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Toggle Switch -->
                        <label class="quick-switch" title="Bật/Tắt mở bán dịch vụ">
                            <input type="checkbox" :checked="service.is_active !== undefined ? service.is_active : service.status === 'active'" @change="toggleServiceStatus(service)">
                            <span class="quick-switch-slider"></span>
                        </label>
                    </div>

                    <!-- Status & Code Badge -->
                    <div class="d-flex align-items-center justify-content-between mb-3 px-3 py-2 rounded-3" style="background: var(--surface-container-low, #f8fafc);">
                        <span class="status-badge" :class="(service.is_active !== undefined ? service.is_active : service.status === 'active') ? 'status-badge--active' : 'status-badge--inactive'">
                            <span class="pulse-dot" :class="(service.is_active !== undefined ? service.is_active : service.status === 'active') ? 'pulse-dot--active' : 'pulse-dot--maintenance'"></span>
                            {{ (service.is_active !== undefined ? service.is_active : service.status === 'active') ? 'Đang mở bán' : 'Tạm dừng bán' }}
                        </span>
                        <span class="text-muted" style="font-size: 0.76rem; font-family: monospace; font-weight: 600;">
                            {{ service.service_code || 'SVC-00' }}
                        </span>
                    </div>

                    <!-- Description -->
                    <p v-if="service.description" class="text-muted mb-0 flex-grow-1" style="font-size: 0.84rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        {{ stripHtml(service.description) }}
                    </p>
                    <p v-else class="text-muted mb-0 flex-grow-1 fst-italic" style="font-size: 0.84rem;">Chưa có mô tả</p>

                    <!-- Actions -->
                    <div class="d-flex gap-2 mt-3 pt-3" style="border-top: 1px solid var(--border-color, rgba(0,0,0,0.06));">
                        <button class="btn-outline flex-fill justify-content-center" @click="openServiceModal(service)">
                            <i class="bi bi-pencil"></i> Chỉnh sửa
                        </button>
                        <button class="btn-icon-danger" @click="deleteService(service.service_id || service.id)" title="Xóa dịch vụ">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ═══════════ 5. MAINTENANCES TAB ═══════════ -->
        <template v-if="activeTab === 'maintenances'">
            <div v-if="filteredMaintenances.length === 0" class="court-empty-state">
                <div class="court-empty-state__icon"><i class="bi bi-tools"></i></div>
                <div class="court-empty-state__title">Chưa có lịch bảo trì nào</div>
                <div class="court-empty-state__text">Lên lịch bảo trì định kỳ để giữ cụm sân luôn đạt chất lượng thi đấu tốt nhất</div>
                <button class="btn-primary mt-3" @click="openMaintenanceModal()">
                    <i class="bi bi-plus-lg"></i> Thêm Lịch Bảo Trì
                </button>
            </div>

            <div v-else class="court-card-grid">
                <div v-for="m in filteredMaintenances" :key="m.maintenance_id || m.id" class="court-card" :class="'court-card--' + getMaintenanceStatusClass(m.status)">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="fw-bold mb-1" style="font-size: 1.05rem;">{{ m.title }}</h5>
                            <span class="status-badge" :class="'status-badge--' + getMaintenanceStatusClass(m.status)">
                                <span class="pulse-dot" :class="'pulse-dot--' + getMaintenanceStatusClass(m.status)"></span>
                                {{ getMaintenanceStatusLabel(m.status) }}
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-1 px-2 py-1 rounded-pill" style="background: var(--surface-container-low, #f8fafc); font-size: 0.75rem; font-weight: 600;">
                            <i class="bi bi-building" style="font-size: 0.7rem;"></i>
                            {{ getCourtName(m.court_id) }}
                        </div>
                    </div>

                    <!-- Time Range -->
                    <div class="d-flex gap-3 mb-3 px-3 py-2 rounded-3" style="background: var(--surface-container-low, #f8fafc);">
                        <div>
                            <small class="text-muted d-block" style="font-size: 0.7rem; text-transform: uppercase;">Bắt đầu</small>
                            <span class="fw-semibold" style="font-size: 0.85rem;">{{ formatDateTime(m.start_datetime) }}</span>
                        </div>
                        <div style="border-left: 1px solid var(--border-color, #E9ECEF);"></div>
                        <div>
                            <small class="text-muted d-block" style="font-size: 0.7rem; text-transform: uppercase;">Kết thúc</small>
                            <span class="fw-semibold" style="font-size: 0.85rem;">{{ formatDateTime(m.end_datetime) }}</span>
                        </div>
                    </div>

                    <!-- Description -->
                    <p v-if="m.description" class="text-muted mb-0 flex-grow-1" style="font-size: 0.85rem; line-height: 1.5;">{{ stripHtml(m.description) }}</p>
                    <p v-else class="text-muted mb-0 flex-grow-1 fst-italic" style="font-size: 0.85rem;">Không có mô tả</p>

                    <!-- Actions -->
                    <div class="d-flex gap-2 mt-3 pt-3" style="border-top: 1px solid var(--border-color, rgba(0,0,0,0.06));">
                        <button class="btn-outline flex-fill justify-content-center" @click="openMaintenanceModal(m)">
                            <i class="bi bi-pencil"></i> Sửa
                        </button>
                        <button class="btn-icon-danger" @click="deleteMaintenance(m.maintenance_id || m.id)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ═══════════ MODALS WITH ENTERPRISE UPLOAD & PRESETS ═══════════ -->
        
        <!-- 1. Court Modal (Large 2-column with Live Image Upload & Presets) -->
        <div class="modal fade court-modal" id="courtModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-bottom py-3 px-4" style="background: var(--surface-container-low, #f8fafc);">
                        <div class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 36px; height: 36px; background: var(--court-primary-soft); color: var(--primary, #E63B6F);">
                                <i class="bi bi-building"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">{{ editingId ? 'Chỉnh Sửa Sân' : 'Thêm Sân Mới' }}</h5>
                                <small class="text-muted">Cấu hình thông số kỹ thuật, hình ảnh và trạng thái hoạt động</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-4">
                            <!-- Left Column: Core Info & Specs -->
                            <div class="col-md-7">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Tên Sân <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="courtForm.court_name" placeholder="VD: Sân 01 - Tiêu Chuẩn Yonex">
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Mã Định Danh <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" v-model="courtForm.court_code" placeholder="VD: SAN-01">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Thứ Tự Sắp Xếp</label>
                                        <input type="number" class="form-control" v-model="courtForm.sort_order" min="0">
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Loại Sân</label>
                                        <select class="form-select" v-model="courtForm.type">
                                            <option value="standard">Tiêu chuẩn</option>
                                            <option value="vip">VIP</option>
                                            <option value="indoor">Trong nhà</option>
                                            <option value="outdoor">Ngoài trời</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Trạng Thái</label>
                                        <select class="form-select" v-model="courtForm.status">
                                            <option value="active">Hoạt động</option>
                                            <option value="inactive">Tạm ngưng</option>
                                            <option value="maintenance">Bảo trì</option>
                                            <option value="closed">Đóng cửa</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Mặt Sàn</label>
                                        <input type="text" class="form-control" v-model="courtForm.surface" placeholder="VD: Thảm PVC Yonex">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Số Người Tối Đa</label>
                                        <input type="number" class="form-control" v-model="courtForm.max_players" min="2" max="10">
                                    </div>
                                </div>

                                <div class="mb-0">
                                    <label class="form-label fw-semibold">Mô Tả Chi Tiết</label>
                                    <textarea class="form-control" v-model="courtForm.description" rows="3" placeholder="Chi tiết trang thiết bị, ánh sáng, quạt mát..."></textarea>
                                </div>
                            </div>

                            <!-- Right Column: Image Upload & Live Preview -->
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Ảnh Đại Diện Sân</label>
                                
                                <!-- Live Image Preview Box -->
                                <div class="court-image-preview-box mb-3 rounded-3 overflow-hidden position-relative" style="height: 160px; background: #0f172a; border: 2px dashed #cbd5e1; display: flex; align-items: center; justify-content: center;">
                                    <img v-if="courtForm.image_url" :src="courtForm.image_url" class="w-100 h-100 object-fit-cover" alt="Preview">
                                    <div v-else class="text-center text-muted p-3">
                                        <i class="bi bi-image" style="font-size: 2rem; color: #94a3b8;"></i>
                                        <div style="font-size: 0.8rem; margin-top: 4px;">Chưa có ảnh đại diện</div>
                                    </div>
                                    <button v-if="courtForm.image_url" type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle" style="width: 28px; height: 28px; padding: 0;" @click="courtForm.image_url = ''" title="Xóa ảnh">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>

                                <!-- Upload File Input -->
                                <div class="mb-3">
                                    <label class="btn btn-outline-primary w-100 rounded-3 mb-2" :class="{ 'disabled': uploadingImage }">
                                        <span v-if="uploadingImage" class="spinner-border spinner-border-sm me-1"></span>
                                        <i v-else class="bi bi-cloud-arrow-up me-1"></i>
                                        Tải Ảnh Lên Từ Máy Tính
                                        <input type="file" class="d-none" accept="image/*" @change="handleCourtImageUpload">
                                    </label>
                                    <input type="text" class="form-control form-control-sm" v-model="courtForm.image_url" placeholder="Hoặc dán URL ảnh trực tiếp...">
                                </div>

                                <!-- Preset Badminton Court Images -->
                                <div>
                                    <small class="text-muted d-block fw-semibold mb-2" style="font-size: 0.75rem;">Hoặc chọn nhanh ảnh mẫu:</small>
                                    <div class="row g-2">
                                        <div v-for="(p, idx) in courtPresetImages" :key="idx" class="col-6">
                                            <div class="preset-img-item rounded-2 overflow-hidden border p-1 text-center" 
                                                 style="cursor: pointer; background: var(--surface-container-low, #f8fafc); transition: all 0.2s;"
                                                 :class="{ 'border-primary shadow-sm': courtForm.image_url === p.url }"
                                                 @click="courtForm.image_url = p.url">
                                                <img :src="p.url" class="rounded-1 w-100" style="height: 48px; object-fit: cover;" alt="Preset">
                                                <div class="text-truncate mt-1" style="font-size: 0.68rem; font-weight: 600;">{{ p.title }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3 px-4">
                        <button type="button" class="btn-outline" data-bs-dismiss="modal">Hủy bỏ</button>
                        <button type="button" class="btn-primary" @click="saveCourt">
                            <i class="bi bi-check-lg"></i> {{ editingId ? 'Lưu Thay Đổi' : 'Thêm Sân Mới' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Schedule Modal -->
        <div class="modal fade court-modal" id="scheduleModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-bottom py-3 px-4" style="background: var(--surface-container-low, #f8fafc);">
                        <div class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 36px; height: 36px; background: var(--court-primary-soft); color: var(--primary, #E63B6F);">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">{{ editingId ? 'Sửa Lịch Hoạt Động' : 'Thêm Lịch Hoạt Động' }}</h5>
                                <small class="text-muted">Cấu hình khung giờ mở/đóng cửa cho sân</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Sân Áp Dụng <span class="text-danger">*</span></label>
                            <select class="form-select" v-model="scheduleForm.court_id">
                                <option value="">-- Chọn sân áp dụng --</option>
                                <option v-for="court in store.courts" :key="court.court_id || court.id" :value="court.court_id || court.id">
                                    {{ court.court_name || court.name }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3" v-if="!editingId">
                            <label class="form-label fw-semibold">Ngày Trong Tuần</label>
                            <select class="form-select" v-model="scheduleForm.day_of_week" :disabled="scheduleForm.apply_all_days">
                                <option :value="1">Thứ hai</option>
                                <option :value="2">Thứ ba</option>
                                <option :value="3">Thứ tư</option>
                                <option :value="4">Thứ năm</option>
                                <option :value="5">Thứ sáu</option>
                                <option :value="6">Thứ bảy</option>
                                <option :value="0">Chủ nhật</option>
                            </select>
                        </div>

                        <!-- Quick Presets -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block">Khung Giờ Mẫu:</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline rounded-pill" @click="setSchedulePreset('05:00', '22:00')">
                                    05:00 - 22:00 (Tiêu chuẩn)
                                </button>
                                <button type="button" class="btn btn-sm btn-outline rounded-pill" @click="setSchedulePreset('05:00', '23:00')">
                                    05:00 - 23:00 (Mở muộn)
                                </button>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Giờ Mở Cửa <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" v-model="scheduleForm.open_time">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Giờ Đóng Cửa <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" v-model="scheduleForm.close_time">
                            </div>
                        </div>

                        <div class="mb-3" v-if="!editingId">
                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: var(--surface-container-low, #f8fafc); border: 1px solid var(--border-color, #E9ECEF);">
                                <div>
                                    <div class="fw-bold" style="font-size: 0.88rem;">Áp dụng cả tuần</div>
                                    <small class="text-muted">Áp dụng khung giờ cho cả 7 ngày trong tuần</small>
                                </div>
                                <label class="quick-switch mb-0">
                                    <input type="checkbox" v-model="scheduleForm.apply_all_days">
                                    <span class="quick-switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="mb-0">
                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: var(--surface-container-low, #f8fafc); border: 1px solid var(--border-color, #E9ECEF);">
                                <div>
                                    <div class="fw-bold" style="font-size: 0.88rem;">Kích hoạt lịch</div>
                                    <small class="text-muted">Đang áp dụng hoạt động</small>
                                </div>
                                <label class="quick-switch mb-0">
                                    <input type="checkbox" v-model="scheduleForm.is_active">
                                    <span class="quick-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3 px-4">
                        <button type="button" class="btn-outline" data-bs-dismiss="modal">Hủy bỏ</button>
                        <button type="button" class="btn-primary" @click="saveSchedule">
                            <i class="bi bi-check-lg"></i> {{ editingId ? 'Lưu Thay Đổi' : 'Tạo Lịch Hoạt Động' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Price Modal -->
        <div class="modal fade court-modal" id="priceModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-bottom py-3 px-4" style="background: var(--surface-container-low, #f8fafc);">
                        <div class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 36px; height: 36px; background: var(--court-primary-soft); color: var(--primary, #E63B6F);">
                                <i class="bi bi-currency-dollar"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">{{ editingId ? 'Sửa Bảng Giá' : 'Thêm Bảng Giá Mới' }}</h5>
                                <small class="text-muted">Cấu hình biểu phí thuê sân theo khung giờ và loại ngày</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Sân Áp Dụng <span class="text-danger">*</span></label>
                            <select class="form-select" v-model="priceForm.court_id">
                                <option value="">-- Chọn sân áp dụng --</option>
                                <option v-for="court in store.courts" :key="court.court_id || court.id" :value="court.court_id || court.id">
                                    {{ court.court_name || court.name }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tên Cấu Hình Giá</label>
                            <input type="text" class="form-control" v-model="priceForm.price_name" placeholder="VD: Giờ Vàng Buổi Tối, Giờ Tiêu Chuẩn Sáng...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Loại Ngày</label>
                            <select class="form-select" v-model="priceForm.day_type">
                                <option value="weekday">Ngày thường (Thứ 2 - Thứ 6)</option>
                                <option value="weekend">Cuối tuần (Thứ 7 - Chủ nhật)</option>
                                <option value="holiday">Ngày lễ</option>
                                <option value="all">Tất cả các ngày</option>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Giờ Bắt Đầu</label>
                                <input type="time" class="form-control" v-model="priceForm.from_time">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Giờ Kết Thúc</label>
                                <input type="time" class="form-control" v-model="priceForm.to_time">
                            </div>
                        </div>

                        <!-- Price Presets -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block">Chọn Nhanh Mức Giá Mẫu:</label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline rounded-pill" @click="setPricePreset(40000)">40.000đ</button>
                                <button type="button" class="btn btn-sm btn-outline rounded-pill" @click="setPricePreset(50000)">50.000đ</button>
                                <button type="button" class="btn btn-sm btn-outline rounded-pill" @click="setPricePreset(60000)">60.000đ</button>
                                <button type="button" class="btn btn-sm btn-outline rounded-pill" @click="setPricePreset(80000)">80.000đ</button>
                                <button type="button" class="btn btn-sm btn-outline rounded-pill" @click="setPricePreset(100000)">100.000đ</button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Giá Thuê / Giờ (VNĐ) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control fw-bold" v-model="priceForm.price_per_hour" min="0" step="5000">
                                <span class="input-group-text">VNĐ / Giờ</span>
                            </div>
                            <div class="p-2 mt-2 rounded-2" style="background: var(--surface-container-low, #f8fafc); font-size: 0.8rem;">
                                <i class="bi bi-info-circle text-primary me-1"></i>
                                Tương đương <strong>{{ formatCurrency(Math.round(priceForm.price_per_hour / 2)) }}</strong> cho mỗi slot 30 phút.
                            </div>
                        </div>

                        <div class="mb-0">
                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: var(--surface-container-low, #f8fafc); border: 1px solid var(--border-color, #E9ECEF);">
                                <div>
                                    <div class="fw-bold" style="font-size: 0.88rem;">Kích hoạt bảng giá</div>
                                    <small class="text-muted">Đang kích hoạt áp dụng khi khách đặt sân</small>
                                </div>
                                <label class="quick-switch mb-0">
                                    <input type="checkbox" v-model="priceForm.is_active">
                                    <span class="quick-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3 px-4">
                        <button type="button" class="btn-outline" data-bs-dismiss="modal">Hủy bỏ</button>
                        <button type="button" class="btn-primary" @click="savePrice">
                            <i class="bi bi-check-lg"></i> {{ editingId ? 'Lưu Bảng Giá' : 'Thêm Bảng Giá' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Service Modal -->
        <div class="modal fade court-modal" id="serviceModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-bottom py-3 px-4" style="background: var(--surface-container-low, #f8fafc);">
                        <div class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 36px; height: 36px; background: var(--court-primary-soft); color: var(--primary, #E63B6F);">
                                <i class="bi bi-bag-plus"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">{{ editingId ? 'Sửa Dịch Vụ Sân Bãi' : 'Thêm Dịch Vụ Mới' }}</h5>
                                <small class="text-muted">Cung cấp nước giải khát, cầu lông, cho thuê vợt...</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-4">
                            <!-- Left Column: Form Details -->
                            <div class="col-md-7">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Tên Dịch Vụ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="serviceForm.service_name" placeholder="VD: Nước suối Aquafina 500ml, Ống cầu lông RSL...">
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Giá Bán (VNĐ) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control fw-bold" v-model="serviceForm.unit_price" min="0" step="1000">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Đơn Vị Tính</label>
                                        <select class="form-select" v-model="serviceForm.unit">
                                            <option value="piece">Chiếc / Quả / Đôi</option>
                                            <option value="bottle">Chai / Lon</option>
                                            <option value="set">Hộp / Bộ</option>
                                            <option value="hour">Giờ thuê</option>
                                            <option value="other">Khác</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Mã Dịch Vụ</label>
                                        <input type="text" class="form-control" v-model="serviceForm.service_code" placeholder="VD: SVC-WATER-01">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Thứ Tự Sắp Xếp</label>
                                        <input type="number" class="form-control" v-model="serviceForm.sort_order" min="0">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Mô Tả Quy Cách</label>
                                    <textarea class="form-control" v-model="serviceForm.description" rows="2" placeholder="Quy cách sản phẩm, hãng sản xuất..."></textarea>
                                </div>

                                <div class="mb-0">
                                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: var(--surface-container-low, #f8fafc); border: 1px solid var(--border-color, #E9ECEF);">
                                        <div>
                                            <div class="fw-bold" style="font-size: 0.88rem;">Mở bán dịch vụ</div>
                                            <small class="text-muted">Cho phép khách chọn dịch vụ này khi đặt sân</small>
                                        </div>
                                        <label class="quick-switch mb-0">
                                            <input type="checkbox" v-model="serviceForm.is_active">
                                            <span class="quick-switch-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Image Upload & Presets -->
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Hình Ảnh Minh Họa</label>
                                <div class="service-image-preview-box mb-3 rounded-3 overflow-hidden position-relative" style="height: 140px; background: #0f172a; border: 2px dashed #cbd5e1; display: flex; align-items: center; justify-content: center;">
                                    <img v-if="serviceForm.image_url" :src="serviceForm.image_url" class="w-100 h-100 object-fit-cover" alt="Preview">
                                    <div v-else class="text-center text-muted p-2">
                                        <i class="bi bi-box-seam" style="font-size: 2rem; color: #94a3b8;"></i>
                                        <div style="font-size: 0.78rem; margin-top: 4px;">Chưa có ảnh</div>
                                    </div>
                                    <button v-if="serviceForm.image_url" type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle" style="width: 26px; height: 26px; padding: 0;" @click="serviceForm.image_url = ''" title="Xóa ảnh">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>

                                <label class="btn btn-outline-primary w-100 rounded-3 mb-2" :class="{ 'disabled': uploadingImage }">
                                    <span v-if="uploadingImage" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="bi bi-cloud-arrow-up me-1"></i>
                                    Tải Ảnh Lên Từ Máy Tính
                                    <input type="file" class="d-none" accept="image/*" @change="handleServiceImageUpload">
                                </label>
                                <input type="text" class="form-control form-control-sm mb-3" v-model="serviceForm.image_url" placeholder="Hoặc dán URL ảnh...">

                                <!-- Presets -->
                                <small class="text-muted d-block fw-semibold mb-2" style="font-size: 0.75rem;">Mẫu phổ biến:</small>
                                <div class="d-flex flex-column gap-1">
                                    <div v-for="(sp, idx) in servicePresetImages" :key="idx" 
                                         class="p-2 rounded-2 border d-flex align-items-center justify-content-between"
                                         style="cursor: pointer; background: var(--surface-container-low, #f8fafc);"
                                         @click="applyServicePreset(sp)">
                                        <div class="d-flex align-items-center gap-2">
                                            <i :class="sp.icon" class="text-primary"></i>
                                            <span style="font-size: 0.76rem; font-weight: 600;">{{ sp.title }}</span>
                                        </div>
                                        <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">Chọn</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3 px-4">
                        <button type="button" class="btn-outline" data-bs-dismiss="modal">Hủy bỏ</button>
                        <button type="button" class="btn-primary" @click="saveService">
                            <i class="bi bi-check-lg"></i> {{ editingId ? 'Lưu Dịch Vụ' : 'Thêm Dịch Vụ Mới' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Maintenance Modal -->
        <div class="modal fade court-modal" id="maintenanceModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-bottom py-3 px-4" style="background: var(--surface-container-low, #f8fafc);">
                        <div class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 36px; height: 36px; background: var(--court-primary-soft); color: var(--primary, #E63B6F);">
                                <i class="bi bi-tools"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">{{ editingId ? 'Sửa Lịch Bảo Trì' : 'Thêm Lịch Bảo Trì' }}</h5>
                                <small class="text-muted">Đăng ký lịch bảo dưỡng, sửa chữa và thay thế trang thiết bị</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Sân Bảo Trì <span class="text-danger">*</span></label>
                            <select class="form-select" v-model="maintenanceForm.court_id">
                                <option value="">-- Chọn sân cần bảo trì --</option>
                                <option v-for="court in store.courts" :key="court.court_id || court.id" :value="court.court_id || court.id">
                                    {{ court.court_name || court.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Presets for Maintenance Reason -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block">Chọn Nhanh Lý Do Bảo Trì:</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline rounded-pill" @click="setMaintenanceReason('Thay thảm sân & vệ sinh sàn')">
                                    Thay thảm sân
                                </button>
                                <button type="button" class="btn btn-sm btn-outline rounded-pill" @click="setMaintenanceReason('Bảo dưỡng hệ thống đèn LED')">
                                    Sửa đèn LED
                                </button>
                                <button type="button" class="btn btn-sm btn-outline rounded-pill" @click="setMaintenanceReason('Căng lại lưới thi đấu')">
                                    Căng lưới
                                </button>
                                <button type="button" class="btn btn-sm btn-outline rounded-pill" @click="setMaintenanceReason('Sơn lại vạch kẻ sân')">
                                    Sơn vạch kẻ
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tiêu Đề Bảo Trì <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" v-model="maintenanceForm.title" placeholder="VD: Thay thảm sân Yonex, sửa đèn chiếu sáng...">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Thời Gian Bắt Đầu</label>
                                <input type="datetime-local" class="form-control" v-model="maintenanceForm.start_datetime">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Thời Gian Kết Thúc</label>
                                <input type="datetime-local" class="form-control" v-model="maintenanceForm.end_datetime">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Trạng Thái Tiến Độ</label>
                            <select class="form-select" v-model="maintenanceForm.status">
                                <option value="scheduled">Đã lên lịch</option>
                                <option value="in_progress">Đang tiến hành thi công</option>
                                <option value="completed">Hoàn thành / Đã nghiệm thu</option>
                                <option value="cancelled">Đã hủy</option>
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold">Ghi Chú Chi Tiết</label>
                            <textarea class="form-control" v-model="maintenanceForm.description" rows="3" placeholder="Chi tiết nhà thầu hoặc kỹ thuật viên phụ trách..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3 px-4">
                        <button type="button" class="btn-outline" data-bs-dismiss="modal">Hủy bỏ</button>
                        <button type="button" class="btn-primary" @click="saveMaintenance">
                            <i class="bi bi-check-lg"></i> {{ editingId ? 'Lưu Lịch Bảo Trì' : 'Tạo Lịch Bảo Trì' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.court-management-page {
    font-family: var(--font-inter, inherit);
    max-width: 100%;
}

.preset-img-item:hover {
    border-color: var(--primary, #E63B6F) !important;
    transform: translateY(-1px);
}
</style>
