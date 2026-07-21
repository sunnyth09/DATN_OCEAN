import sys
import os

path = os.path.abspath(r"C:\Users\Admin\.gemini\antigravity-ide\brain\ae5dc94c-8cf5-4425-932c-08ec6b40f36e\task.md")
if not os.path.exists(path):
    print("Cannot find task.md")
    sys.exit(0)

with open(path, "r", encoding="utf-8") as f:
    c = f.read()

phase2_tasks = """
# Phase 2: UI Kit (Bộ Component dùng chung)
- `[/]` Xây dựng `<BaseInput.vue>` đồng bộ viền, màu sắc, label, error message.
- `[ ]` Xây dựng `<BaseSelect.vue>` đồng bộ giao diện combobox.
- `[ ]` Xây dựng `<BaseModal.vue>` đóng gói lại các Teleport Modal.
- `[ ]` Thay thế các input/modal cũ sang UI Kit ở một vài trang chính (VD: Cart, Profile).
"""

c = c + "\n" + phase2_tasks

with open(path, "w", encoding="utf-8") as f:
    f.write(c)

print("Appended Phase 2 tasks")
