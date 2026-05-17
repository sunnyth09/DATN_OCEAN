<script setup>
import { ref, reactive, onMounted, computed, nextTick } from "vue";
import { useRouter, useRoute } from "vue-router";
import api from "@/axios";
import Swal from 'sweetalert2';
import AdminCategoryFormTree from "@/components/AdminCategoryFormTree.vue";
import Quill from "quill";
import "quill/dist/quill.snow.css";

let quillShort = null;
let quillLong = null;
const editorShort = ref(null);
const editorLong = ref(null);

const initQuill = () => {
    const modules = {
        toolbar: [
            [{ header: [1, 2, 3, false] }],
            ["bold", "italic", "underline"],
            [{ list: "ordered" }, { list: "bullet" }],
            ["link"],
        ],
    };
    if (editorShort.value && !quillShort) {
        quillShort = new Quill(editorShort.value, {
            theme: "snow",
            placeholder: "Nhập mô tả ngắn gọn...",
            modules,
        });
        if (product.short_description) quillShort.root.innerHTML = product.short_description;
        quillShort.on("text-change", () => {
            product.short_description = quillShort.root.innerHTML === "<p><br></p>" ? "" : quillShort.root.innerHTML;
        });
    }
    if (editorLong.value && !quillLong) {
        quillLong = new Quill(editorLong.value, {
            theme: "snow",
            placeholder: "Nhập chi tiết sản phẩm...",
            modules,
        });
        if (product.description) quillLong.root.innerHTML = product.description;
        quillLong.on("text-change", () => {
            product.description = quillLong.root.innerHTML === "<p><br></p>" ? "" : quillLong.root.innerHTML;
        });
    }
};

const router = useRouter();
const route = useRoute();
const productId = computed(() => route.params.id);

const categories = ref([]);
const brands = ref([]);
const errors = ref({});
const isLoading = ref(true);
const isSaving = ref(false);

const product = reactive({
    category_id: "",
    brand_id: "",
    seller_id: "",
    name: "",
    slug: "",
    short_description: "",
    description: "",
    thumbnail_url: "",
    imagePreview: "",
    product_type: "simple",
    status: "draft",
    is_featured: false,
    price: "",
    compare_at_price: "",
    stock: "",
    // Sale fields (simple product)
    has_sale: false,
    sale_price: "",
    sale_starts_at: "",
    sale_ends_at: "",
    variants: [],
    gallery_files: [],
    galleryPreviews: [],
    existing_gallery: [],
    deleted_gallery_ids: [],
    deleted_variant_image_ids: [],
});

const storageUrl = import.meta.env.VITE_API_STORAGE || "http://localhost:8383/storage";

const handleFetchCategories = async () => {
    try {
        const res = await api.get("/categories");
        categories.value = res.data.data || res.data;
    } catch (e) { console.error(e); }
};

const handleFetchBrands = async () => {
    try {
        const res = await api.get("/brands");
        brands.value = res.data;
    } catch (e) { console.error(e); }
};

const fetchProduct = async () => {
    try {
        const res = await api.get(`/products/edit/${productId.value}`);
        const p = res.data;
        product.category_id = p.category_id || "";
        product.brand_id = p.brand_id || "";
        product.seller_id = p.seller_id || "";
        product.name = p.name || "";
        product.slug = p.slug || "";
        product.short_description = p.short_description || "";
        product.description = p.description || "";
        product.product_type = p.product_type || "simple";
        product.status = p.status || "draft";
        product.is_featured = !!p.is_featured;
        product.thumbnail_url = p.thumbnail_url || "";
        product.imagePreview = p.thumbnail_url ? `${storageUrl}/${p.thumbnail_url}` : "";

        // Gallery (exclude main + variant images)
        if (p.images && p.images.length > 0) {
            product.existing_gallery = p.images.filter((img) => !img.is_main && !img.variant_id);
        }

        // Simple product
        if (p.product_type === "simple" && p.variants && p.variants.length > 0) {
            const defVariant = p.variants[0];
            product.price = defVariant.price || "";
            product.compare_at_price = defVariant.compare_at_price || "";
            product.stock = defVariant.stock || "";
            if (defVariant.sale_price) {
                product.has_sale = true;
                product.sale_price = defVariant.sale_price;
                product.sale_starts_at = defVariant.sale_starts_at ? defVariant.sale_starts_at.slice(0, 16) : "";
                product.sale_ends_at = defVariant.sale_ends_at ? defVariant.sale_ends_at.slice(0, 16) : "";
            }
        }

        // Variant product: group variants by color
        if (p.product_type === "variant" && p.variants && p.variants.length > 0) {
            const colorMap = {};
            p.variants.forEach((v) => {
                const c = v.color || "default";
                if (!colorMap[c]) {
                    colorMap[c] = {
                        color: v.color || "",
                        sizes: [],
                        images: [],
                        imagePreviews: [],
                        existingImages: [],
                        _variantIds: [], // thu thập tất cả variant_id cùng màu
                    };
                }
                colorMap[c]._variantIds.push(v.variant_id);
                colorMap[c].sizes.push({
                    size: v.size || "",
                    price: v.price || 0,
                    stock: v.stock || 0,
                    has_sale: !!v.sale_price,
                    sale_price: v.sale_price || "",
                    sale_starts_at: v.sale_starts_at ? v.sale_starts_at.slice(0, 16) : "",
                    sale_ends_at: v.sale_ends_at ? v.sale_ends_at.slice(0, 16) : "",
                });
            });

            // Thu thập ảnh từ TẤT CẢ variants cùng màu (không chỉ variant đầu tiên)
            Object.values(colorMap).forEach((group) => {
                const seenIds = new Set();
                const variantImgs = (p.images || []).filter(
                    (img) => img.variant_id && group._variantIds.includes(img.variant_id)
                );
                group.existingImages = variantImgs
                    .filter((img) => { if (seenIds.has(img.image_id)) return false; seenIds.add(img.image_id); return true; })
                    .map((img) => ({ image_id: img.image_id, url: `${storageUrl}/${img.image_url}` }));
                delete group._variantIds; // dọn dẹp
            });

            product.variants = Object.values(colorMap);
        }
    } catch (e) {
        console.error("Error fetching product:", e);
        Swal.fire('Lỗi', 'Không thể tải thông tin sản phẩm.', 'error');
    } finally {
        isLoading.value = false;
        nextTick(() => initQuill());
    }
};

