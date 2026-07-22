import sys
import re

path = "src/Pages/admin/AdminCourtDashboard.vue"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

content = content.replace("new Modal(el)", "Modal.getOrCreateInstance(el)")

# Also, there's another place:
# @click="posForm.booking_date = filterDate; (() => { const el = document.getElementById('posQuickModal'); if(el) new Modal(el).show(); })()"
# But we just replaced new Modal(el) with Modal.getOrCreateInstance(el), so it should work globally.

with open(path, "w", encoding="utf-8") as f:
    f.write(content)

print("Replaced all 'new Modal' with 'Modal.getOrCreateInstance'")
