<script setup>
import { reactive, ref, onMounted, nextTick } from "vue";
import api from '@/axios';
import { useRouter, useRoute } from 'vue-router';
import Quill from "quill";
import "quill/dist/quill.snow.css";
import { Toast } from 'bootstrap';
import { getStorageUrl } from '@/utils/url';

const router = useRouter();
const route = useRoute();
const postId = route.params.id;

let quillContent = null;
const editorContent = ref(null);

const categories = ref([]);
const authors = ref([]);
const toastObj = ref({ message: '', type: 'success' });
const isSubmitting = ref(false);
const isLoading = ref(true);
const SUMMARY_MAX_LENGTH = 500;
const SEO_DESCRIPTION_MAX_LENGTH = 500;

const showToast = (message, type = 'success') => {
  toastObj.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('postToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 2500 }).show();
  });
};

const initQuill = () => {
    const CustomImageHandler = () => {
        const input = document.createElement("input");
        input.setAttribute("type", "file");
        input.setAttribute("accept", "image/*");
        input.click();

        input.onchange = async () => {
            const file = input.files[0];
            if (file) {
                const fd = new FormData();
                fd.append("image", file);

                try {
                    const res = await api.post("/posts/upload-image", fd, {
                        headers: { "Content-Type": "multipart/form-data" },
                    });
                    const url = res.data.url;
                    const range = quillContent.getSelection();
                    if(range){
                        quillContent.insertEmbed(range.index, "image", url);
                    } else {
                        quillContent.insertEmbed(0, "image", url);
                    }
                } catch (e) {
                    showToast("Lỗi tải ảnh lên", "danger");
                }
            }
        };
    };

    const modules = {
        toolbar: {
            container: [
                [{ header: [1, 2, 3, false] }],
                ["bold", "italic", "underline"],
                [{ list: "ordered" }, { list: "bullet" }],
                ["link", "image"],
            ],
            handlers: {
                image: CustomImageHandler,
            },
        },
    };

    if (editorContent.value && !quillContent) {
        quillContent = new Quill(editorContent.value, {
            theme: "snow",
            placeholder: "Nhập nội dung chi tiết bài viết...",
            modules,
        });
        if (post.content) {
            quillContent.root.innerHTML = post.content;
        }
        quillContent.on("text-change", () => {
            post.content =
                quillContent.root.innerHTML === "<p><br></p>"
                    ? ""
                    : quillContent.root.innerHTML;
        });
    }
};

const fetchDependencies = async () => {
    try {
        const catRes = await api.get('/post-categories');
        if(catRes.data && catRes.data.data) {
             categories.value = catRes.data.data;
        }
    } catch(err) {
        console.error("Lỗi tải danh mục", err);
    }
};

const fetchPost = async () => {
    try {
        const res = await api.get(`/posts/edit/${postId}`);
        const data = res.data || res.data.data;
        if(data) {
            post.title = data.title;
            post.summary = data.summary || "";
            post.content = data.content || "";
            post.post_category_id = data.post_category_id;
            post.post_type = data.post_type || "news";
            post.status = data.status || "draft";
            post.is_featured = !!data.is_featured;
            post.published_at = data.published_at ? data.published_at.substring(0, 16) : "";
            post.seo_title = data.seo_title || "";
            post.seo_description = data.seo_description || "";
            post.seo_keywords = data.seo_keywords || "";
            
            if(data.thumbnail_url) {
                post.thumbnailPreview = getStorageUrl(data.thumbnail_url);
            }
        }
    } catch(err) {
        showToast("Lỗi tải thông tin bài viết", "danger");
    } finally {
        isLoading.value = false;
        nextTick(() => initQuill());
    }
};

onMounted(async () => {
    await fetchDependencies();
    await fetchPost();
});

const handleThumbnailChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        post.thumbnailFile = file;
        post.thumbnailPreview = URL.createObjectURL(file);
    }
};

