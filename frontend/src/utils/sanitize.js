import DOMPurify from 'dompurify';

// Làm sạch HTML do người dùng/seller nhập (mô tả sản phẩm...) trước khi render bằng v-html.
// Chống XSS: loại bỏ script, event handler (onerror, onclick...), và các thẻ nguy hiểm.
export function sanitizeHtml(dirty) {
    if (dirty == null) return '';
    return DOMPurify.sanitize(String(dirty), { USE_PROFILES: { html: true } });
}
