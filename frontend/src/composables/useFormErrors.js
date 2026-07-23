import { reactive } from 'vue';

// Tái dùng cho mọi form: hứng lỗi validation 422 (đã được axios interceptor
// chuẩn hóa thành error.validationErrors = { field: 'msg đầu tiên' }).
export function useFormErrors() {
  const errors = reactive({});

  const clearErrors = () => {
    Object.keys(errors).forEach((key) => delete errors[key]);
  };

  const setErrors = (next = {}) => {
    clearErrors();
    Object.assign(errors, next);
  };

  // Bọc lời gọi API: tự clear lỗi cũ, tự nạp lỗi 422 vào `errors`, rồi
  // ném lại để component tự quyết định toast/log các lỗi khác.
  const submit = async (fn) => {
    clearErrors();
    try {
      return await fn();
    } catch (error) {
      if (error.validationErrors) {
        Object.assign(errors, error.validationErrors);
      }
      throw error;
    }
  };

  return { errors, clearErrors, setErrors, submit };
}