// ===== Variants =====
const addVariant = () => {
    product.variants.push({ color: "", images: [], imagePreviews: [], existingImages: [], sizes: [{ size: "", stock: 0, price: 0, has_sale: false, sale_price: "", sale_starts_at: "", sale_ends_at: "" }] });
};
const removeVariant = (i) => product.variants.splice(i, 1);
const addSize = (vi) => product.variants[vi].sizes.push({ size: "", stock: 0, price: 0, has_sale: false, sale_price: "", sale_starts_at: "", sale_ends_at: "" });
const removeSize = (vi, si) => product.variants[vi].sizes.splice(si, 1);

// ===== Images =====
const handleThumbnailChange = (e) => {
    const f = e.target.files[0];
    if (f) { product.thumbnail_url = f; product.imagePreview = URL.createObjectURL(f); }
};
const handleGalleryChange = (e) => {
    Array.from(e.target.files).forEach((f) => {
        product.gallery_files.push(f);
        product.galleryPreviews.push(URL.createObjectURL(f));
    });
    e.target.value = "";
};
const removeNewGalleryImage = (i) => { product.gallery_files.splice(i, 1); product.galleryPreviews.splice(i, 1); };
const removeExistingGalleryImage = (i, id) => { product.existing_gallery.splice(i, 1); product.deleted_gallery_ids.push(id); };

// Tính % giảm giá preview (simple)
const simpleSalePercent = computed(() => {
    if (!product.has_sale || !product.sale_price || !product.price) return 0;
    return Math.round((product.price - product.sale_price) / product.price * 100);
});

const handleVariantImageChange = (e, vi) => {
    Array.from(e.target.files).forEach((f) => {
        product.variants[vi].images.push(f);
        product.variants[vi].imagePreviews.push(URL.createObjectURL(f));
    });
    e.target.value = "";
};
const removeVariantImage = (vi, ii) => { product.variants[vi].images.splice(ii, 1); product.variants[vi].imagePreviews.splice(ii, 1); };
const removeExistingVariantImage = (vi, ii) => {
    const removed = product.variants[vi].existingImages.splice(ii, 1);
    if (removed[0]?.image_id) {
        product.deleted_variant_image_ids.push(removed[0].image_id);
    }
};

// ===== Validate =====
const validateForm = () => {
    errors.value = {};
    let ok = true;
    if (!product.name) { errors.value.name = "Tên sản phẩm là bắt buộc"; ok = false; }
    if (!product.category_id) { errors.value.category_id = "Danh mục là bắt buộc"; ok = false; }
    if (product.product_type === "simple") {
        if (!product.price) { errors.value.price = "Giá bán là bắt buộc"; ok = false; }
        else if (Number(product.price) < 100000) { errors.value.price = "Giá bán tối thiểu là 100.000đ"; ok = false; }
        if (product.stock === "" || product.stock === null) { errors.value.stock = "Số lượng kho là bắt buộc"; ok = false; }
        if (product.has_sale) {
            if (!product.sale_price) { errors.value.sale_price = "Bắt buộc"; ok = false; }
            else if (Number(product.sale_price) < 1) { errors.value.sale_price = "Phải > 0đ"; ok = false; }
        }
    } else {
        if (!product.variants.length) { errors.value.variants_global = "Cần ít nhất một biến thể"; ok = false; }
        else {
            const colorSet = new Set();
            const combos = new Set();
            errors.value.variants = [];
            product.variants.forEach((v, vi) => {
                const ve = {};
                if (!v.color) { ve.color = "Màu sắc là bắt buộc"; ok = false; }
                else {
                    const ck = v.color.trim().toLowerCase();
                    if (colorSet.has(ck)) { ve.color = `Màu "${v.color}" đã bị trùng`; ok = false; }
                    else colorSet.add(ck);
                }
                if (!v.sizes.length) { ve.sizes_global = "Cần ít nhất một kích cỡ"; ok = false; }
                else {
                    ve.sizes = [];
                    const sizeSet = new Set();
                    v.sizes.forEach((s, si) => {
                        const se = {};
                        if (s.size) {
                            const sk = s.size.trim().toLowerCase();
                            if (sizeSet.has(sk)) { se.size = `Size "${s.size}" bị trùng`; ok = false; }
                            else sizeSet.add(sk);
                        }
                        if (!s.price) { se.price = "Giá là bắt buộc"; ok = false; }
                        else if (Number(s.price) < 100000) { se.price = "Giá tối thiểu 100.000đ"; ok = false; }
                        if (s.stock === "" || s.stock === null) { se.stock = "Kho là bắt buộc"; ok = false; }
                        if (s.has_sale) {
                            if (!s.sale_price) { se.sale_price = "Bắt buộc"; ok = false; }
                            else if (Number(s.sale_price) < 1) { se.sale_price = "Phải > 0đ"; ok = false; }
                        }
                        const combo = `${(v.color || "").trim().toLowerCase()}-${(s.size || "").trim().toLowerCase()}`;
                        if (v.color && s.size && combos.has(combo)) { se.duplicate = "Biến thể trùng"; ok = false; }
                        else if (v.color && s.size) combos.add(combo);
                        ve.sizes[si] = se;
                    });
                }
                errors.value.variants[vi] = ve;
            });
        }
    }
    return ok;
};

