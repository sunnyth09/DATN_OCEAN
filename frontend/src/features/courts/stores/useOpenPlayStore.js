import { defineStore } from 'pinia';
import { ref } from 'vue';
import { openPlayService } from '../services/openPlayService';

export const useOpenPlayStore = defineStore('openPlay', () => {
  const matches = ref([]);
  const currentMatch = ref(null);
  const eligibleBookings = ref([]);
  const myOpenPlays = ref({ hosted: [], joined: [] });
  const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 10,
  });
  const isLoading = ref(false);
  const isActionLoading = ref(false);
  const error = ref(null);

  const fetchMatches = async (params = {}) => {
    isLoading.value = true;
    error.value = null;
    try {
      const res = await openPlayService.getOpenPlays(params);
      const data = res.data?.data;
      if (data) {
        matches.value = data.data || [];
        pagination.value = {
          currentPage: data.current_page || 1,
          lastPage: data.last_page || 1,
          total: data.total || 0,
          perPage: data.per_page || 10,
        };
      }
      return matches.value;
    } catch (err) {
      error.value = err.response?.data?.message || 'Không thể tải danh sách trận đấu.';
      throw err;
    } finally {
      isLoading.value = false;
    }
  };

  const fetchMatchDetail = async (id) => {
    isLoading.value = true;
    error.value = null;
    try {
      const res = await openPlayService.getOpenPlayDetail(id);
      currentMatch.value = res.data?.data || null;
      return currentMatch.value;
    } catch (err) {
      error.value = err.response?.data?.message || 'Không thể tải chi tiết trận đấu.';
      throw err;
    } finally {
      isLoading.value = false;
    }
  };

  const fetchEligibleBookings = async () => {
    isLoading.value = true;
    try {
      const res = await openPlayService.getEligibleBookings();
      eligibleBookings.value = res.data?.data || [];
      return eligibleBookings.value;
    } catch (err) {
      eligibleBookings.value = [];
      throw err;
    } finally {
      isLoading.value = false;
    }
  };

  const createMatch = async (payload) => {
    isActionLoading.value = true;
    try {
      const res = await openPlayService.createOpenPlay(payload);
      return res.data;
    } finally {
      isActionLoading.value = false;
    }
  };

  const joinMatch = async (id, payload = {}) => {
    isActionLoading.value = true;
    try {
      const res = await openPlayService.joinOpenPlay(id, payload);
      await fetchMatchDetail(id);
      return res.data;
    } finally {
      isActionLoading.value = false;
    }
  };

  const leaveMatch = async (id, reason = null) => {
    isActionLoading.value = true;
    try {
      const res = await openPlayService.leaveOpenPlay(id, { reason });
      await fetchMatchDetail(id);
      return res.data;
    } finally {
      isActionLoading.value = false;
    }
  };

  const joinWaitlist = async (id) => {
    isActionLoading.value = true;
    try {
      const res = await openPlayService.joinWaitlist(id);
      await fetchMatchDetail(id);
      return res.data;
    } finally {
      isActionLoading.value = false;
    }
  };

  const leaveWaitlist = async (id) => {
    isActionLoading.value = true;
    try {
      const res = await openPlayService.leaveWaitlist(id);
      await fetchMatchDetail(id);
      return res.data;
    } finally {
      isActionLoading.value = false;
    }
  };

  const approveParticipant = async (matchId, participantId) => {
    isActionLoading.value = true;
    try {
      const res = await openPlayService.approveParticipant(matchId, participantId);
      await fetchMatchDetail(matchId);
      return res.data;
    } finally {
      isActionLoading.value = false;
    }
  };

  const rejectParticipant = async (matchId, participantId, reason = null) => {
    isActionLoading.value = true;
    try {
      const res = await openPlayService.rejectParticipant(matchId, participantId, reason);
      await fetchMatchDetail(matchId);
      return res.data;
    } finally {
      isActionLoading.value = false;
    }
  };

  const removeParticipant = async (matchId, participantId, reason = null) => {
    isActionLoading.value = true;
    try {
      const res = await openPlayService.removeParticipant(matchId, participantId, reason);
      await fetchMatchDetail(matchId);
      return res.data;
    } finally {
      isActionLoading.value = false;
    }
  };

  const cancelMatch = async (matchId, reason = null) => {
    isActionLoading.value = true;
    try {
      const res = await openPlayService.cancelOpenPlay(matchId, reason);
      await fetchMatchDetail(matchId);
      return res.data;
    } finally {
      isActionLoading.value = false;
    }
  };

  const paySlot = async (matchId, payload) => {
    isActionLoading.value = true;
    try {
      const res = await openPlayService.paySlot(matchId, payload);
      await fetchMatchDetail(matchId);
      return res.data;
    } finally {
      isActionLoading.value = false;
    }
  };

  const fetchMyOpenPlays = async () => {
    isLoading.value = true;
    try {
      const res = await openPlayService.getMyOpenPlays();
      myOpenPlays.value = res.data?.data || { hosted: [], joined: [] };
      return myOpenPlays.value;
    } finally {
      isLoading.value = false;
    }
  };

  return {
    matches,
    currentMatch,
    eligibleBookings,
    myOpenPlays,
    pagination,
    isLoading,
    isActionLoading,
    error,
    fetchMatches,
    fetchMatchDetail,
    fetchEligibleBookings,
    createMatch,
    joinMatch,
    leaveMatch,
    joinWaitlist,
    leaveWaitlist,
    approveParticipant,
    rejectParticipant,
    removeParticipant,
    cancelMatch,
    paySlot,
    fetchMyOpenPlays,
  };
});
