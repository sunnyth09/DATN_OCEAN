import { ref } from 'vue';
import { defineStore } from 'pinia';
import { courtBookingService } from '@/services/courtBookingService';

export const useCourtBookingStore = defineStore('courtBooking', () => {
  // -------------------------
  // STATE
  // -------------------------
  const courts = ref([]);
  const userBookings = ref([]);
  const adminBookings = ref([]);
  const currentCourt = ref(null);
  const currentBooking = ref(null);
  const prices = ref([]);
  const services = ref([]);
  const maintenances = ref([]);
  const schedules = ref([]);
  const courtDashboard = ref(null);
  const courtStats = ref(null);
  const courtCalendar = ref(null);
  const pagination = ref(null);
  
  const loading = ref(false);
  const error = ref(null);

  // -------------------------
  // HELPERS
  // -------------------------
  const setLoading = (state) => (loading.value = state);
  const setError = (err) => {
    error.value = err.response?.data?.message || err.message || 'Lỗi không xác định';
    throw err;
  };
  const clearError = () => (error.value = null);

  // Hỗ trợ rút gọn code try/catch
  const wrapAction = async (apiCall) => {
    setLoading(true);
    clearError();
    try {
      const res = await apiCall();
      return res.data;
    } catch (err) {
      setError(err);
    } finally {
      setLoading(false);
    }
  };

  // -------------------------
  // USER ACTIONS
  // -------------------------
  const fetchCourts = async (params = {}) => {
    const data = await wrapAction(() => courtBookingService.getCourts(params));
    if (data) courts.value = data.data;
    return data;
  };
  const fetchCourtDetail = async (id) => {
    const data = await wrapAction(() => courtBookingService.getCourtDetail(id));
    if (data) currentCourt.value = data.data;
    return data;
  };
  const checkAvailability = (id, params) => wrapAction(() => courtBookingService.checkAvailability(id, params));
  const lockSlot = (payload) => wrapAction(() => courtBookingService.lockSlot(payload));
  const releaseLock = (payload) => wrapAction(() => courtBookingService.releaseLock(payload));
  const bookCourt = (payload) => wrapAction(() => courtBookingService.createBooking(payload));
  
  const fetchUserBookings = async (params = {}) => {
    const data = await wrapAction(() => courtBookingService.getUserBookings(params));
    if (data) userBookings.value = data.data;
    return data;
  };
  const fetchUserBookingDetail = async (id) => {
    const data = await wrapAction(() => courtBookingService.getUserBookingDetail(id));
    if (data) currentBooking.value = data.data;
    return data;
  };
  const cancelBooking = (id, payload) => wrapAction(() => courtBookingService.cancelBooking(id, payload));
  const payBooking = (id, payload) => wrapAction(() => courtBookingService.payBooking(id, payload));
  const getBookingQr = (id) => wrapAction(() => courtBookingService.getBookingQr(id));
  const fetchPublicServices = (params) => wrapAction(() => courtBookingService.getPublicServices(params));

  // -------------------------
  // ADMIN ACTIONS - COURTS
  // -------------------------
  const fetchAdminCourts = async (params) => {
    const data = await wrapAction(() => courtBookingService.getAdminCourts(params));
    if (data) courts.value = data.data || data;
    return data;
  };
  const createAdminCourt = (payload) => wrapAction(() => courtBookingService.createAdminCourt(payload));
  const updateAdminCourt = (id, payload) => wrapAction(() => courtBookingService.updateAdminCourt(id, payload));
  const deleteAdminCourt = (id) => wrapAction(() => courtBookingService.deleteAdminCourt(id));

  // -------------------------
  // ADMIN ACTIONS - BOOKINGS
  // -------------------------
  const fetchAdminBookings = async (params = {}) => {
    const data = await wrapAction(() => courtBookingService.getAdminBookings(params));
    // Support pagination mapping
    if (data) {
      adminBookings.value = data.data?.data || data.data;
      // Store pagination info
      if (data.data?.current_page) {
        pagination.value = {
          current_page: data.data.current_page,
          last_page: data.data.last_page,
          per_page: data.data.per_page,
          total: data.data.total,
        };
      }
    }
    return data;
  };
  const fetchAdminBookingDetail = async (id) => {
    const data = await wrapAction(() => courtBookingService.getAdminBookingDetail(id));
    if (data) currentBooking.value = data.data;
    return data;
  };
  const createAdminBooking = (payload) => wrapAction(() => courtBookingService.createAdminBooking(payload));
  const updateAdminBooking = (id, payload) => wrapAction(() => courtBookingService.updateAdminBooking(id, payload));
  const deleteAdminBooking = (id) => wrapAction(() => courtBookingService.deleteAdminBooking(id));
  const confirmBooking = (id, payload = {}) => wrapAction(() => courtBookingService.confirmBooking(id, payload));
  const cancelAdminBooking = (id, payload = {}) => wrapAction(() => courtBookingService.cancelAdminBooking(id, payload));
  const recordAdminPayment = (id, payload) => wrapAction(() => courtBookingService.recordAdminPayment(id, payload));
  const qrCheckInBooking = (id, payload) => wrapAction(() => courtBookingService.qrCheckInBooking(id, payload));
  const adminCheckIn = (id) => wrapAction(() => courtBookingService.checkInBooking(id));
  const adminCheckOut = (id) => wrapAction(() => courtBookingService.checkOutBooking(id));
  const addServiceToBooking = (id, payload) => wrapAction(() => courtBookingService.addServiceToBooking(id, payload));
  const extendBooking = (id, payload) => wrapAction(() => courtBookingService.extendBooking(id, payload));

  // -------------------------
  // ADMIN ACTIONS - DASHBOARD & REPORTS
  // -------------------------
  const fetchCourtDashboard = async (params) => {
    const data = await wrapAction(() => courtBookingService.getCourtDashboard(params));
    if (data) courtDashboard.value = data.data;
    return data;
  };
  const fetchCourtStats = async (params) => {
    const data = await wrapAction(() => courtBookingService.getCourtStats(params));
    if (data) courtStats.value = data.data;
    return data;
  };
  const fetchCourtCalendar = async (params) => {
    const data = await wrapAction(() => courtBookingService.getCourtCalendar(params));
    if (data) courtCalendar.value = data.data;
    return data;
  };

  // -------------------------
  // ADMIN ACTIONS - CONFIGURATIONS
  // -------------------------
  // Schedules
  const fetchSchedules = async (params) => {
    const data = await wrapAction(() => courtBookingService.getSchedules(params));
    if (data) schedules.value = data.data || data;
    return data;
  };
  const createSchedule = (payload) => wrapAction(() => courtBookingService.createSchedule(payload));
  const updateSchedule = (id, payload) => wrapAction(() => courtBookingService.updateSchedule(id, payload));
  const deleteSchedule = (id) => wrapAction(() => courtBookingService.deleteSchedule(id));

  // Prices
  const fetchPrices = async (params) => {
    const data = await wrapAction(() => courtBookingService.getPrices(params));
    if (data) prices.value = data.data || data;
    return data;
  };
  const createPrice = (payload) => wrapAction(() => courtBookingService.createPrice(payload));
  const updatePrice = (id, payload) => wrapAction(() => courtBookingService.updatePrice(id, payload));
  const deletePrice = (id) => wrapAction(() => courtBookingService.deletePrice(id));

  // Services
  const fetchServices = async (params) => {
    const data = await wrapAction(() => courtBookingService.getServices(params));
    if (data) services.value = data.data || data;
    return data;
  };
  const createService = (payload) => wrapAction(() => courtBookingService.createService(payload));
  const updateService = (id, payload) => wrapAction(() => courtBookingService.updateService(id, payload));
  const deleteService = (id) => wrapAction(() => courtBookingService.deleteService(id));

  // Maintenances
  const fetchMaintenances = async (params) => {
    const data = await wrapAction(() => courtBookingService.getMaintenances(params));
    if (data) maintenances.value = data.data || data;
    return data;
  };
  const createMaintenance = (payload) => wrapAction(() => courtBookingService.createMaintenance(payload));
  const updateMaintenance = (id, payload) => wrapAction(() => courtBookingService.updateMaintenance(id, payload));
  const deleteMaintenance = (id) => wrapAction(() => courtBookingService.deleteMaintenance(id));

  return {
    // State
    courts, userBookings, adminBookings, currentCourt, currentBooking,
    prices, services, maintenances, schedules, courtDashboard, courtStats, courtCalendar,
    pagination, loading, error, clearError,
    
    // User Methods
    fetchCourts, fetchCourtDetail, checkAvailability, lockSlot, releaseLock, bookCourt, 
    fetchUserBookings, fetchUserBookingDetail, cancelBooking, payBooking, getBookingQr, fetchPublicServices,
    
    // Admin Courts Methods
    fetchAdminCourts, createAdminCourt, updateAdminCourt, deleteAdminCourt,
    
    // Admin Bookings Methods
    fetchAdminBookings, fetchAdminBookingDetail, createAdminBooking,
    updateAdminBooking, deleteAdminBooking, confirmBooking, cancelAdminBooking,
    recordAdminPayment, qrCheckInBooking, adminCheckIn, adminCheckOut, addServiceToBooking, extendBooking,
    
    // Admin Dashboard & Reports
    fetchCourtDashboard, fetchCourtStats, fetchCourtCalendar,
    
    // Admin Config Methods
    fetchSchedules, createSchedule, updateSchedule, deleteSchedule,
    fetchPrices, createPrice, updatePrice, deletePrice,
    fetchServices, createService, updateService, deleteService,
    fetchMaintenances, createMaintenance, updateMaintenance, deleteMaintenance
  };
});
