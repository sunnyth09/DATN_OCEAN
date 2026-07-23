import sys

path = "src/Pages/Client/Courts/CourtDetail.vue"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# 1. Replace the UI block for lock countdown to include the cancel button
old_ui_block = """                            <div v-if="activeLock && lockCountdown > 0" class="text-center mb-3 px-3 py-2 rounded-3" style="background: rgba(25,135,84,0.08); color: var(--court-available); font-size: 0.82rem; font-weight: 700;">
                                Đang giữ chỗ: {{ Math.floor(lockCountdown / 60) }}:{{ String(lockCountdown % 60).padStart(2, '0') }}
                            </div>"""

new_ui_block = """                            <div v-if="activeLock && lockCountdown > 0" class="d-flex justify-content-between align-items-center mb-3 px-3 py-2 rounded-3" style="background: rgba(25,135,84,0.08); color: var(--court-available); font-size: 0.82rem; font-weight: 700;">
                                <span>Đang giữ chỗ: {{ Math.floor(lockCountdown / 60) }}:{{ String(lockCountdown % 60).padStart(2, '0') }}</span>
                                <button @click="confirmReleaseLock" class="btn btn-sm btn-link text-danger p-0 fw-semibold text-decoration-none" style="font-size: 0.8rem;">
                                    Hủy giữ chỗ
                                </button>
                            </div>"""
content = content.replace(old_ui_block, new_ui_block)

# 2. Add confirmReleaseLock and beforeunload events before </script>
js_code = """
const confirmReleaseLock = async () => {
    const result = await Swal.fire({
        title: 'Hủy giữ chỗ?',
        text: "Bạn có chắc chắn muốn hủy khung giờ đã chọn không?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e63b6f',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Không'
    });
    if (result.isConfirmed) {
        await releaseActiveLock();
        selectedSlots.value = [];
        toast.info('Đã hủy giữ chỗ.');
        fetchAvailableSlots(true);
    }
};

const handleBeforeUnload = (e) => {
    if (activeLock.value?.lock_token) {
        // Send a synchronous request or fire-and-forget to release lock when closing tab
        store.releaseLock({ lock_token: activeLock.value.lock_token }).catch(() => {});
    }
};

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    releaseActiveLock();
});
</script>"""
content = content.replace("</script>", js_code)

with open(path, "w", encoding="utf-8") as f:
    f.write(content)
print("Updated CourtDetail.vue")
