import sys

path = "src/Pages/admin/AdminCourtDashboard.vue"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

target = """const posForm = ref({
    court_id: '',
    booking_date: '',
    start_time: '07:00',
    end_time: '09:00',
    payment_method: 'cash',
    note: ''
});"""

addition = """

const openCreatePosModal = () => {
    posForm.value.booking_date = filterDate.value;
    const el = document.getElementById('posQuickModal');
    if (el) Modal.getOrCreateInstance(el).show();
};"""

if addition not in content:
    content = content.replace(target, target + addition)
    with open(path, "w", encoding="utf-8") as f:
        f.write(content)
    print("Added openCreatePosModal")
else:
    print("Already exists")