// ===== Submit =====
const handleSubmit = async () => {
    if (!validateForm()) { window.scrollTo({ top: 0, behavior: "smooth" }); return; }
    isSaving.value = true;

    const fd = new FormData();
    fd.append("_method", "PUT");
    fd.append("name", product.name);
    fd.append("slug", product.slug);
    fd.append("category_id", product.category_id);
    fd.append("brand_id", product.brand_id || "");
    fd.append("seller_id", product.seller_id || "");
    fd.append("short_description", product.short_description || "");
    fd.append("description", product.description || "");
    fd.append("product_type", product.product_type);
    fd.append("status", product.status);
    fd.append("is_featured", product.is_featured ? "1" : "0");

    if (product.thumbnail_url instanceof File) fd.append("thumbnail", product.thumbnail_url);

    product.gallery_files.forEach((f, i) => { if (f instanceof File) fd.append(`gallery[${i}]`, f); });
    product.deleted_gallery_ids.forEach((id, i) => fd.append(`deleted_gallery_ids[${i}]`, id));

    if (product.product_type === "simple") {
        fd.append("price", product.price);
        fd.append("compare_at_price", product.compare_at_price || "");
        fd.append("stock", product.stock);
        if (product.has_sale && product.sale_price) {
            fd.append("sale_price", product.sale_price);
            fd.append("sale_starts_at", product.sale_starts_at || "");
            fd.append("sale_ends_at", product.sale_ends_at || "");
        }
    } else {
        fd.append("variants", JSON.stringify(product.variants.map((v) => ({
            color: v.color,
            sizes: v.sizes.map((s) => ({
                size: s.size,
                stock: s.stock,
                price: s.price,
                sale_price: s.has_sale ? s.sale_price : null,
                sale_starts_at: s.has_sale ? s.sale_starts_at : null,
                sale_ends_at: s.has_sale ? s.sale_ends_at : null,
            }))
        }))));
        product.variants.forEach((v, vi) => {
            v.images.forEach((f, ii) => fd.append(`variant_images[${vi}][${ii}]`, f));
        });
        // Gửi danh sách ảnh biến thể bị xóa bởi user
        product.deleted_variant_image_ids.forEach((id, i) => {
            fd.append(`deleted_variant_image_ids[${i}]`, id);
        });
    }

    try {
        await api.post(`/products/${productId.value}`, fd, { headers: { "Content-Type": "multipart/form-data" } });
        Swal.fire('Thành công', 'Cập nhật sản phẩm thành công!', 'success');
        router.push("/admin/product");
    } catch (e) {
        console.error("Error:", e.response?.data || e);
        if (e.response?.data?.errors) errors.value = e.response.data.errors;
        Swal.fire('Lỗi', e.response?.data?.message || "Có lỗi xảy ra khi cập nhật.", 'error');
    } finally {
        isSaving.value = false;
    }
};

onMounted(() => { handleFetchCategories(); handleFetchBrands(); fetchProduct(); });
</script>