const post = reactive({
    title: "",
    summary: "",
    content: "",
    post_category_id: "",
    post_type: "news",
    status: "draft",
    is_featured: false,
    view_count: 0,
    published_at: "",
    thumbnailFile: null,
    thumbnailPreview: null,
    seo_title: "",
    seo_description: "",
    seo_keywords: ""
});

const handleSubmit = async () => {
    if (!post.title || !post.post_category_id) {
         showToast("Vui lòng nhập tiêu đề và chọn danh mục!", "danger");
         return;
    }

    if (post.summary && post.summary.length > SUMMARY_MAX_LENGTH) {
        showToast(`Tóm tắt nội dung không được vượt quá ${SUMMARY_MAX_LENGTH} ký tự.`, "danger");
        return;
    }

    if (post.seo_description && post.seo_description.length > SEO_DESCRIPTION_MAX_LENGTH) {
        showToast(`Mô tả SEO không được vượt quá ${SEO_DESCRIPTION_MAX_LENGTH} ký tự.`, "danger");
        return;
    }

    isSubmitting.value = true;
    const formData = new FormData();
    formData.append("_method", "PUT");
    formData.append("title", post.title);
    if(post.summary) formData.append("summary", post.summary);
    formData.append("content", post.content);
    formData.append("post_category_id", post.post_category_id);
    formData.append("post_type", post.post_type);
    formData.append("status", post.status);
    formData.append("is_featured", post.is_featured ? 1 : 0);
    
    if (post.view_count) formData.append("view_count", post.view_count);
    if (post.published_at) formData.append("published_at", post.published_at);
    
    if (post.seo_title) formData.append("seo_title", post.seo_title);
    if (post.seo_description) formData.append("seo_description", post.seo_description);
    if (post.seo_keywords) formData.append("seo_keywords", post.seo_keywords);

    if (post.thumbnailFile) {
        formData.append("thumbnail", post.thumbnailFile);
    }

    try {
        const response = await api.post(`/posts/${postId}`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            }
        });
        showToast(response.data?.message || "Cập nhật bài viết thành công!", "success");
        setTimeout(() => {
             router.push('/admin/post');
        }, 1500);
    } catch (error) {
        showToast(error.response?.data?.message || "Có lỗi xảy ra khi cập nhật bài viết", "danger");
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div class="edit-post-page">
        <div v-if="isLoading">
            <div class="spinner-border text-primary text-center" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <template v-else>
            <!-- Page Header -->
            <div class="page-header animate-in">
                <div class="header-info">
                    <h1 class="page-title">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Sửa bài viết
                    </h1>
                    <p class="page-subtitle">Cập nhật nội dung bài viết #{{ postId }}</p>
                </div>
                <div class="header-actions">
                    <button type="button" class="btn-outline" @click="$router.push('/admin/post')">Hủy bỏ</button>
                    <button type="submit" form="edit-post-form" class="btn-primary" :disabled="isSubmitting">
                        <svg v-if="!isSubmitting" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span>{{ isSubmitting ? 'Đang lưu...' : 'Lưu cập nhật' }}</span>
                    </button>
                </div>
            </div>

            <form id="edit-post-form" @submit.prevent="handleSubmit" class="main-content animate-in" style="animation-delay: 0.1s">
                <div class="form-grid">
                    <!-- Cột trái (Nội dung chính) -->
                    <div class="content-col">
                        <div class="ocean-card form-section">
                            <h3 class="section-title">Thông tin cơ bản</h3>
                            <div class="form-group">
                                <label for="title">Tiêu đề bài viết <span class="required">*</span></label>
                                <input type="text" id="title" v-model="post.title" class="form-control" placeholder="Nhập tiêu đề..." required />
                            </div>
                            <div class="form-group">
                                <label for="summary">Tóm tắt nội dung</label>
                                <textarea
                                    id="summary"
                                    v-model="post.summary"
                                    class="form-control"
                                    rows="3"
                                    :maxlength="SUMMARY_MAX_LENGTH"
                                    placeholder="Nhập đoạn tóm tắt ngắn gọn..."
                                ></textarea>
                                <small class="field-hint">{{ post.summary.length }}/{{ SUMMARY_MAX_LENGTH }}</small>
                            </div>
                            <div class="form-group">
                                <label>Nội dung chi tiết</label>
                                <div class="quill-wrapper editor-long">
                                    <div ref="editorContent"></div>
                                </div>
                            </div>
                        </div>

                        <div class="ocean-card form-section">
                            <h3 class="section-title">SEO Tùy chỉnh (Tùy chọn)</h3>
                            <div class="form-group">
                                <label for="seo_title">Tiêu đề SEO</label>
                                <input type="text" id="seo_title" v-model="post.seo_title" class="form-control" placeholder="Tối đa 60 ký tự" />
                            </div>
                            <div class="form-group">
                                <label for="seo_description">Mô tả SEO</label>
                                <textarea
                                    id="seo_description"
                                    v-model="post.seo_description"
                                    class="form-control"
                                    rows="2"
                                    :maxlength="SEO_DESCRIPTION_MAX_LENGTH"
                                    placeholder="Tối đa 500 ký tự"
                                ></textarea>
                                <small class="field-hint">{{ post.seo_description.length }}/{{ SEO_DESCRIPTION_MAX_LENGTH }}</small>
                            </div>
                            <div class="form-group">
                                <label for="seo_keywords">Từ khóa SEO</label>
                                <input type="text" id="seo_keywords" v-model="post.seo_keywords" class="form-control" placeholder="Cách nhau bởi dấu phẩy" />
                            </div>
                        </div>
                    </div>

                    <!-- Cột phải (Cài đặt) -->
                    <div class="sidebar-col">
                        <div class="ocean-card form-section">
                            <h3 class="section-title">Trạng thái & Phân loại</h3>
                            <div class="form-group">
                                <label for="post_category_id">Danh mục bài viết <span class="required">*</span></label>
                                <select id="post_category_id" v-model="post.post_category_id" class="form-control form-select" required>
                                    <option value="" disabled>— Chọn danh mục —</option>
                                    <option v-for="cat in categories" :key="cat.post_category_id" :value="cat.post_category_id">
                                        {{ cat.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="post_type">Loại bài viết</label>
                                <select id="post_type" v-model="post.post_type" class="form-control form-select">
                                    <option value="news">Tin tức</option>
                                    <option value="promotion">Khuyến mãi</option>
                                    <option value="guide">Hướng dẫn</option>
                                    <option value="review">Đánh giá</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Trạng thái</label>
                                <select id="status" v-model="post.status" class="form-control form-select">
                                    <option value="draft">Bản nháp</option>
                                    <option value="published">Xuất bản</option>
                                    <option value="hidden">Đang ẩn</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="published_at">Ngày đăng dự kiến</label>
                                <input type="datetime-local" id="published_at" v-model="post.published_at" class="form-control" />
                            </div>
                            <div class="form-group mt-3">
                                <label class="toggle-switch-wrapper">
                                    <strong style="font-size:0.85rem">Bài viết nổi bật</strong>
                                    <div class="toggle-switch">
                                        <input type="checkbox" v-model="post.is_featured" class="toggle-input" />
                                        <span class="toggle-slider"></span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="ocean-card form-section">
                            <h3 class="section-title">Hình ảnh</h3>
                            <div class="form-group">
                                <label>Ảnh Thumbnail (Nhỏ gọn)</label>
                                <div class="image-upload-box">
                                    <input type="file" id="thumbnail" @change="handleThumbnailChange" class="file-input" accept="image/*" />
                                    <label for="thumbnail" class="upload-label" v-if="!post.thumbnailPreview">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        <span>Tải ảnh lên</span>
                                    </label>
                                    <div class="preview-box" v-else>
                                        <img :src="post.thumbnailPreview" alt="Thumbnail Preview" class="img-preview" />
                                        <button type="button" class="btn-remove-img" @click="() => { post.thumbnailPreview = null; post.thumbnailFile = null}">×</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </template>

        <!-- Bootstrap Toast -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
            <div class="toast align-items-center border-0" :class="toastObj.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="postToast" role="alert">
                <div class="d-flex">
                    <div class="toast-body">{{ toastObj.message }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.edit-post-page { font-family: var(--font-inter); padding-bottom: 2rem; }

/* Header */
.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
}
.page-title {
    font-size: 1.5rem; font-weight: 800; color: var(--text-main);
    display: flex; align-items: center; gap: 12px;
}
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; font-weight: 500; }
.header-actions { display: flex; gap: 12px; }

/* Buttons */
.btn-primary {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 22px; border-radius: 8px; border: none;
    background: #E63B6F; color: white;
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2);
}
.btn-primary:hover {
    background: var(--ocean-bright); transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(3, 169, 244, 0.3);
}
.btn-primary:disabled { opacity: 0.7; transform: none; cursor: not-allowed; }

.btn-outline {
    padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-main);
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.btn-outline:hover { border-color: var(--text-light); }

/* Layout Grid */
.form-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}
@media (max-width: 992px) {
    .form-grid { grid-template-columns: 1fr; }
}

