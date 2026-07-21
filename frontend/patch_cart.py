import sys

path = "src/Pages/Client/Cart/Index.vue"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# Replace coupon-section input and button with BaseInput and BaseButton
old_coupon = """                    <div class="coupon-section">
                        <input type="text" class="coupon-input" placeholder="Mã giảm giá" />
                        <button class="btn-apply-coupon">Áp dụng</button>
                    </div>"""

new_coupon = """                    <div class="coupon-section" style="display: flex; gap: 8px;">
                        <BaseInput placeholder="Mã giảm giá" style="flex: 1; margin-bottom: 0;" />
                        <BaseButton variant="secondary" size="md">Áp dụng</BaseButton>
                    </div>"""

if old_coupon in content:
    content = content.replace(old_coupon, new_coupon)

# Add imports for BaseInput and BaseButton if missing
import_statements = """import { useCartStore } from '@/stores/cartStore';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseButton from '@/components/base/BaseButton.vue';"""

if "import BaseInput from" not in content:
    content = content.replace("import { useCartStore } from '@/stores/cartStore';", import_statements)


with open(path, "w", encoding="utf-8") as f:
    f.write(content)

print("Patched Cart/Index.vue")
