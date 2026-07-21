import sys

path = "src/Pages/Client/Profile/ProfileInfo.vue"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# Add import if missing
if "import BaseInput from" not in content:
    content = content.replace(
        "import AppIcon from '@/components/AppIcon.vue';", 
        "import AppIcon from '@/components/AppIcon.vue';\nimport BaseInput from '@/components/base/BaseInput.vue';"
    )

old_input_block = """          <div class="form-group">
            <label class="form-label">
              Họ và tên <span class="required" v-if="isEditing">*</span>
            </label>
            <input
              type="text"
              v-model="form.full_name"
              class="form-input"
              :class="{ 'form-input--error': errors.full_name, 'form-input--disabled': !isEditing }"
              :disabled="!isEditing"
              placeholder="Nhập họ và tên"
              maxlength="120"
              required
            />
            <span v-if="errors.full_name" class="error-text">{{ errors.full_name[0] }}</span>
          </div>"""

new_input_block = """          <BaseInput 
            v-model="form.full_name" 
            label="Họ và tên" 
            :required="isEditing" 
            :disabled="!isEditing" 
            placeholder="Nhập họ và tên"
            maxlength="120"
            :error="errors.full_name ? errors.full_name[0] : ''" 
          />"""

content = content.replace(old_input_block, new_input_block)

with open(path, "w", encoding="utf-8") as f:
    f.write(content)
print("Patched ProfileInfo.vue")