.form-section { padding: 24px; margin-bottom: 24px; }
.section-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;}

/* Form controls */
.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
.required { color: var(--coral); }
.form-control {
    width: 100%; padding: 10px 14px; border-radius: 8px;
    border: 1px solid var(--border-color); background: var(--ocean-deepest);
    color: var(--text-main); font-family: var(--font-inter);
    font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box;
}
.form-control:focus { border-color: #E63B6F; outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); background: white;}
.form-control::placeholder { color: var(--text-light); }
.field-hint { display: block; margin-top: 4px; font-size: 0.75rem; color: var(--text-muted); text-align: right; }
.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 14px center;
}

/* Image Upload */
.image-upload-box {
    border: 2px dashed var(--border-color); border-radius: 8px;
    padding: 0; text-align: center; position: relative; overflow: hidden;
    background: var(--ocean-deepest); transition: all 0.2s;
    min-height: 140px; display: flex; align-items: center; justify-content: center;
}
.image-upload-box:hover { border-color: #E63B6F; background: #FFF0F3; }
.file-input { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10; }
.upload-label { display: flex; flex-direction: column; align-items: center; gap: 8px; color: var(--text-light); pointer-events: none;}
.upload-label svg { color: #E63B6F; }
.preview-box { width: 100%; height: 100%; position: absolute; top: 0; left: 0; }
.img-preview { width: 100%; height: 100%; object-fit: cover; }
.btn-remove-img {
    position: absolute; top: 8px; right: 8px; width: 26px; height: 26px; z-index: 20;
    background: rgba(255, 59, 48, 0.9); color: white; border: none; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    font-size: 1.2rem; line-height: 1; transition: all 0.2s;
}
.btn-remove-img:hover { transform: scale(1.1); }

/* Toggle */
.toggle-switch-wrapper { display: flex; align-items: center; justify-content: space-between; cursor: pointer; width: 100%; }
.toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.toggle-input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--text-light); transition: .3s; border-radius: 24px; }
.toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
.toggle-input:checked + .toggle-slider { background-color: #E63B6F; }
.toggle-input:checked + .toggle-slider:before { transform: translateX(20px); }

/* Quill Custom Styles */
.quill-wrapper { display: flex; flex-direction: column; }
.quill-wrapper :deep(.ql-toolbar.ql-snow) {
    border: 1px solid var(--border-color); border-top-left-radius: 8px; border-top-right-radius: 8px;
    background: var(--ocean-deepest); font-family: var(--font-inter); transition: border-color 0.2s;
}
.quill-wrapper :deep(.ql-container.ql-snow) {
    border: 1px solid var(--border-color); border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;
    border-top: none; font-family: var(--font-inter); font-size: 0.95rem; background: white; transition: border-color 0.2s;
}
.quill-wrapper:focus-within :deep(.ql-toolbar.ql-snow) { border-color: #E63B6F; }
.quill-wrapper:focus-within :deep(.ql-container.ql-snow) { border-color: #E63B6F; box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); }
.quill-wrapper :deep(.ql-editor) { color: var(--text-main); }
.editor-long :deep(.ql-editor) { min-height: 350px; }
</style>
