<script setup>
import { ref, reactive, onMounted, computed, nextTick } from "vue";
import { useRouter, useRoute } from "vue-router";
import api from "@/axios";
import Swal from 'sweetalert2';
import AdminCategoryFormTree from "@/components/AdminCategoryFormTree.vue";
import Quill from "quill";
import "quill/dist/quill.snow.css";
import { getAppBaseUrl } from '@/utils/url';

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
            ["link", "image"],
        ],
    };

    const uploadFileToEditor = async (file, quillInstance) => {
        if (file.size > 4 * 1024 * 1024) {
            Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: 'Ảnh không được vượt quá 4MB.', icon: 'error', showConfirmButton: false, timer: 3000 });
            return;
        }
        const fd = new FormData();
        fd.append("image", file);
        try {
            const res = await api.post("/products/upload-editor-image", fd, { headers: { "Content-Type": "multipart/form-data" } });
            let range = quillInstance.getSelection(true);
            const url = `${getAppBaseUrl()}${res.data.url}`;
            quillInstance.insertEmbed(range.index, "image", url);
            quillInstance.setSelection(range.index + 1);
        } catch (e) {
            console.error("Upload image error:", e);
            Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: 'Không thể tải ảnh lên. Vui lòng thử lại.', icon: 'error', showConfirmButton: false, timer: 3000 });
        }
    };

    const imageHandler = (quillInstance) => {
        const input = document.createElement("input");
        input.setAttribute("type", "file");
        input.setAttribute("accept", "image/jpeg,image/png,image/jpg,image/gif,image/webp");
        input.click();
        input.onchange = async () => {
            const file = input.files[0];
            if (!file) return;
            await uploadFileToEditor(file, quillInstance);
        };
    };

    const setupDragAndPaste = (quillInstance) => {
        quillInstance.root.addEventListener("paste", async (e) => {
            if (e.clipboardData && e.clipboardData.items && e.clipboardData.items.length) {
                for (let i = 0; i < e.clipboardData.items.length; i++) {
                    const item = e.clipboardData.items[i];
                    if (item.type.startsWith("image/")) {
                        e.preventDefault();
                        const file = item.getAsFile();
                        if (file) await uploadFileToEditor(file, quillInstance);
                    }
                }
            }
        });
        quillInstance.root.addEventListener("drop", async (e) => {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                for (let i = 0; i < e.dataTransfer.files.length; i++) {
                    const file = e.dataTransfer.files[i];
                    if (file.type.startsWith("image/")) {
                        e.preventDefault();
                        await uploadFileToEditor(file, quillInstance);
                    }
                }
            }
        });
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
        quillShort.getModule("toolbar").addHandler("image", () => imageHandler(quillShort));
        setupDragAndPaste(quillShort);
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
        quillLong.getModule("toolbar").addHandler("image", () => imageHandler(quillLong));
        setupDragAndPaste(quillLong);
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
    sku: "",
    weight: "",
    material: "",
    origin: "",
    style: "",
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

const storageUrl = import.meta.env.VITE_API_STORAGE || `${getAppBaseUrl()}/storage`;

// Chuẩn hóa URL ảnh: DB có thể lưu path đã kèm '/storage/' (vd '/storage/products/x.webp'),
// path trần ('products/x.webp'), hoặc URL tuyệt đối. Tránh lặp '/storage//storage/'.
const buildMedia = (path) => {
    if (!path) return null;
    if (/^https?:\/\//i.test(path)) return path;
    const clean = String(path).replace(/^\/+/, '');
    if (clean.startsWith('storage/')) {
        return `${getAppBaseUrl()}/${clean}`;
    }
    return `${storageUrl}/${clean}`;
};

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
        product.sku = p.sku || "";
        product.weight = p.weight || "";
        product.material = p.material || "";
        product.origin = p.origin || "";
        product.style = p.style || "";
        product.thumbnail_url = p.thumbnail_url || "";
        product.imagePreview = p.thumbnail_url ? buildMedia(p.thumbnail_url) : "";

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
                    .map((img) => ({ image_id: img.image_id, url: buildMedia(img.image_url) }));
                delete group._variantIds; // dọn dẹp
            });

            product.variants = Object.values(colorMap);
        }
    } catch (e) {
        console.error("Error fetching product:", e);
        Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: 'Không thể tải thông tin sản phẩm.', icon: 'error', showConfirmButton: false, timer: 3000 });
    } finally {
        isLoading.value = false;
        nextTick(() => initQuill());
    }
};

