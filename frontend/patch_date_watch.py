import sys

path = "src/Pages/Client/Courts/CourtDetail.vue"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

old_watcher = """watch(selectedDate, async () => {
    await releaseActiveLock();
    selectedSlots.value = []; // Reset selected slots khi đổi ngày
    await fetchAvailableSlots();
});"""

new_watcher = """watch(selectedDate, async (newVal, oldVal) => {
    if (oldVal && newVal !== oldVal && activeLock.value?.lock_token) {
        const result = await Swal.fire({
            title: 'Hủy giữ chỗ?',
            text: "Bạn đang giữ chỗ ở ngày hiện tại. Việc đổi ngày sẽ hủy giữ chỗ này. Bạn có tiếp tục?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e63b6f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Đồng ý',
            cancelButtonText: 'Không'
        });
        if (!result.isConfirmed) {
            // Restore old date without triggering the watcher logic again for the reset
            // We need to be careful with infinite loops.
            // A simple trick: temporarily disable the watcher, but in Vue 3 we can just set it 
            // and the next trigger will check if activeLock is there. Wait, if we revert, 
            // the watcher fires again. If we revert to oldVal, newVal == oldVal of the PREVIOUS state.
            // But we can check if it's reverting by a flag, or just use a helper variable.
            // Actually, if we revert, the activeLock is STILL there, so it will ask again? 
            // No, when we revert, we are going BACK to the date that HAS the lock. 
            // Wait, does the lock belong to the old date? Yes!
            // So if we revert, `newVal` will be the locked date, `oldVal` will be the discarded date.
            // But we don't want to ask again when reverting to the locked date!
            // Let's add a flag `isRevertingDate`.
        }
    }
});"""

# Actually, the easiest way to prevent infinite loops in watch when reverting is a flag.
new_watcher = """let isRevertingDate = false;
watch(selectedDate, async (newVal, oldVal) => {
    if (isRevertingDate) {
        isRevertingDate = false;
        return;
    }
    
    if (oldVal && newVal !== oldVal && activeLock.value?.lock_token) {
        const result = await Swal.fire({
            title: 'Đổi ngày?',
            text: "Việc chọn ngày khác sẽ hủy các khung giờ bạn đang giữ. Bạn có chắc chắn?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e63b6f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Đồng ý đổi',
            cancelButtonText: 'Giữ lại'
        });
        if (!result.isConfirmed) {
            isRevertingDate = true;
            selectedDate.value = oldVal;
            return;
        }
        await releaseActiveLock();
    }
    selectedSlots.value = []; 
    await fetchAvailableSlots();
});"""

content = content.replace(old_watcher, new_watcher)

with open(path, "w", encoding="utf-8") as f:
    f.write(content)
print("Patched watcher")