<template>
    <div class="create-product-page">
        <div v-if="isLoading" class="loading-state">
            <div class="spinner"></div>
            <p>Đang tải thông tin sản phẩm...</p>
        </div>

        <form v-else @submit.prevent="handleSubmit" novalidate enctype="multipart/form-data">
            <div class="page-header animate-in">
                <div class="header-info">
                    <div class="back-link">
                        <router-link to="/admin/product">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            Trở Về
                        </router-link>
                    </div>
                    <h1 class="page-title">Chỉnh Sửa Sản Phẩm</h1>
                    <p class="page-subtitle">Cập nhật thông tin sản phẩm #{{ productId }}</p>
                </div>
                <div class="header-actions">
                    <router-link to="/admin/product" class="btn-outline">Hủy bỏ</router-link>
                    <button type="submit" class="btn-primary" :disabled="isSaving">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        {{ isSaving ? 'Đang lưu...' : 'Lưu Thay Đổi' }}
                    </button>
                </div>
            </div>

            <div class="form-container">
                <!-- Left Column -->
                <div class="form-column main-col">
                    <div class="ocean-card form-card animate-in" style="animation-delay: 0.1s">
                        <h3 class="card-title">Thông Tin Cơ Bản</h3>
                        <div class="form-group">
                            <label>Tên Sản Phẩm <span class="required">*</span></label>
                            <input type="text" v-model="product.name" class="form-control" :class="{'is-invalid': errors.name}" placeholder="Ví dụ: Đồng Hồ Xanh Đại Dương" />
                            <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
                        </div>
                        <div class="form-group">
                            <label>Mô Tả Ngắn</label>
                            <div class="quill-wrapper editor-short"><div ref="editorShort"></div></div>
                        </div>
                        <div class="form-group">
                            <label>Mô Tả Chi Tiết</label>
                            <div class="quill-wrapper editor-long"><div ref="editorLong"></div></div>
                        </div>
                    </div>

                    <!-- Price -->
                    <div class="ocean-card form-card animate-in" style="animation-delay: 0.2s">
                        <h3 class="card-title">Giá và Loại Sản Phẩm</h3>
                        <div class="info-badge mb-3">
                            Loại: <strong>{{ product.product_type === 'simple' ? 'Sản Phẩm Đơn' : 'Sản Phẩm Biến Thể' }}</strong>
                        </div>
                        <div class="price-grid" v-if="product.product_type === 'simple'">
                            <div class="form-group">
                                <label>Giá Bán <span class="required">*</span></label>
                                <div class="input-with-prefix">
                                    <span class="prefix">₫</span>
                                    <input type="number" min="100000" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();" v-model="product.price" class="form-control" :class="{'is-invalid': errors.price}" placeholder="0" />
                                </div>
                                <span v-if="errors.price" class="field-error">{{ errors.price }}</span>
                            </div>
                            <div class="form-group">
                                <label>Giá Gốc Trước Giảm</label>
                                <div class="input-with-prefix">
                                    <span class="prefix">₫</span>
                                    <input type="number" min="100000" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();" v-model="product.compare_at_price" class="form-control" placeholder="0" />
                                </div>
                            </div>
                            <div class="form-group" style="grid-column: span 2">
                                <label>Số Lượng Kho <span class="required">*</span></label>
                                <input type="number" min="0" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();" v-model="product.stock" class="form-control" :class="{'is-invalid': errors.stock}" placeholder="0" />
                                <span v-if="errors.stock" class="field-error">{{ errors.stock }}</span>
                            </div>
                        </div>

                        <!-- Khuyến Mãi Theo Thời Gian (Simple) -->
                        <div class="sale-promo-section" v-if="product.product_type === 'simple'">
                            <label class="toggle-switch-wrapper sale-toggle">
                                <span class="toggle-label">
                                    <strong>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px">
                                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
                                            <line x1="7" y1="7" x2="7.01" y2="7"/>
                                        </svg>
                                        Khuyến Mãi Theo Thời Gian
                                    </strong>
                                    <span>Thiết lập giá giảm có thời hạn</span>
                                </span>
                                <div class="toggle-switch">
                                    <input type="checkbox" v-model="product.has_sale" class="toggle-input" />
                                    <span class="toggle-slider"></span>
                                </div>
                            </label>
                            <Transition name="slide-fade">
                                <div v-if="product.has_sale" class="sale-fields-grid">
                                    <div class="form-group">
                                        <label>Giá Khuyến Mãi</label>
                                        <div class="input-with-prefix" :class="{'is-invalid': errors.sale_price}">
                                            <span class="prefix">₫</span>
                                            <input type="number" min="1" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();" v-model="product.sale_price" class="form-control" placeholder="0" />
                                        </div>
                                        <span v-if="errors.sale_price" class="field-error">{{ errors.sale_price }}</span>
                                        <span v-if="simpleSalePercent > 0 && !errors.sale_price" class="sale-preview-badge">Giảm {{ simpleSalePercent }}%</span>
                                    </div>
                                    <div class="form-group">
                                        <label>Bắt Đầu</label>
                                        <input type="datetime-local" v-model="product.sale_starts_at" class="form-control" />
                                    </div>
                                    <div class="form-group">
                                        <label>Kết Thúc</label>
                                        <input type="datetime-local" v-model="product.sale_ends_at" class="form-control" />
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>

                    <!-- Variants -->
                    <div v-if="product.product_type === 'variant'" class="ocean-card form-card animate-in" style="animation-delay: 0.3s">
                        <div class="card-header-flex">
                            <h3 class="card-title">Biến Thể Sản Phẩm ({{ product.variants.length }})</h3>
                            <button class="btn-outline-small" type="button" @click.prevent="addVariant">
                                + Thêm Biến Thể
                            </button>
                        </div>

                        <div class="variant-item" v-for="(variant, vIndex) in product.variants" :key="vIndex">
                            <div class="variant-header">
                                <h4>Biến thể #{{ vIndex + 1 }}: {{ variant.color || 'Chưa đặt tên' }}</h4>
                                <button class="btn-icon-danger" type="button" @click.prevent="removeVariant(vIndex)">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </div>
                            <div class="variant-body">
                                <div class="form-group">
                                    <label>Tên Màu Sắc / Kiểu Dáng</label>
                                    <input type="text" v-model="variant.color" class="form-control" :class="{'is-invalid': errors.variants && errors.variants[vIndex]?.color}" placeholder="Ví dụ: Xanh Đại Dương" />
                                    <span v-if="errors.variants && errors.variants[vIndex]?.color" class="field-error">{{ errors.variants[vIndex].color }}</span>
                                </div>
                                <div class="form-group">
                                    <label>Hình Ảnh Biến Thể</label>
                                    <div class="variant-images-grid">
                                        <div v-for="(ei, ii) in variant.existingImages" :key="'ex-'+ii" class="variant-img-item">
                                            <img :src="ei.url" alt="Existing" />
                                            <button type="button" class="remove-img-btn" @click.prevent="removeExistingVariantImage(vIndex, ii)">×</button>
                                        </div>
                                        <div v-for="(preview, ii) in variant.imagePreviews" :key="'new-'+ii" class="variant-img-item">
                                            <img :src="preview" alt="New" />
                                            <button type="button" class="remove-img-btn" @click.prevent="removeVariantImage(vIndex, ii)">×</button>
                                        </div>
                                        <div class="variant-img-add">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.5">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                            <span>Thêm ảnh</span>
                                            <input type="file" class="file-input-hide" accept="image/*" @change="(e) => handleVariantImageChange(e, vIndex)" multiple />
                                        </div>
                                    </div>
                                </div>
                                <div class="sizes-section">
                                    <label>Kích Cỡ / Số Lượng</label>
                                    <table class="sizes-table">
                                        <thead><tr><th>Size (tùy chọn)</th><th>Kho</th><th>Giá (₫)</th><th></th></tr></thead>
                                        <tbody>
                                            <template v-for="(s, sIndex) in variant.sizes" :key="sIndex">
                                                <tr>
                                                    <td>
                                                        <input type="text" v-model="s.size" class="form-control input-sm" :class="{ 'is-invalid': errors.variants?.[vIndex]?.sizes?.[sIndex]?.size, 'input-error': errors.variants?.[vIndex]?.sizes?.[sIndex]?.size }" placeholder="Để trống nếu không cần" />
                                                        <span v-if="errors.variants?.[vIndex]?.sizes?.[sIndex]?.size" class="field-error">{{ errors.variants[vIndex].sizes[sIndex].size }}</span>
                                                    </td>
                                                    <td>
                                                        <input type="number" min="0" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();" v-model="s.stock" class="form-control input-sm" :class="{ 'is-invalid': errors.variants?.[vIndex]?.sizes?.[sIndex]?.stock, 'input-error': errors.variants?.[vIndex]?.sizes?.[sIndex]?.stock }" placeholder="0" />
                                                        <span v-if="errors.variants?.[vIndex]?.sizes?.[sIndex]?.stock" class="field-error">{{ errors.variants[vIndex].sizes[sIndex].stock }}</span>
                                                    </td>
                                                    <td>
                                                        <input type="number" min="100000" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();" v-model="s.price" class="form-control input-sm" :class="{ 'is-invalid': errors.variants?.[vIndex]?.sizes?.[sIndex]?.price, 'input-error': errors.variants?.[vIndex]?.sizes?.[sIndex]?.price }" placeholder="0" />
                                                        <span v-if="errors.variants?.[vIndex]?.sizes?.[sIndex]?.price" class="field-error">{{ errors.variants[vIndex].sizes[sIndex].price }}</span>
                                                    </td>
                                                    <td class="action-cell">
                                                        <button class="btn-sale-toggle" :class="{ active: s.has_sale }" type="button" title="Thiết lập khuyến mãi" @click.prevent="s.has_sale = !s.has_sale">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
                                                                <line x1="7" y1="7" x2="7.01" y2="7"/>
                                                            </svg>
                                                        </button>
                                                        <button class="btn-icon-danger square" type="button" @click.prevent="removeSize(vIndex, sIndex)">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <!-- Sale row (expandable) -->
                                                <tr v-if="s.has_sale" class="sale-expand-row">
                                                    <td colspan="4" class="sale-expand-cell">
                                                        <div class="sale-inline-fields">
                                                            <div class="sale-inline-field">
                                                                <label>Giá KM (₫)</label>
                                                                <input type="number" min="1" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();" v-model="s.sale_price" class="form-control input-sm" :class="{ 'is-invalid': errors.variants?.[vIndex]?.sizes?.[sIndex]?.sale_price, 'input-error': errors.variants?.[vIndex]?.sizes?.[sIndex]?.sale_price }" placeholder="0" />
                                                                <span v-if="errors.variants?.[vIndex]?.sizes?.[sIndex]?.sale_price" class="field-error" style="margin-top:2px">{{ errors.variants[vIndex].sizes[sIndex].sale_price }}</span>
                                                                <span v-if="s.sale_price && s.price && !errors.variants?.[vIndex]?.sizes?.[sIndex]?.sale_price" class="sale-preview-badge small">
                                                                    -{{ Math.round((s.price - s.sale_price) / s.price * 100) }}%
                                                                </span>
                                                            </div>
                                                            <div class="sale-inline-field">
                                                                <label>Bắt đầu</label>
                                                                <input type="datetime-local" v-model="s.sale_starts_at" class="form-control input-sm" />
                                                            </div>
                                                            <div class="sale-inline-field">
                                                                <label>Kết thúc</label>
                                                                <input type="datetime-local" v-model="s.sale_ends_at" class="form-control input-sm" />
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr v-if="errors.variants?.[vIndex]?.sizes?.[sIndex]?.duplicate">
                                                    <td colspan="4" style="padding: 0 10px 10px;">
                                                        <span class="field-error" style="color: #c62828; margin-top: 0;">⚠ {{ errors.variants[vIndex].sizes[sIndex].duplicate }}</span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                    <button class="btn-text-link mt-2" type="button" @click.prevent="addSize(vIndex)">+ Thêm Kích Cỡ</button>
                                    <span v-if="errors.variants?.[vIndex]?.sizes_global" class="field-error" style="display: block; margin-top: 10px;">{{ errors.variants[vIndex].sizes_global }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="form-column side-col">
                    <div class="ocean-card form-card animate-in" style="animation-delay: 0.15s">
                        <h3 class="card-title">Hình Ảnh Sản Phẩm</h3>
                        <div class="form-group mb-0">
                            <label>Ảnh Bìa Chính</label>
                            <div class="image-upload-box">
                                <div v-if="product.imagePreview" class="preview-container">
                                    <img :src="product.imagePreview" alt="Preview" class="img-preview" />
                                    <button class="remove-img-btn" @click.prevent="product.imagePreview = ''; product.thumbnail_url = ''">×</button>
                                </div>
                                <div v-else class="upload-placeholder">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                    <span>Bấm để tải ảnh mới lên</span>
                                    <span class="upload-hint">Khuyến nghị: 800x800px</span>
                                    <input type="file" class="file-input-hide" accept="image/*" @change="handleThumbnailChange" />
                                </div>
                            </div>
                        </div>
                        <div class="form-group mt-4 mb-0">
                            <label>Ảnh Phụ (Nhiều ảnh)</label>
                            <div class="gallery-upload-container">
                                <div v-for="(img, i) in product.existing_gallery" :key="'ex-'+i" class="gallery-item">
                                    <img :src="`${storageUrl}/${img.image_url}`" alt="Gallery" />
                                    <button class="remove-img-btn" type="button" @click.prevent="removeExistingGalleryImage(i, img.image_id)">×</button>
                                </div>
                                <div v-for="(preview, i) in product.galleryPreviews" :key="'new-'+i" class="gallery-item">
                                    <img :src="preview" alt="New Gallery" />
                                    <button class="remove-img-btn" type="button" @click.prevent="removeNewGalleryImage(i)">×</button>
                                </div>
                                <div class="gallery-add-btn">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.5">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    <input type="file" class="file-input-hide" accept="image/*" multiple @change="handleGalleryChange" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ocean-card form-card animate-in" style="animation-delay: 0.25s">
                        <h3 class="card-title">Thông Tin Phân Loại</h3>
                        <div class="form-group">
                            <label>Danh Mục <span class="required">*</span></label>
                            <select v-model="product.category_id" class="form-control form-select" :class="{'is-invalid': errors.category_id}">
                                <option value="">Chọn danh mục</option>
                                <AdminCategoryFormTree :categories="categories" />
                            </select>
                            <span v-if="errors.category_id" class="field-error">{{ errors.category_id }}</span>
                        </div>
                        <div class="form-group">
                            <label>Thương Hiệu</label>
                            <select v-model="product.brand_id" class="form-control form-select">
                                <option value="">Chọn thương hiệu</option>
                                <option v-for="b in brands" :key="b.brand_id || b.id" :value="b.brand_id || b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="ocean-card form-card animate-in" style="animation-delay: 0.35s">
                        <h3 class="card-title">Trạng Thái</h3>
                        <div class="form-group">
                            <label>Trạng Thái</label>
                            <select v-model="product.status" class="form-control form-select">
                                <option value="draft">Bản Nháp</option>
                                <option value="active">Đang Bán</option>
                                <option value="inactive">Tạm Ẩn</option>
                                <option value="out_of_stock">Hết Hàng</option>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label class="toggle-switch-wrapper">
                                <span class="toggle-label">
                                    <strong>Sản Phẩm Nổi Bật</strong>
                                    <span>Hiển thị trên trang chủ</span>
                                </span>
                                <div class="toggle-switch">
                                    <input type="checkbox" v-model="product.is_featured" class="toggle-input" />
                                    <span class="toggle-slider"></span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>

<style scoped>
/* Validation Styles */
.field-error {
    color: #e53935;
    font-size: 0.8rem;
    margin-top: 4px;
    display: block;
}
.is-invalid {
    border-color: #e53935 !important;
    background-color: #fff2f2 !important;
}
.form-error-box {
    background-color: #fff2f2;
    border: 1px solid #e53935;
    color: #c62828;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 0.9rem;
}
.create-product-page { font-family: var(--font-inter); padding-bottom: 40px; }
.loading-state { text-align: center; padding: 80px 20px; color: var(--text-muted); font-weight: 600; }
.spinner { width: 30px; height: 30px; border: 3px solid var(--border-color); border-top-color: #E63B6F; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px; }
@keyframes spin { to { transform: rotate(360deg); } }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.back-link { margin-bottom: 8px; }
.back-link a { display: inline-flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
.back-link a:hover { color: #E63B6F; }
.page-title { font-size: 1.5rem; font-weight: 800; color: var(--text-main); line-height: 1.2; }
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; margin-top: 4px; }
.header-actions { display: flex; gap: 12px; }
.btn-primary { display: flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; border: none; background: #E63B6F; color: white; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 4px 10px rgba(230, 59, 111,0.2); transition: all 0.2s; text-decoration: none; }
.btn-primary:hover:not(:disabled) { background: var(--ocean-bright); transform: translateY(-2px); box-shadow: 0 6px 14px rgba(230, 59, 111,0.3); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-outline { padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color); background: white; color: var(--text-main); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; }
.btn-outline:hover { background: var(--ocean-deepest); border-color: var(--text-light); }
.btn-outline-small { display: flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; color: #E63B6F; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-outline-small:hover { background: rgba(230, 59, 111,0.05); border-color: #E63B6F; }
.btn-text-link { background: none; border: none; color: #E63B6F; font-size: 0.8rem; font-weight: 600; cursor: pointer; padding: 0; }
.btn-text-link:hover { text-decoration: underline; }
.btn-icon-danger { width: 28px; height: 28px; border-radius: 6px; border: 1px solid transparent; background: rgba(239,83,80,0.1); color: var(--coral); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
.btn-icon-danger:hover { background: var(--coral); color: white; }
.btn-icon-danger.square { width: 32px; height: 32px; border-radius: 6px; }
.form-container { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
@media (max-width: 900px) { .form-container { grid-template-columns: 1fr; } }
.form-column { display: flex; flex-direction: column; gap: 24px; }
.form-card { padding: 24px; }
.card-title { font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); }
.card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); }
.card-header-flex .card-title { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
.form-group { margin-bottom: 18px; }
.form-group.mb-0 { margin-bottom: 0; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
.required { color: var(--coral); }
.form-control { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: white; color: var(--text-main); font-family: var(--font-inter); font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box; }
.form-control:focus { border-color: #E63B6F; outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111,0.1); }
.form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; }
.input-sm { padding: 8px 10px; font-size: 0.8rem; }
.price-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.input-with-prefix { display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: white; transition: all 0.2s; }
.input-with-prefix:focus-within { border-color: #E63B6F; box-shadow: 0 0 0 3px rgba(230, 59, 111,0.1); }
.prefix { padding: 10px 14px; background: var(--ocean-deepest); color: var(--text-muted); font-weight: 600; border-right: 1px solid var(--border-color); font-size: 0.85rem; }
.input-with-prefix .form-control { border: none; border-radius: 0; box-shadow: none !important; }
.info-badge { padding: 10px 14px; background: rgba(230, 59, 111,0.06); border-radius: 8px; font-size: 0.85rem; color: var(--text-main); }
.variant-item { border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 20px; overflow: hidden; background: var(--ocean-deepest); }
.variant-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: white; border-bottom: 1px solid var(--border-color); }
.variant-header h4 { font-size: 0.9rem; font-weight: 700; color: var(--text-main); }
.variant-body { padding: 16px; }
.sizes-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.sizes-table th { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-align: left; padding: 0 8px 8px 0; }
.sizes-table td { padding: 0 8px 8px 0; }
.sizes-table td:last-child { padding-right: 0; width: 40px; }
.image-upload-box { border: 2px dashed var(--border-color); border-radius: 10px; background: var(--ocean-deepest); transition: all 0.2s; position: relative; overflow: hidden; }
.image-upload-box:hover { border-color: #E63B6F; background: var(--hover-bg); }
.upload-placeholder { padding: 40px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; text-align: center; color: var(--text-muted); font-size: 0.85rem; font-weight: 600; }
.upload-hint { font-size: 0.7rem; font-weight: 500; opacity: 0.7; }
.file-input-hide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
.preview-container { position: relative; width: 100%; padding-top: 100%; }
.img-preview { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; padding: 10px; }
.remove-img-btn { position: absolute; top: 10px; right: 10px; width: 24px; height: 24px; border-radius: 50%; background: white; border: 1px solid var(--border-color); color: var(--coral); font-weight: bold; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 10; display: flex; align-items: center; justify-content: center; }
.gallery-upload-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
.gallery-item { width: 80px; height: 80px; position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; }
.gallery-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.gallery-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
.gallery-item .remove-img-btn { position: absolute; top: -2px; right: -2px; background: #ef5350; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; z-index: 10; opacity: 0; transition: opacity 0.2s; }
.gallery-item:hover .remove-img-btn { opacity: 1; }
.gallery-add-btn { width: 80px; height: 80px; border: 2px dashed var(--border-color); border-radius: 8px; display: flex; align-items: center; justify-content: center; position: relative; cursor: pointer; background: var(--ocean-deepest); transition: border-color 0.2s, background 0.2s; }
.gallery-add-btn:hover { border-color: #E63B6F; background: rgba(230, 59, 111,0.04); }
.variant-images-grid { display: flex; flex-wrap: wrap; gap: 10px; }
.variant-img-item { width: 72px; height: 72px; position: relative; border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); box-shadow: 0 1px 4px rgba(0,0,0,0.06); transition: transform 0.2s, box-shadow 0.2s; }
.variant-img-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
.variant-img-item img { width: 100%; height: 100%; object-fit: cover; }
.variant-img-item .remove-img-btn { position: absolute; top: 2px; right: 2px; width: 18px; height: 18px; border-radius: 50%; background: rgba(239,83,80,0.9); color: white; border: none; font-size: 12px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; z-index: 5; padding: 0; }
.variant-img-item:hover .remove-img-btn { opacity: 1; }
.variant-img-add { width: 72px; height: 72px; border: 2px dashed var(--border-color); border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; position: relative; cursor: pointer; background: var(--ocean-deepest); transition: border-color 0.2s, background 0.2s; }
.variant-img-add span { font-size: 0.6rem; color: var(--text-muted); font-weight: 600; }
.variant-img-add:hover { border-color: #E63B6F; background: rgba(230, 59, 111,0.04); }
.toggle-switch-wrapper { display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
.toggle-label { display: flex; flex-direction: column; gap: 4px; }
.toggle-label strong { font-size: 0.85rem; color: var(--text-main); }
.toggle-label span { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
.toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.toggle-input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--text-light); transition: 0.3s; border-radius: 24px; }
.toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: 0.3s; border-radius: 50%; }
.toggle-input:checked + .toggle-slider { background-color: #E63B6F; }
.toggle-input:checked + .toggle-slider:before { transform: translateX(20px); }
.mt-2 { margin-top: 8px; }
.mt-4 { margin-top: 16px; }
.mb-0 { margin-bottom: 0 !important; }
.mb-3 { margin-bottom: 16px; }
.error-message { color: #ef5350; font-size: 0.78rem; font-weight: 600; margin-top: 4px; }
.input-error { border-color: #ef5350 !important; box-shadow: 0 0 0 2px rgba(239,83,80,0.15) !important; }
.error-row td { border-bottom: none !important; }
.animate-in { animation: fadeSlideUp 0.4s ease both; }
@keyframes fadeSlideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
.quill-wrapper { display: flex; flex-direction: column; }
.quill-wrapper :deep(.ql-toolbar.ql-snow) { border: 1px solid var(--border-color); border-top-left-radius: 8px; border-top-right-radius: 8px; background: var(--ocean-deepest, #f8fafc); font-family: var(--font-inter); transition: border-color 0.2s; }
.quill-wrapper :deep(.ql-container.ql-snow) { border: 1px solid var(--border-color); border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; border-top: none; font-family: var(--font-inter); font-size: 0.9rem; background: white; transition: border-color 0.2s; }
.quill-wrapper:focus-within :deep(.ql-toolbar.ql-snow) { border-color: #E63B6F; }
.quill-wrapper:focus-within :deep(.ql-container.ql-snow) { border-color: #E63B6F; }
.quill-wrapper :deep(.ql-editor) { color: var(--text-main); }
.editor-short :deep(.ql-editor) { min-height: 100px; max-height: 250px; }
.editor-long :deep(.ql-editor) {
    min-height: 250px;
}

/* Sale Promo Styles */
.sale-promo-section {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px dashed var(--border-color);
}
.sale-toggle {
    margin-bottom: 20px;
    padding: 12px 16px;
    background: rgba(230, 59, 111, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(230, 59, 111, 0.1);
}
.sale-fields-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    position: relative;
}
.sale-preview-badge {
    position: absolute;
    top: -12px;
    left: 20px;
    background: var(--coral);
    color: white;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    box-shadow: 0 2px 4px rgba(239, 83, 80, 0.2);
}
.sale-preview-badge.small {
    position: static;
    display: inline-block;
    margin-top: 6px;
    margin-left: 0;
}
.sale-expand-row td {
    padding: 0 8px 12px 8px;
    border-bottom: 2px dashed var(--border-color) !important;
}
.sale-expand-cell {
    background-color: rgba(230, 59, 111, 0.03);
    border-radius: 0 0 6px 6px;
}
.sale-inline-fields {
    display: flex;
    gap: 16px;
    padding: 12px;
}
.sale-inline-field {
    flex: 1;
}
.sale-inline-field label {
    font-size: 0.75rem;
    color: #E63B6F;
    margin-bottom: 4px;
    display: block;
    font-weight: 600;
}
.action-cell {
    display: flex;
    gap: 6px;
    align-items: center;
}
.btn-sale-toggle {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    background: #f8fafc;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-sale-toggle:hover {
    background: var(--ocean-deepest);
    color: #E63B6F;
}
.btn-sale-toggle.active {
    background: rgba(230, 59, 111, 0.1);
    color: #E63B6F;
    border-color: #E63B6F;
}
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}
.slide-fade-leave-active {
  transition: all 0.2s cubic-bezier(1, 0.5, 0.8, 1);
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}
</style>