// ===== Variants =====
const addVariant = () => {
    product.variants.push({ color: "", bulkStock: "", bulkPrice: "", images: [], imagePreviews: [], existingImages: [], sizes: [{ size: "", stock: 0, price: 0, has_sale: false, sale_price: "", sale_starts_at: "", sale_ends_at: "" }] });
};
const removeVariant = (i) => product.variants.splice(i, 1);
const addSize = (vi) => product.variants[vi].sizes.push({ size: "", stock: 0, price: 0, has_sale: false, sale_price: "", sale_starts_at: "", sale_ends_at: "" });
const removeSize = (vi, si) => product.variants[vi].sizes.splice(si, 1);

const formatMoney = (val) => {
    if (!val || isNaN(val)) return '';
    return new Intl.NumberFormat('vi-VN').format(val) + ' ₫';
};

const formatNumberWithCommas = (val) => {
    if (val === null || val === undefined || val === '') return '';
    const clean = String(val).replace(/\D/g, '');
    if (!clean) return '';
    return new Intl.NumberFormat('vi-VN').format(clean);
};

const parseFormattedNumber = (val) => {
    if (val === null || val === undefined || val === '') return '';
    const clean = String(val).replace(/\D/g, '');
    return clean ? Number(clean) : '';
};

const onNumericInput = (e, obj, key) => {
    const raw = e.target.value || '';
    const clean = raw.replace(/\D/g, '');
    const formatted = clean ? new Intl.NumberFormat('vi-VN').format(clean) : '';
    if (e.target.value !== formatted) {
        e.target.value = formatted;
    }
    obj[key] = clean ? Number(clean) : '';
};

const applyBulkToVariant = (vi) => {
    const v = product.variants[vi];
    if (!v) return;
    const bStock = v.bulkStock !== undefined && v.bulkStock !== "" ? Number(v.bulkStock) : null;
    const bPrice = v.bulkPrice !== undefined && v.bulkPrice !== "" ? Number(v.bulkPrice) : null;
    if (bStock === null && bPrice === null) return;
    v.sizes.forEach(s => {
        if (bStock !== null) s.stock = bStock;
        if (bPrice !== null) s.price = bPrice;
    });
};

const addPresetSizes = (vi, presetList = ['S', 'M', 'L', 'XL']) => {
    const v = product.variants[vi];
    if (!v) return;
    const existingSizes = v.sizes.map(s => (s.size || '').trim().toUpperCase());
    presetList.forEach(p => {
        if (!existingSizes.includes(p)) {
            const defaultPrice = v.sizes[0]?.price || v.bulkPrice || 0;
            const defaultStock = v.sizes[0]?.stock || v.bulkStock || 0;
            v.sizes.push({
                size: p,
                stock: defaultStock,
                price: defaultPrice,
                has_sale: false,
                sale_price: "",
                sale_starts_at: "",
                sale_ends_at: ""
            });
        }
    });
    if (v.sizes.length > 1 && v.sizes[0].size === "" && !v.sizes[0].stock && !v.sizes[0].price) {
        v.sizes.shift();
    }
};

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
    if (!validateForm()) {
        nextTick(() => {
            const firstErrorEl = document.querySelector('.field-error');
            if (firstErrorEl) {
                firstErrorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                window.scrollTo({ top: 0, behavior: "smooth" });
            }
        });
        return;
    }
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
    fd.append("sku", product.sku || "");
    fd.append("weight", product.weight || "");
    fd.append("material", product.material || "");
    fd.append("origin", product.origin || "");
    fd.append("style", product.style || "");

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
        Swal.fire({ toast: true, position: 'top-end', title: 'Thành công', text: 'Cập nhật sản phẩm thành công!', icon: 'success', showConfirmButton: false, timer: 3000 });
        
        // Pass query params and edited_id to retain state
        router.push({ 
            path: '/admin/product', 
            query: { ...route.query, edited_id: productId.value } 
        });
    } catch (e) {
        console.error("Error:", e.response?.data || e);
        if (e.response?.data?.errors) errors.value = e.response.data.errors;
        Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: e.response?.data?.message || "Có lỗi xảy ra khi cập nhật.", icon: 'error', showConfirmButton: false, timer: 3000 });
    } finally {
        isSaving.value = false;
    }
};

