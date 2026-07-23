import sys

path = "src/Pages/admin/AdminCourtDashboard.vue"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# Replace inline click handler with openCreatePosModal
old_btn = """<button class="scheduler-create-btn" @click="posForm.booking_date = filterDate; (() => { const el = document.getElementById('posQuickModal'); if(el) Modal.getOrCreateInstance(el).show(); })()">
                    <i class="bi bi-plus-lg"></i> Tạo Booking
                </button>"""

new_btn = """<button class="scheduler-create-btn" @click="openCreatePosModal">
                    <i class="bi bi-plus-lg"></i> Tạo Booking
                </button>"""

content = content.replace(old_btn, new_btn)

# Define openCreatePosModal before openPosModal or somewhere in the script
script_addition = """
const openCreatePosModal = () => {
    posForm.value.booking_date = filterDate.value;
    const el = document.getElementById('posQuickModal');
    if (el) Modal.getOrCreateInstance(el).show();
};
"""

# Find a good place to insert script_addition, e.g., right before openPosModal
old_open_pos = "const openPosModal = (courtId, timeSlot) => {"
new_open_pos = script_addition + "\n" + old_open_pos

content = content.replace(old_open_pos, new_open_pos)

with open(path, "w", encoding="utf-8") as f:
    f.write(content)

print("Fixed click handler for Tạo Booking")
