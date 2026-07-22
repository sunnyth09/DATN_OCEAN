import sys

path = "src/Pages/admin/AdminBookingManagement.vue"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

content = content.replace("new Modal(modalEl)", "Modal.getOrCreateInstance(modalEl)")

with open(path, "w", encoding="utf-8") as f:
    f.write(content)

print("Replaced all 'new Modal' with 'Modal.getOrCreateInstance' in AdminBookingManagement")