onMounted(() => { handleFetchCategories(); handleFetchBrands(); fetchProduct(); });
</script>

<template>
    <div class="create-product-page">
        <!-- Modern Skeleton Loading -->
        <div v-if="isLoading" class="edit-product-skeleton">
            <div class="page-header">
                <div class="header-info">
                    <div class="skeleton-box" style="width: 80px; height: 18px; border-radius: 4px; margin-bottom: 8px;"></div>
                    <div class="skeleton-box" style="width: 240px; height: 32px; border-radius: 6px; margin-bottom: 6px;"></div>
                    <div class="skeleton-box" style="width: 180px; height: 16px; border-radius: 4px;"></div>
                </div>
                <div class="header-actions">
                    <div class="skeleton-box" style="width: 90px; height: 42px; border-radius: 8px;"></div>
                    <div class="skeleton-box" style="width: 140px; height: 42px; border-radius: 8px;"></div>
                </div>
            </div>

            <div class="form-container">
                <!-- Left Column Skeleton -->
                <div class="form-column main-col">
                    <div class="ocean-card form-card">
                        <div class="skeleton-box" style="width: 160px; height: 22px; border-radius: 6px; margin-bottom: 20px;"></div>
                        <div class="skeleton-box" style="width: 120px; height: 14px; border-radius: 4px; margin-bottom: 8px;"></div>
                        <div class="skeleton-box" style="width: 100%; height: 44px; border-radius: 8px; margin-bottom: 20px;"></div>
                        <div class="skeleton-box" style="width: 100px; height: 14px; border-radius: 4px; margin-bottom: 8px;"></div>
                        <div class="skeleton-box" style="width: 100%; height: 100px; border-radius: 8px; margin-bottom: 20px;"></div>
                        <div class="skeleton-box" style="width: 120px; height: 14px; border-radius: 4px; margin-bottom: 8px;"></div>
                        <div class="skeleton-box" style="width: 100%; height: 160px; border-radius: 8px;"></div>
                    </div>
                    <div class="ocean-card form-card" style="margin-top: 24px;">
                        <div class="skeleton-box" style="width: 180px; height: 22px; border-radius: 6px; margin-bottom: 20px;"></div>
                        <div class="row g-3">
                            <div class="col-md-6"><div class="skeleton-box" style="width: 100%; height: 44px; border-radius: 8px;"></div></div>
                            <div class="col-md-6"><div class="skeleton-box" style="width: 100%; height: 44px; border-radius: 8px;"></div></div>
                        </div>
                    </div>
                </div>

                <!-- Right Column Skeleton -->
                <div class="form-column side-col">
                    <div class="ocean-card form-card">
                        <div class="skeleton-box" style="width: 140px; height: 22px; border-radius: 6px; margin-bottom: 20px;"></div>
                        <div class="skeleton-box" style="width: 100%; height: 200px; border-radius: 12px; margin-bottom: 16px;"></div>
                        <div class="skeleton-box" style="width: 100%; height: 40px; border-radius: 8px;"></div>
                    </div>
                    <div class="ocean-card form-card" style="margin-top: 24px;">
                        <div class="skeleton-box" style="width: 150px; height: 22px; border-radius: 6px; margin-bottom: 20px;"></div>
                        <div class="skeleton-box" style="width: 100%; height: 44px; border-radius: 8px; margin-bottom: 16px;"></div>
                        <div class="skeleton-box" style="width: 100%; height: 44px; border-radius: 8px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <form v-else @submit.prevent="handleSubmit" novalidate enctype="multipart/form-data">
            <div class="page-header animate-in">
                <div class="header-info">
                    <div class="back-link">
                        <router-link :to="{ path: '/admin/product', query: route.query }">
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
                    <router-link :to="{ path: '/admin/product', query: route.query }" class="btn-outline">Hủy bỏ</router-link>
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

                    <!-- Tech Specs -->
                    <div class="ocean-card form-card animate-in" style="animation-delay: 0.15s">
                        <h3 class="card-title">Thông Số Kỹ Thuật</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Mã Sản Phẩm (SKU) - Tự động tạo</label>
                                    <input type="text" v-model="product.sku" class="form-control" disabled style="background-color: #e9ecef; cursor: not-allowed;" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Trọng Lượng (gram)</label>
                                    <input type="number" v-model="product.weight" class="form-control" placeholder="Ví dụ: 300" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Chất Liệu</label>
                                    <input type="text" v-model="product.material" class="form-control" placeholder="Ví dụ: Cotton" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Xuất Xứ</label>
                                    <input type="text" v-model="product.origin" class="form-control" placeholder="Ví dụ: Việt Nam" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Kiểu Dáng</label>
                                    <input type="text" v-model="product.style" class="form-control" placeholder="Ví dụ: Slim Fit" />
                                </div>
                            </div>
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
                                    <input type="text" inputmode="numeric" :value="formatNumberWithCommas(product.price)" @input="onNumericInput($event, product, 'price')" class="form-control" :class="{'is-invalid': errors.price}" placeholder="0" />
                                </div>
                                <span v-if="errors.price" class="field-error">{{ errors.price }}</span>
                            </div>
                            <div class="form-group">
                                <label>Giá Gốc Trước Giảm</label>
                                <div class="input-with-prefix">
                                    <span class="prefix">₫</span>
                                    <input type="text" inputmode="numeric" :value="formatNumberWithCommas(product.compare_at_price)" @input="onNumericInput($event, product, 'compare_at_price')" class="form-control" placeholder="0" />
                                </div>
                            </div>
                            <div class="form-group" style="grid-column: span 2">
                                <label>Số Lượng Kho <span class="required">*</span></label>
                                <input type="text" inputmode="numeric" :value="formatNumberWithCommas(product.stock)" @input="onNumericInput($event, product, 'stock')" class="form-control" :class="{'is-invalid': errors.stock}" placeholder="0" />
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
                                            <input type="text" inputmode="numeric" :value="formatNumberWithCommas(product.sale_price)" @input="onNumericInput($event, product, 'sale_price')" class="form-control" placeholder="0" />
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
                                    <div class="sizes-header-flex">
                                        <label class="sizes-title-label">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                            </svg>
                                            Kích Cỡ / Số Lượng & Giá Bán
                                        </label>
                                        <span class="sizes-count-badge">{{ variant.sizes.length }} kích cỡ</span>
                                    </div>

                                    <!-- Bulk Quick Fill Bar -->
                                    <div class="variant-bulk-bar">
                                        <div class="bulk-title">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                            <span>Áp dụng nhanh cho tất cả size:</span>
                                        </div>
                                        <div class="bulk-fields">
                                            <div class="bulk-input-group">
                                                <span class="bulk-label">Kho</span>
                                                <input type="text" inputmode="numeric" :value="formatNumberWithCommas(variant.bulkStock)" @input="onNumericInput($event, variant, 'bulkStock')" placeholder="Số lượng" class="form-control input-xs" />
                                            </div>
                                            <div class="bulk-input-group">
                                                <span class="bulk-label">Giá ₫</span>
                                                <input type="text" inputmode="numeric" :value="formatNumberWithCommas(variant.bulkPrice)" @input="onNumericInput($event, variant, 'bulkPrice')" placeholder="Giá bán" class="form-control input-xs" />
                                            </div>
                                            <button type="button" class="btn-bulk-apply" @click.prevent="applyBulkToVariant(vIndex)" title="Điền nhanh Kho và Giá cho tất cả size">
                                                Áp Dụng
                                            </button>
                                        </div>
                                    </div>

                                    <table class="sizes-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 22%">Size (Kích cỡ)</th>
                                                <th style="width: 20%">Kho (Tồn)</th>
                                                <th style="width: 32%">Giá Bán (₫)</th>
                                                <th style="width: 16%; text-align: center">Khuyến Mãi</th>
                                                <th style="width: 10%; text-align: center">Xóa</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template v-for="(s, sIndex) in variant.sizes" :key="sIndex">
                                                <tr class="size-main-row" :class="{'has-sale-active': s.has_sale}">
                                                    <td>
                                                        <input type="text" v-model="s.size" class="form-control input-sm font-weight-600" :class="{ 'is-invalid': errors.variants?.[vIndex]?.sizes?.[sIndex]?.size, 'input-error': errors.variants?.[vIndex]?.sizes?.[sIndex]?.size }" placeholder="Ví dụ: L, M..." />
                                                        <span v-if="errors.variants?.[vIndex]?.sizes?.[sIndex]?.size" class="field-error">{{ errors.variants[vIndex].sizes[sIndex].size }}</span>
                                                    </td>
                                                    <td>
                                                        <div class="stock-input-wrapper">
                                                            <input type="text" inputmode="numeric" :value="formatNumberWithCommas(s.stock)" @input="onNumericInput($event, s, 'stock')" class="form-control input-sm" :class="{ 'is-invalid': errors.variants?.[vIndex]?.sizes?.[sIndex]?.stock, 'input-error': errors.variants?.[vIndex]?.sizes?.[sIndex]?.stock }" placeholder="0" />
                                                        </div>
                                                        <span v-if="errors.variants?.[vIndex]?.sizes?.[sIndex]?.stock" class="field-error">{{ errors.variants[vIndex].sizes[sIndex].stock }}</span>
                                                    </td>
                                                    <td>
                                                        <div class="price-input-wrapper">
                                                            <input type="text" inputmode="numeric" :value="formatNumberWithCommas(s.price)" @input="onNumericInput($event, s, 'price')" class="form-control input-sm font-weight-600" :class="{ 'is-invalid': errors.variants?.[vIndex]?.sizes?.[sIndex]?.price, 'input-error': errors.variants?.[vIndex]?.sizes?.[sIndex]?.price }" placeholder="0" />
                                                        </div>
                                                        <span v-if="errors.variants?.[vIndex]?.sizes?.[sIndex]?.price" class="field-error">{{ errors.variants[vIndex].sizes[sIndex].price }}</span>
                                                    </td>
                                                    <td class="action-cell text-center">
                                                        <button class="btn-sale-toggle" :class="{ active: s.has_sale }" type="button" :title="s.has_sale ? 'Đang mở Khuyến mãi (bấm để tắt)' : 'Bật khuyến mãi cho size này'" @click.prevent="s.has_sale = !s.has_sale">
                                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
                                                                <line x1="7" y1="7" x2="7.01" y2="7"/>
                                                            </svg>
                                                            <span class="sale-btn-text">{{ s.has_sale ? 'Đang KM' : '+ KM' }}</span>
                                                        </button>
                                                    </td>
                                                    <td class="action-cell text-center">
                                                        <button class="btn-icon-danger square" type="button" title="Xóa size này" @click.prevent="removeSize(vIndex, sIndex)">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <!-- Redesigned Modern Sale Compact Box -->
                                                <tr v-if="s.has_sale" class="sale-expand-row">
                                                    <td colspan="5" class="sale-expand-cell">
                                                        <div class="sale-compact-card">
                                                            <div class="sale-compact-header">
                                                                <div class="sale-title-tag">
                                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                                                                    <span>Thiết Lập Khuyến Mãi Cho Size {{ s.size || '#' + (sIndex + 1) }}</span>
                                                                </div>
                                                                <span v-if="s.sale_price && s.price && Number(s.price) > Number(s.sale_price)" class="sale-discount-pill">
                                                                    Giảm -{{ Math.round((s.price - s.sale_price) / s.price * 100) }}%
                                                                </span>
                                                            </div>
                                                            <div class="sale-inline-fields">
                                                                <div class="sale-inline-field">
                                                                    <label>Giá Khuyến Mãi (₫)</label>
                                                                    <div class="price-input-wrapper">
                                                                        <input type="text" inputmode="numeric" :value="formatNumberWithCommas(s.sale_price)" @input="onNumericInput($event, s, 'sale_price')" class="form-control input-sm font-weight-600" :class="{ 'is-invalid': errors.variants?.[vIndex]?.sizes?.[sIndex]?.sale_price, 'input-error': errors.variants?.[vIndex]?.sizes?.[sIndex]?.sale_price }" placeholder="0" />
                                                                    </div>
                                                                    <span v-if="errors.variants?.[vIndex]?.sizes?.[sIndex]?.sale_price" class="field-error" style="margin-top:2px">{{ errors.variants[vIndex].sizes[sIndex].sale_price }}</span>
                                                                </div>
                                                                <div class="sale-inline-field">
                                                                    <label>Thời Gian Bắt Đầu</label>
                                                                    <input type="datetime-local" v-model="s.sale_starts_at" class="form-control input-sm date-input" />
                                                                </div>
                                                                <div class="sale-inline-field">
                                                                    <label>Thời Gian Kết Thúc</label>
                                                                    <input type="datetime-local" v-model="s.sale_ends_at" class="form-control input-sm date-input" />
                                                                </div>
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

                                    <!-- Modern Footer Actions (Add size + Quick Presets) -->
                                    <div class="size-footer-actions">
                                        <button class="btn-add-size" type="button" @click.prevent="addSize(vIndex)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                            Thêm Kích Cỡ
                                        </button>

                                        <div class="size-presets">
                                            <span class="preset-label">Tạo nhanh:</span>
                                            <button type="button" class="btn-preset-chip" @click.prevent="addPresetSizes(vIndex, ['S', 'M', 'L', 'XL'])">
                                                + S, M, L, XL
                                            </button>
                                            <button type="button" class="btn-preset-chip" @click.prevent="addPresetSizes(vIndex, ['38', '39', '40', '41', '42'])">
                                                + Size 38-42
                                            </button>
                                        </div>
                                    </div>

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
                                    <img :src="buildMedia(img.image_url)" alt="Gallery" />
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
.spinner { width: 30px; height: 30px; border: 3px solid var(--border-color); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px; }
@keyframes spin { to { transform: rotate(360deg); } }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.back-link { margin-bottom: 8px; }
.back-link a { display: inline-flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
.back-link a:hover { color: var(--primary); }
.page-title { font-size: 1.5rem; font-weight: 800; color: var(--text-main); line-height: 1.2; }
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; margin-top: 4px; }
.header-actions { display: flex; gap: 12px; }
.btn-primary { display: flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; border: none; background: var(--primary); color: white; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 4px 10px rgba(230, 59, 111,0.2); transition: all 0.2s; text-decoration: none; }
.btn-primary:hover:not(:disabled) { background: var(--ocean-bright); transform: translateY(-2px); box-shadow: 0 6px 14px rgba(230, 59, 111,0.3); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-outline { padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-main); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; }
.btn-outline:hover { background: var(--ocean-deepest); border-color: var(--text-light); }
.btn-outline-small { display: flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; color: var(--primary); font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-outline-small:hover { background: rgba(230, 59, 111,0.05); border-color: var(--primary); }
.btn-text-link { background: none; border: none; color: var(--primary); font-size: 0.8rem; font-weight: 600; cursor: pointer; padding: 0; }
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
.form-control { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-main); font-family: var(--font-inter); font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box; }
.form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(230, 59, 111,0.1); }
.form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; }
.input-sm { padding: 8px 10px; font-size: 0.8rem; }
.price-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.input-with-prefix { display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: var(--card-bg); transition: all 0.2s; }
.input-with-prefix:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(230, 59, 111,0.1); }
.prefix { padding: 10px 14px; background: var(--ocean-deepest); color: var(--text-muted); font-weight: 600; border-right: 1px solid var(--border-color); font-size: 0.85rem; }
.input-with-prefix .form-control { border: none; border-radius: 0; box-shadow: none !important; }
.info-badge { padding: 10px 14px; background: rgba(230, 59, 111,0.06); border-radius: 8px; font-size: 0.85rem; color: var(--text-main); }
.variant-item { border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 20px; overflow: hidden; background: var(--ocean-deepest); }
.variant-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--card-bg); border-bottom: 1px solid var(--border-color); }
.variant-header h4 { font-size: 0.9rem; font-weight: 700; color: var(--text-main); }
.variant-body { padding: 16px; }
.sizes-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.sizes-table th { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-align: left; padding: 0 8px 8px 0; }
.sizes-table td { padding: 0 8px 8px 0; }
.sizes-table td:last-child { padding-right: 0; width: 40px; }
.image-upload-box { border: 2px dashed var(--border-color); border-radius: 10px; background: var(--ocean-deepest); transition: all 0.2s; position: relative; overflow: hidden; }
.image-upload-box:hover { border-color: var(--primary); background: var(--hover-bg); }
.upload-placeholder { padding: 40px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; text-align: center; color: var(--text-muted); font-size: 0.85rem; font-weight: 600; }
.upload-hint { font-size: 0.7rem; font-weight: 500; opacity: 0.7; }
.file-input-hide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
.preview-container { position: relative; width: 100%; padding-top: 100%; }
.img-preview { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; padding: 10px; }
.remove-img-btn { position: absolute; top: 10px; right: 10px; width: 24px; height: 24px; border-radius: 50%; background: var(--card-bg); border: 1px solid var(--border-color); color: var(--coral); font-weight: bold; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 10; display: flex; align-items: center; justify-content: center; }
.gallery-upload-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
.gallery-item { width: 80px; height: 80px; position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; }
.gallery-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.gallery-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
.gallery-item .remove-img-btn { position: absolute; top: -2px; right: -2px; background: #ef5350; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; z-index: 10; opacity: 0; transition: opacity 0.2s; }
.gallery-item:hover .remove-img-btn { opacity: 1; }
.gallery-add-btn { width: 80px; height: 80px; border: 2px dashed var(--border-color); border-radius: 8px; display: flex; align-items: center; justify-content: center; position: relative; cursor: pointer; background: var(--ocean-deepest); transition: border-color 0.2s, background 0.2s; }
.gallery-add-btn:hover { border-color: var(--primary); background: rgba(230, 59, 111,0.04); }
.variant-images-grid { display: flex; flex-wrap: wrap; gap: 10px; }
.variant-img-item { width: 72px; height: 72px; position: relative; border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); box-shadow: 0 1px 4px rgba(0,0,0,0.06); transition: transform 0.2s, box-shadow 0.2s; }
.variant-img-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
.variant-img-item img { width: 100%; height: 100%; object-fit: cover; }
.variant-img-item .remove-img-btn { position: absolute; top: 2px; right: 2px; width: 18px; height: 18px; border-radius: 50%; background: rgba(239,83,80,0.9); color: white; border: none; font-size: 12px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; z-index: 5; padding: 0; }
.variant-img-item:hover .remove-img-btn { opacity: 1; }
.variant-img-add { width: 72px; height: 72px; border: 2px dashed var(--border-color); border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; position: relative; cursor: pointer; background: var(--ocean-deepest); transition: border-color 0.2s, background 0.2s; }
.variant-img-add span { font-size: 0.6rem; color: var(--text-muted); font-weight: 600; }
.variant-img-add:hover { border-color: var(--primary); background: rgba(230, 59, 111,0.04); }
.toggle-switch-wrapper { display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
.toggle-label { display: flex; flex-direction: column; gap: 4px; }
.toggle-label strong { font-size: 0.85rem; color: var(--text-main); }
.toggle-label span { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
.toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.toggle-input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--text-light); transition: 0.3s; border-radius: 24px; }
.toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: var(--card-bg); transition: 0.3s; border-radius: 50%; }
.toggle-input:checked + .toggle-slider { background-color: var(--primary); }
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
.quill-wrapper :deep(.ql-container.ql-snow) { border: 1px solid var(--border-color); border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; border-top: none; font-family: var(--font-inter); font-size: 0.9rem; background: var(--card-bg); transition: border-color 0.2s; }
.quill-wrapper:focus-within :deep(.ql-toolbar.ql-snow) { border-color: var(--primary); }
.quill-wrapper:focus-within :deep(.ql-container.ql-snow) { border-color: var(--primary); }
.quill-wrapper :deep(.ql-editor) { color: var(--text-main); }
.editor-short :deep(.ql-editor) { min-height: 100px; max-height: 250px; }
.editor-long :deep(.ql-editor) {
    min-height: 250px;
}
.quill-wrapper :deep(.ql-editor img) {
    max-width: 30%;
    height: auto;
    border-radius: 6px;
    margin: 8px auto;
    display: block;
}

/* ── Enhanced Sizes Section & Bulk Bar Styles ── */
.sizes-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.sizes-title-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text-main);
}
.sizes-count-badge {
    background: rgba(230, 59, 111, 0.08);
    color: var(--primary);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
}

/* Bulk Quick Fill Bar */
.variant-bulk-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    background: linear-gradient(135deg, rgba(230, 59, 111, 0.04) 0%, rgba(2, 132, 199, 0.04) 100%);
    border: 1px dashed rgba(230, 59, 111, 0.25);
    border-radius: 10px;
    margin-bottom: 14px;
}
.bulk-title {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--primary);
    white-space: nowrap;
}
.bulk-fields {
    display: flex;
    align-items: center;
    gap: 10px;
}
.bulk-input-group {
    display: flex;
    align-items: center;
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    overflow: hidden;
}
.bulk-label {
    padding: 4px 8px;
    background: var(--ocean-deepest, #f8fafc);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-muted);
    border-right: 1px solid var(--border-color);
    white-space: nowrap;
}
.input-xs {
    padding: 4px 8px !important;
    font-size: 0.78rem !important;
    width: 90px !important;
    border: none !important;
    box-shadow: none !important;
}
.btn-bulk-apply {
    padding: 6px 14px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.btn-bulk-apply:hover {
    background: var(--ocean-bright, #d81b60);
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(230, 59, 111, 0.25);
}

/* Sizes Table Enhancements */
.sizes-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 4px;
}
.sizes-table th {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    padding: 8px 10px;
    background: var(--ocean-deepest, #f8fafc);
    border-top: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
}
.sizes-table th:first-child { border-top-left-radius: 8px; border-left: 1px solid var(--border-color); }
.sizes-table th:last-child { border-top-right-radius: 8px; border-right: 1px solid var(--border-color); }

.size-main-row td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--border-color);
    background: var(--card-bg, #fff);
    vertical-align: middle !important;
    transition: background 0.2s;
}
.size-main-row.has-sale-active td {
    background: rgba(230, 59, 111, 0.02);
}

.price-input-wrapper {
    position: relative;
    display: flex;
    flex-direction: column;
}

.font-weight-600 { font-weight: 600 !important; }

.action-cell {
    text-align: center !important;
    vertical-align: middle !important;
    white-space: nowrap;
}
.btn-sale-toggle {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 10px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-muted);
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-sale-toggle:hover {
    background: rgba(230, 59, 111, 0.08);
    color: var(--primary);
    border-color: rgba(230, 59, 111, 0.3);
}
.btn-sale-toggle.active {
    background: #e11d48;
    color: white;
    border-color: #e11d48;
    box-shadow: 0 2px 6px rgba(225, 29, 72, 0.3);
}

/* Redesigned Compact Sale Box */
.sale-expand-row td {
    padding: 0 10px 12px 10px !important;
    border-bottom: 1px dashed rgba(230, 59, 111, 0.25) !important;
    background: var(--card-bg, #fff);
}
.sale-compact-card {
    background: linear-gradient(135deg, rgba(230, 59, 111, 0.03) 0%, rgba(230, 59, 111, 0.06) 100%);
    border: 1px solid rgba(230, 59, 111, 0.18);
    border-left: 3.5px solid #e11d48;
    border-radius: 8px;
    padding: 10px 14px;
    margin-top: 4px;
}
.sale-compact-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 6px;
    border-bottom: 1px dashed rgba(230, 59, 111, 0.15);
}
.sale-title-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    font-weight: 800;
    color: #e11d48;
}
.sale-discount-pill {
    background: #e11d48;
    color: #fff;
    font-size: 0.7rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 12px;
    letter-spacing: 0.02em;
}
.sale-inline-fields {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;
}
.sale-inline-field label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    margin-bottom: 4px;
    display: block;
}
.date-input {
    font-size: 0.75rem !important;
    padding: 6px 8px !important;
}

/* Footer Actions & Presets */
.size-footer-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 14px;
    padding-top: 10px;
    border-top: 1px dashed var(--border-color);
}
.btn-add-size {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    background: rgba(230, 59, 111, 0.08);
    color: var(--primary);
    border: 1px solid rgba(230, 59, 111, 0.2);
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-add-size:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(230, 59, 111, 0.25);
}
.size-presets {
    display: flex;
    align-items: center;
    gap: 6px;
}
.preset-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-muted);
}
.btn-preset-chip {
    padding: 4px 10px;
    background: var(--ocean-deepest, #f8fafc);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-main);
    cursor: pointer;
    transition: all 0.2s;
}
.btn-preset-chip:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(230, 59, 111, 0.06);
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


/* ===== Modern Skeleton Loading Styles ===== */
.edit-product-skeleton {
  width: 100%;
  pointer-events: none;
}

.skeleton-box {
  background: var(--surface-container, #e2e8f0);
  position: relative;
  overflow: hidden;
}

.skeleton-box::after {
  content: '';
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  transform: translateX(-100%);
  background-image: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0,
    rgba(255, 255, 255, 0.4) 30%,
    rgba(255, 255, 255, 0.75) 60%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
  100% {
    transform: translateX(100%);
  }
}
</style>
