<script setup>
import { ref, reactive, computed, onMounted, nextTick } from "vue";
import { useRouter } from "vue-router";
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
        if (product.short_description) {
            quillShort.root.innerHTML = product.short_description;
        }
        quillShort.on("text-change", () => {
            product.short_description =
                quillShort.root.innerHTML === "<p><br></p>"
                    ? ""
                    : quillShort.root.innerHTML;
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
        if (product.description) {
            quillLong.root.innerHTML = product.description;
        }
        quillLong.on("text-change", () => {
            product.description =
                quillLong.root.innerHTML === "<p><br></p>"
                    ? ""
                    : quillLong.root.innerHTML;
        });
        quillLong.getModule("toolbar").addHandler("image", () => imageHandler(quillLong));
        setupDragAndPaste(quillLong);
    }
};

const router = useRouter();

const categories = ref([]);
const brands = ref([]);
const errors = ref({});
const IMAGE_MAX_SIZE_MB = 4;
const IMAGE_MAX_SIZE_BYTES = IMAGE_MAX_SIZE_MB * 1024 * 1024;
const ALLOWED_IMAGE_TYPES = [
    "image/jpeg",
    "image/png",
    "image/gif",
    "image/svg+xml",
    "image/webp",
];
const ALLOWED_IMAGE_EXTENSIONS = [".jpg", ".jpeg", ".png", ".gif", ".svg", ".webp"];

const getImageValidationError = (file) => {
    const fileName = file.name.toLowerCase();
    const hasAllowedType = ALLOWED_IMAGE_TYPES.includes(file.type);
    const hasAllowedExtension = ALLOWED_IMAGE_EXTENSIONS.some((ext) =>
        fileName.endsWith(ext),
    );

    if (!hasAllowedType && !hasAllowedExtension) {
        return `Ảnh "${file.name}" không đúng định dạng. Chỉ hỗ trợ JPG, JPEG, PNG, GIF, SVG, WEBP.`;
    }

    if (file.size > IMAGE_MAX_SIZE_BYTES) {
        return `Ảnh "${file.name}" vượt quá ${IMAGE_MAX_SIZE_MB}MB. Vui lòng nén ảnh hoặc chọn ảnh nhỏ hơn.`;
    }

    return "";
};

const normalizeServerErrors = (serverErrors = {}) => {
    const normalized = {};

    Object.entries(serverErrors).forEach(([key, value]) => {
        normalized[key] = Array.isArray(value) ? value[0] : value;
    });

    if (normalized.thumbnail && !normalized.thumbnail_url) {
        normalized.thumbnail_url = normalized.thumbnail;
    }

    const galleryMessages = Object.entries(normalized)
        .filter(([key]) => key === "gallery" || key.startsWith("gallery."))
        .map(([, value]) => value);

    if (galleryMessages.length) {
        normalized.gallery = galleryMessages.join(" ");
    }

    if (typeof normalized.variant_images === "string") {
        normalized.variant_images_global = normalized.variant_images;
        delete normalized.variant_images;
    }

    const variantImageMessages = Object.entries(normalized)
        .filter(([key]) => key.startsWith("variant_images."))
        .reduce((messages, [key, value]) => {
            const variantIndex = Number(key.split(".")[1]);
            if (!Number.isNaN(variantIndex)) {
                messages[variantIndex] = messages[variantIndex]
                    ? `${messages[variantIndex]} ${value}`
                    : value;
            }
            return messages;
        }, []);

    if (variantImageMessages.length) {
        normalized.variant_images = variantImageMessages;
    }

    return normalized;
};

const firstErrorMessage = (serverErrors = {}) => {
    const first = Object.values(serverErrors)[0];
    return Array.isArray(first) ? first[0] : first;
};

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
    product_type: "simple", // simple | variant
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
    gallery_files: [],
    galleryPreviews: [],
    variants: [
        {
            color: "",
            images: [],
            imagePreviews: [],
            sizes: [{ size: "", stock: 0, price: 0, has_sale: false, sale_price: "", sale_starts_at: "", sale_ends_at: "" }],
        },
    ],
});

// Tính % giảm giá preview (simple)
const simpleSalePercent = computed(() => {
    if (!product.has_sale || !product.sale_price || !product.price) return 0;
    return Math.round((product.price - product.sale_price) / product.price * 100);
});

const addVariant = () => {
    product.variants.push({
        color: "",
        images: [],
        imagePreviews: [],
        sizes: [{ size: "", stock: 0, price: 0, has_sale: false, sale_price: "", sale_starts_at: "", sale_ends_at: "" }],
    });
};

// xoá biến thể
const removeVariant = (index) => {
    product.variants.splice(index, 1);
};

// thêm kích cỡ
const addSize = (variantIndex) => {
    product.variants[variantIndex].sizes.push({ size: "", stock: 0, price: 0, has_sale: false, sale_price: "", sale_starts_at: "", sale_ends_at: "" });
};

// xoá kích cỡ
const removeSize = (variantIndex, sizeIndex) => {
    product.variants[variantIndex].sizes.splice(sizeIndex, 1);
};

const handleThumbnailChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        const fileError = getImageValidationError(file);
        if (fileError) {
            errors.value.thumbnail_url = fileError;
            event.target.value = "";
            return;
        }

        product.thumbnail_url = file;
        product.imagePreview = URL.createObjectURL(file);
        delete errors.value.thumbnail_url;
        delete errors.value.thumbnail;
    }
};

const removeThumbnailImage = () => {
    product.thumbnail_url = "";
    product.imagePreview = "";
    delete errors.value.thumbnail_url;
    delete errors.value.thumbnail;
};

// xử lý ảnh phụ (gallery)
const handleGalleryChange = (event) => {
    const files = Array.from(event.target.files);
    const fileErrors = [];

    files.forEach((file) => {
        const fileError = getImageValidationError(file);
        if (fileError) {
            fileErrors.push(fileError);
            return;
        }

        product.gallery_files.push(file);
        product.galleryPreviews.push(URL.createObjectURL(file));
    });

    if (fileErrors.length) {
        errors.value.gallery = fileErrors.join(" ");
    } else {
        delete errors.value.gallery;
    }

    event.target.value = "";
};

// xóa ảnh phụ
const removeGalleryImage = (index) => {
    product.gallery_files.splice(index, 1);
    product.galleryPreviews.splice(index, 1);
};

// xóa ảnh biến thể
const removeVariantImage = (variantIndex, imageIndex) => {
    product.variants[variantIndex].images.splice(imageIndex, 1);
    product.variants[variantIndex].imagePreviews.splice(imageIndex, 1);
};

// xử lý ảnh biến thể — hỗ trợ nhiều ảnh
const handleVariantImageChange = (event, index) => {
    const files = Array.from(event.target.files);
    const fileErrors = [];

    if (files.length) {
        files.forEach((file) => {
            const fileError = getImageValidationError(file);
            if (fileError) {
                fileErrors.push(fileError);
                return;
            }

            product.variants[index].images.push(file);
            product.variants[index].imagePreviews.push(
                URL.createObjectURL(file),
            );
        });
    }

    if (fileErrors.length) {
        if (!errors.value.variant_images) errors.value.variant_images = [];
        errors.value.variant_images[index] = fileErrors.join(" ");
    } else if (errors.value.variant_images) {
        errors.value.variant_images[index] = "";
    }

    event.target.value = "";
};

const handleFetchCategories = async () => {
    try {
        const res = await api.get("/categories");
        categories.value = res.data.data;
    } catch (err) {
        console.error("Error fetching categories:", err);
    }
};

const handleFetchBrands = async () => {
    try {
        const res = await api.get("/brands");
        brands.value = res.data;
    } catch (err) {
        console.error("Error fetching brands:", err);
    }
};

const validateForm = () => {
    errors.value = {};
    let isValid = true;

    if (!product.name) {
        errors.value.name = "Tên sản phẩm là bắt buộc";
        isValid = false;
    }
    if (!product.category_id) {
        errors.value.category_id = "Danh mục là bắt buộc";
        isValid = false;
    }

    if (!product.thumbnail_url) {
        errors.value.thumbnail_url = "Ảnh đại diện là bắt buộc";
        isValid = false;
    }

    if (product.product_type === "simple") {
        if (!product.price) {
            errors.value.price = "Giá bán là bắt buộc";
            isValid = false;
        } else if (Number(product.price) < 100000) {
            errors.value.price = "Giá bán tối thiểu là 100.000đ";
            isValid = false;
        }
        if (product.stock === "" || product.stock === null) {
            errors.value.stock = "Số lượng kho là bắt buộc";
            isValid = false;
        }
        if (product.has_sale) {
            if (!product.sale_price) {
                errors.value.sale_price = "Giá khuyến mãi là bắt buộc";
                isValid = false;
            } else if (Number(product.sale_price) < 1) {
                errors.value.sale_price = "Giá khuyến mãi phải lớn hơn 0đ";
                isValid = false;
            }
        }
    } else {
        if (!product.variants || product.variants.length === 0) {
            errors.value.variants_global = "Cần ít nhất một biến thể";
            isValid = false;
        } else {
            const variantCombinations = new Set();
            const colorSet = new Set();
            errors.value.variants = [];

            product.variants.forEach((variant, vIndex) => {
                const vErrors = {};
                if (!variant.color) {
                    vErrors.color = "Màu sắc là bắt buộc";
                    isValid = false;
                } else {
                    const colorKey = variant.color.trim().toLowerCase();
                    if (colorSet.has(colorKey)) {
                        vErrors.color = `Màu "${variant.color}" đã được sử dụng ở biến thể khác`;
                        isValid = false;
                    } else {
                        colorSet.add(colorKey);
                    }
                }

                if (!variant.sizes || variant.sizes.length === 0) {
                    vErrors.sizes_global = "Cần ít nhất một kích cỡ";
                    isValid = false;
                } else {
                    vErrors.sizes = [];
                    const sizeSetInVariant = new Set();
                    variant.sizes.forEach((size, sIndex) => {
                        const sErrors = {};
                        // Size là tùy chọn, chỉ check trùng nếu có nhập
                        if (size.size) {
                            const sizeKey = size.size.trim().toLowerCase();
                            if (sizeSetInVariant.has(sizeKey)) {
                                sErrors.size = `Size "${size.size}" bị trùng trong biến thể này`;
                                isValid = false;
                            } else {
                                sizeSetInVariant.add(sizeKey);
                            }
                        }
                        if (!size.price) {
                            sErrors.price = "Giá là bắt buộc";
                            isValid = false;
                        } else if (Number(size.price) < 100000) {
                            sErrors.price = "Giá tối thiểu 100.000đ";
                            isValid = false;
                        }
                        if (size.stock === "" || size.stock === null) {
                            sErrors.stock = "Kho là bắt buộc";
                            isValid = false;
                        }
                        if (size.has_sale) {
                            if (!size.sale_price) {
                                sErrors.sale_price = "Bắt buộc";
                                isValid = false;
                            } else if (Number(size.sale_price) < 1) {
                                sErrors.sale_price = "Phải > 0đ";
                                isValid = false;
                            }
                        }

                        // Check for duplicates (Color + Size combination)
                        const combo = `${(variant.color || "").trim().toLowerCase()}-${(size.size || "").trim().toLowerCase()}`;
                        if (
                            variant.color &&
                            size.size &&
                            variantCombinations.has(combo)
                        ) {
                            sErrors.duplicate =
                                "Biến thể (Màu + Size) đã tồn tại";
                            isValid = false;
                        } else if (variant.color && size.size) {
                            variantCombinations.add(combo);
                        }
                        vErrors.sizes[sIndex] = sErrors;
                    });
                }
                errors.value.variants[vIndex] = vErrors;
            });
        }
    }
    return isValid;
};

const handleSubmit = async () => {
    if (!validateForm()) {
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }

    const formData = new FormData();
    formData.append("name", product.name);
    formData.append("category_id", product.category_id);
    formData.append("brand_id", product.brand_id || "");
    formData.append("seller_id", product.seller_id || "");
    formData.append("short_description", product.short_description || "");
    formData.append("description", product.description || "");
    formData.append("product_type", product.product_type);
    formData.append("status", product.status);
    formData.append("is_featured", product.is_featured ? 1 : 0);
    formData.append("sku", product.sku || "");
    formData.append("weight", product.weight || "");
    formData.append("material", product.material || "");
    formData.append("origin", product.origin || "");
    formData.append("style", product.style || "");

    if (product.thumbnail_url) {
        formData.append("thumbnail", product.thumbnail_url);
    }

    product.gallery_files.forEach((file, index) => {
        formData.append(`gallery[${index}]`, file);
    });

    if (product.product_type === "simple") {
        formData.append("price", product.price);
        formData.append("compare_at_price", product.compare_at_price || "");
        formData.append("stock", product.stock);
        if (product.has_sale && product.sale_price) {
            formData.append("sale_price", product.sale_price);
            formData.append("sale_starts_at", product.sale_starts_at || "");
            formData.append("sale_ends_at", product.sale_ends_at || "");
        }
    } else {
        formData.append(
            "variants",
            JSON.stringify(
                product.variants.map((variant) => ({
                    color: variant.color,
                    sizes: variant.sizes.map((s) => ({
                        size: s.size,
                        stock: s.stock,
                        price: s.price,
                        sale_price: s.has_sale ? s.sale_price : null,
                        sale_starts_at: s.has_sale ? s.sale_starts_at : null,
                        sale_ends_at: s.has_sale ? s.sale_ends_at : null,
                    })),
                })),
            ),
        );
        product.variants.forEach((variant, vIndex) => {
            variant.images.forEach((file, imgIndex) => {
                formData.append(
                    `variant_images[${vIndex}][${imgIndex}]`,
                    file,
                );
            });
        });
    }

    try {
        const response = await api.post("/products", formData);
        Swal.fire({ toast: true, position: 'top-end', title: 'Thành công', text: 'Thêm sản phẩm thành công!', icon: 'success', showConfirmButton: false, timer: 3000 });
        router.push("/admin/product");
    } catch (error) {
        const responseData = error.response?.data;
        console.error("Error creating product:", responseData || error);
        if (error.response?.data?.errors) {
            errors.value = normalizeServerErrors(error.response.data.errors);
        }
        Swal.fire(
            'Lỗi',
            firstErrorMessage(error.response?.data?.errors) || error.response?.data?.message || "Lỗi khi thêm sản phẩm",
            'error',
        );
    }
};

onMounted(() => {
    handleFetchCategories();
    handleFetchBrands();
    nextTick(() => {
        initQuill();
    });
});
</script>

<template>
    <div class="create-product-page">
        <form @submit.prevent="handleSubmit" novalidate enctype="multipart/form-data">
            <!-- Page Header -->
            <div class="page-header animate-in">
                <div class="header-info">
                    <div class="back-link">
                        <router-link to="/admin/product">
                            <svg
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            Trở Về
                        </router-link>
                    </div>
                    <h1 class="page-title">Thêm Sản Phẩm Mới</h1>
                    <p class="page-subtitle">
                        Thêm một mặt hàng mới vào danh mục cửa hàng của bạn
                    </p>
                </div>
                <div class="header-actions">
                    <router-link to="/admin/product" class="btn-outline">Hủy bỏ</router-link>
                    <button type="submit" class="btn-primary">
                        <svg
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"
                            ></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        Lưu Sản Phẩm
                    </button>
                </div>
            </div>

            <div class="form-container">
                <!-- Left Column: Main Information -->
                <div class="form-column main-col">
                    <div
                        class="ocean-card form-card animate-in"
                        style="animation-delay: 0.1s"
                    >
                        <h3 class="card-title">Thông Tin Cơ Bản</h3>

                        <div class="form-group">
                            <label
                                >Tên Sản Phẩm
                                <span class="required">*</span></label
                            >
                            <input
                                type="text"
                                v-model="product.name"
                                @input="generateSlug"
                                class="form-control"
                                :class="{'is-invalid': errors.name}"
                                placeholder="Ví dụ: Đồng Hồ Xanh Đại Dương"
                            />
                            <span v-if="errors.name" class="field-error">
                                {{ errors.name }}
                            </span>
                        </div>
                        <div class="form-group">
                            <label>Mô Tả Ngắn</label>
                            <div class="quill-wrapper editor-short" :class="{'is-invalid': errors.short_description}">
                                <div ref="editorShort"></div>
                            </div>
                            <span v-if="errors.short_description" class="field-error">
                                {{ errors.short_description }}
                            </span>
                        </div>

                        <div class="form-group">
                            <label>Mô Tả Chi Tiết</label>
                            <div class="quill-wrapper editor-long" :class="{'is-invalid': errors.description}">
                                <div ref="editorLong"></div>
                            </div>
                            <span v-if="errors.description" class="field-error">
                                {{ errors.description }}
                            </span>
                        </div>
                    </div>

                    <!-- Tech Specs -->
                    <div
                        class="ocean-card form-card animate-in"
                        style="animation-delay: 0.15s"
                    >
                        <h3 class="card-title">Thông Số Kỹ Thuật</h3>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Trọng Lượng (gram)</label>
                                    <input
                                        type="number"
                                        v-model="product.weight"
                                        class="form-control"
                                        placeholder="Ví dụ: 300"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Chất Liệu</label>
                                    <input
                                        type="text"
                                        v-model="product.material"
                                        class="form-control"
                                        placeholder="Ví dụ: Cotton"
                                    />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Xuất Xứ</label>
                                    <input
                                        type="text"
                                        v-model="product.origin"
                                        class="form-control"
                                        placeholder="Ví dụ: Việt Nam"
                                    />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Kiểu Dáng</label>
                                    <input
                                        type="text"
                                        v-model="product.style"
                                        class="form-control"
                                        placeholder="Ví dụ: Slim Fit"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="ocean-card form-card animate-in"
                        style="animation-delay: 0.2s"
                    >
                        <h3 class="card-title">Giá và Loại Sản Phẩm</h3>

                        <div class="form-group type-selector">
                            <label>Loại Sản Phẩm</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input
                                        type="radio"
                                        v-model="product.product_type"
                                        value="simple"
                                    />
                                    <div class="radio-box">
                                        <span class="radio-text"
                                            >Sản Phẩm Đơn</span
                                        >
                                        <span class="radio-desc"
                                            >Một mặt hàng không có biến
                                            thể</span
                                        >
                                    </div>
                                </label>
                                <label class="radio-label">
                                    <input
                                        type="radio"
                                        v-model="product.product_type"
                                        value="variant"
                                    />
                                    <div class="radio-box">
                                        <span class="radio-text"
                                            >Sản Phẩm Biến Thể</span
                                        >
                                        <span class="radio-desc"
                                            >Sản phẩm có nhiều màu sắc/kích
                                            cỡ</span
                                        >
                                    </div>
                                </label>
                            </div>
                            <span v-if="errors.product_type" class="field-error">
                                {{ errors.product_type }}
                            </span>
                        </div>

                        <div
                            class="price-grid"
                            v-if="product.product_type === 'simple'"
                        >
                            <div class="form-group">
                                <label
                                    >Giá Bán
                                    <span class="required">*</span></label
                                >
                                <div class="input-with-prefix" :class="{'is-invalid': errors.price}">
                                    <span class="prefix">₫</span>
                                    <input
                                        type="number" min="100000" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();"
                                        v-model="product.price"
                                        class="form-control"
                                        placeholder="0"
                                    />
                                </div>
                                <span v-if="errors.price" class="field-error">
                                    {{ errors.price }}
                                </span>
                            </div>
                            <div class="form-group">
                                <label>Giá Gốc Trước Giảm</label>
                                <div class="input-with-prefix">
                                    <span class="prefix">₫</span>
                                    <input
                                        type="number" min="100000" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();"
                                        v-model="product.compare_at_price"
                                        class="form-control"
                                        placeholder="0"
                                    />
                                </div>
                                <span class="field-hint"
                                    >Hiển thị gạch ngang trên giá này nếu
                                    có</span
                                >
                            </div>
                            <div class="form-group" style="grid-column: span 2">
                                <label
                                    >Số Lượng Kho
                                    <span class="required">*</span></label
                                >
                                <input
                                    type="number" min="0" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();"
                                    v-model="product.stock"
                                    class="form-control"
                                    :class="{'is-invalid': errors.stock}"
                                    placeholder="0"
                                />
                                <span v-if="errors.stock" class="field-error">
                                    {{ errors.stock }}
                                </span>
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

                    <!-- Variable Product Section -->
                    <div
                        v-if="product.product_type === 'variant'"
                        class="ocean-card form-card animate-in"
                        style="animation-delay: 0.3s"
                    >
                        <div class="card-header-flex">
                            <h3 class="card-title">Biến Thể Sản Phẩm</h3>
                            <button
                                class="btn-outline-small"
                                type="button"
                                @click.prevent="addVariant"
                            >
                                <svg
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Thêm Biến Thể
                            </button>
                        </div>
                        <span v-if="errors.variants_global" class="field-error" style="display: block; margin-bottom: 12px;">
                            {{ errors.variants_global }}
                        </span>
                        <span v-if="errors.variant_images_global" class="field-error" style="display: block; margin-bottom: 12px;">
                            {{ errors.variant_images_global }}
                        </span>

                        <div
                            class="variant-item"
                            v-for="(variant, vIndex) in product.variants"
                            :key="vIndex"
                        >
                            <div class="variant-header">
                                <h4>
                                    Thuộc Tính Màu/Tên Lựa Chọn #{{
                                        vIndex + 1
                                    }}
                                </h4>
                                <button
                                    class="btn-icon-danger"
                                    type="button"
                                    title="Xóa biến thể"
                                    @click.prevent="removeVariant(vIndex)"
                                >
                                    <svg
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <polyline
                                            points="3 6 5 6 21 6"
                                        ></polyline>
                                        <path
                                            d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                                        ></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="variant-body">
                                <div class="form-group">
                                    <label>Tên Màu Sắc / Kiểu Dáng</label>
                                    <input
                                        type="text"
                                        v-model="variant.color"
                                        class="form-control"
                                        :class="{'is-invalid': errors.variants && errors.variants[vIndex]?.color}"
                                        placeholder="Ví dụ: Xanh Đại Dương"
                                    />
                                    <span
                                        v-if="
                                            errors.variants &&
                                            errors.variants[vIndex]?.color
                                        "
                                        class="field-error"
                                    >
                                        {{ errors.variants[vIndex].color }}
                                    </span>
                                </div>

                                <div class="form-group">
                                    <label>Hình Ảnh Biến Thể</label>
                                    <div class="variant-images-grid">
                                        <div
                                            v-for="(
                                                preview, imgIndex
                                            ) in variant.imagePreviews"
                                            :key="imgIndex"
                                            class="variant-img-item"
                                        >
                                            <img
                                                :src="preview"
                                                alt="Variant Preview"
                                            />
                                            <button
                                                type="button"
                                                class="remove-img-btn"
                                                @click.prevent="
                                                    removeVariantImage(
                                                        vIndex,
                                                        imgIndex,
                                                    )
                                                "
                                            >
                                                ×
                                            </button>
                                        </div>
                                        <div class="variant-img-add">
                                            <svg
                                                width="20"
                                                height="20"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                opacity="0.5"
                                            >
                                                <line
                                                    x1="12"
                                                    y1="5"
                                                    x2="12"
                                                    y2="19"
                                                ></line>
                                                <line
                                                    x1="5"
                                                    y1="12"
                                                    x2="19"
                                                    y2="12"
                                                ></line>
                                            </svg>
                                            <span>Thêm ảnh</span>
                                            <input
                                                type="file"
                                                class="file-input-hide"
                                                accept="image/*"
                                                @change="
                                                    (e) =>
                                                        handleVariantImageChange(
                                                            e,
                                                            vIndex,
                                                        )
                                                "
                                                multiple
                                            />
                                        </div>
                                    </div>
                                    <span v-if="errors.variant_images && errors.variant_images[vIndex]" class="field-error" style="margin-top: 10px; display:block;">
                                        {{ errors.variant_images[vIndex] }}
                                    </span>
                                </div>

                                <div class="sizes-section">
                                    <label>Kích Cỡ / Số Lượng</label>
                                    <table class="sizes-table">
                                        <thead>
                                            <tr>
                                                <th>Size (tùy chọn)</th>
                                                <th>Số Lượng Kho</th>
                                                <th>Giá (₫)</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template
                                                v-for="(
                                                    s, sIndex
                                                ) in variant.sizes"
                                                :key="sIndex"
                                            >
                                                <tr>
                                                    <td>
                                                        <input
                                                            type="text"
                                                            v-model="s.size"
                                                            class="form-control input-sm"
                                                            :class="{
                                                                'input-error':
                                                                    errors.variants &&
                                                                    errors
                                                                        .variants[
                                                                        vIndex
                                                                    ]?.sizes?.[
                                                                        sIndex
                                                                    ]?.size,
                                                            }"
                                                            placeholder="Để trống nếu không cần"
                                                        />
                                                        <span
                                                            v-if="
                                                                errors.variants && errors.variants[
                                                                    vIndex
                                                                ]?.sizes?.[sIndex]
                                                                    ?.size
                                                            "
                                                            class="field-error"
                                                        >
                                                            {{
                                                                errors.variants[
                                                                    vIndex
                                                                ].sizes[sIndex]
                                                                    .size
                                                            }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <input
                                                            type="number" min="0" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();"
                                                            v-model="s.stock"
                                                            class="form-control input-sm"
                                                            :class="{
                                                                'input-error':
                                                                    errors.variants &&
                                                                    errors
                                                                        .variants[
                                                                        vIndex
                                                                    ]?.sizes?.[
                                                                        sIndex
                                                                    ]?.stock,
                                                            }"
                                                            placeholder="0"
                                                        />
                                                        <span
                                                            v-if="
                                                                errors.variants && errors.variants[
                                                                    vIndex
                                                                ]?.sizes?.[sIndex]
                                                                    ?.stock
                                                            "
                                                            class="field-error"
                                                        >
                                                            {{
                                                                errors.variants[
                                                                    vIndex
                                                                ].sizes[sIndex]
                                                                    .stock
                                                            }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <input
                                                            type="number" min="100000" oninput="if(this.value < 0) this.value = Math.abs(this.value)" @keydown="if(['-','e','E'].includes($event.key)) $event.preventDefault();"
                                                            v-model="s.price"
                                                            class="form-control input-sm"
                                                            :class="{
                                                                'input-error':
                                                                    errors.variants &&
                                                                    errors
                                                                        .variants[
                                                                        vIndex
                                                                    ]?.sizes?.[
                                                                        sIndex
                                                                    ]?.price,
                                                            }"
                                                            placeholder="0"
                                                        />
                                                        <span
                                                            v-if="
                                                                errors.variants && errors.variants[
                                                                    vIndex
                                                                ]?.sizes?.[sIndex]
                                                                    ?.price
                                                            "
                                                            class="field-error"
                                                        >
                                                            {{
                                                                errors.variants[
                                                                    vIndex
                                                                ].sizes[sIndex]
                                                                    .price
                                                            }}
                                                        </span>
                                                    </td>
                                                    <td class="action-cell">
                                                        <button
                                                            class="btn-sale-toggle"
                                                            :class="{ active: s.has_sale }"
                                                            type="button"
                                                            title="Thiết lập khuyến mãi"
                                                            @click.prevent="s.has_sale = !s.has_sale"
                                                        >
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
                                                                <line x1="7" y1="7" x2="7.01" y2="7"/>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            class="btn-icon-danger square"
                                                            type="button"
                                                            @click.prevent="
                                                                removeSize(
                                                                    vIndex,
                                                                    sIndex,
                                                                )
                                                            "
                                                        >
                                                            <svg
                                                                width="14"
                                                                height="14"
                                                                viewBox="0 0 24 24"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                stroke-width="2"
                                                            >
                                                                <line
                                                                    x1="18"
                                                                    y1="6"
                                                                    x2="6"
                                                                    y2="18"
                                                                ></line>
                                                                <line
                                                                    x1="6"
                                                                    y1="6"
                                                                    x2="18"
                                                                    y2="18"
                                                                ></line>
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
                                                <tr v-if="errors.variants && errors.variants[vIndex]?.sizes?.[sIndex]?.duplicate">
                                                    <td colspan="4" style="padding: 0 10px 10px;">
                                                        <span
                                                            class="field-error"
                                                            style="
                                                                color: #c62828;
                                                                margin-top: 0;
                                                            "
                                                        >
                                                            ⚠
                                                            {{
                                                                errors.variants[
                                                                    vIndex
                                                                ].sizes[sIndex]
                                                                    .duplicate
                                                            }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                    <button
                                        class="btn-text-link mt-2"
                                        type="button"
                                        @click.prevent="addSize(vIndex)"
                                    >
                                        + Thêm Kích Cỡ
                                    </button>
                                    <span
                                        v-if="
                                            errors.variants &&
                                            errors.variants[vIndex]
                                                ?.sizes_global
                                        "
                                        class="field-error"
                                        style="display: block; margin-top: 10px;"
                                    >
                                        {{
                                            errors.variants[vIndex].sizes_global
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Settings -->
                <div class="form-column side-col">
                    <div
                        class="ocean-card form-card animate-in"
                        style="animation-delay: 0.15s"
                    >
                        <h3 class="card-title">Hình Ảnh Sản Phẩm</h3>
                        <div class="form-group mb-0">
                            <label
                                >Ảnh Bìa Chính
                                <span class="required">*</span></label
                            >
                            <div class="image-upload-box">
                                <div
                                    v-if="product.imagePreview"
                                    class="preview-container"
                                >
                                    <img
                                        :src="product.imagePreview"
                                        alt="Preview"
                                        class="img-preview"
                                    />
                                    <button
                                        type="button"
                                        class="remove-img-btn"
                                        @click.prevent="removeThumbnailImage"
                                    >
                                        ×
                                    </button>
                                </div>
                                <div v-else class="upload-placeholder">
                                    <svg
                                        width="32"
                                        height="32"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        opacity="0.5"
                                    >
                                        <rect
                                            x="3"
                                            y="3"
                                            width="18"
                                            height="18"
                                            rx="2"
                                            ry="2"
                                        ></rect>
                                        <circle
                                            cx="8.5"
                                            cy="8.5"
                                            r="1.5"
                                        ></circle>
                                        <polyline
                                            points="21 15 16 10 5 21"
                                        ></polyline>
                                    </svg>
                                    <span
                                        >Kéo thả ảnh hoặc Bấm tải Lên Ảnh Bìa
                                        Chính</span
                                    >
                                    <span class="upload-hint"
                                        >Khuyến nghị: Định dạng 800x800px</span
                                    >
                                    <input
                                        type="file"
                                        class="file-input-hide"
                                        accept="image/*"
                                        @change="handleThumbnailChange"
                                    />
                                </div>
                            </div>
                            <span
                                v-if="errors.thumbnail_url || errors.thumbnail"
                                class="field-error" style="margin-top: 10px;text-align:center;display:block;"
                            >
                                {{ errors.thumbnail_url || errors.thumbnail }}
                            </span>
                        </div>

                        <!-- Gallery Upload -->
                        <div class="form-group mt-4 mb-0">
                            <label>Ảnh Phụ (Nhiều ảnh)</label>
                            <div class="gallery-upload-container">
                                <div
                                    v-for="(
                                        preview, index
                                    ) in product.galleryPreviews"
                                    :key="index"
                                    class="gallery-item"
                                >
                                    <img :src="preview" alt="Gallery Preview" />
                                    <button
                                        class="remove-img-btn"
                                        type="button"
                                        @click.prevent="
                                            removeGalleryImage(index)
                                        "
                                    >
                                        ×
                                    </button>
                                </div>
                                <div class="gallery-add-btn">
                                    <svg
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        opacity="0.5"
                                    >
                                        <line
                                            x1="12"
                                            y1="5"
                                            x2="12"
                                            y2="19"
                                        ></line>
                                        <line
                                            x1="5"
                                            y1="12"
                                            x2="19"
                                            y2="12"
                                        ></line>
                                    </svg>
                                    <input
                                        type="file"
                                        class="file-input-hide"
                                        accept="image/*"
                                        multiple
                                        @change="handleGalleryChange"
                                    />
                                </div>
                            </div>
                            <span v-if="errors.gallery" class="field-error" style="margin-top: 10px; display:block;">
                                {{ errors.gallery }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="ocean-card form-card animate-in"
                        style="animation-delay: 0.25s"
                    >
                        <h3 class="card-title">Thông Tin Phân Loại</h3>

                        <div class="form-group">
                            <label>Danh Mục <span class="required">*</span></label>
                            <select
                                v-model="product.category_id"
                                class="form-control form-select"
                                :class="{'is-invalid': errors.category_id}"
                            >
                                <option value="">
                                    Chọn danh mục cho sản phẩm
                                </option>
                                <AdminCategoryFormTree
                                    :categories="categories"
                                />
                            </select>
                            <span v-if="errors.category_id" class="field-error">
                                {{ errors.category_id }}
                            </span>
                        </div>

                        <div class="form-group">
                            <label>Thương Hiệu</label>
                            <select
                                v-model="product.brand_id"
                                class="form-control form-select"
                            >
                                <option value="">
                                    Chọn thương hiệu cho sản phẩm
                                </option>
                                <option
                                    v-for="b in brands"
                                    :key="b.brand_id || b.id"
                                    :value="b.brand_id || b.id"
                                >
                                    {{ b.name }}
                                </option>
                            </select>
                            <span v-if="errors.brand_id" class="field-error">
                                {{ errors.brand_id }}
                            </span>
                        </div>

                        <div class="form-group">
                            <label>Nhà Cung Cấp</label>
                            <input
                                type="text"
                                v-model="product.seller_id"
                                class="form-control"
                                :class="{'is-invalid': errors.seller_id}"
                                placeholder="Nhập mã nhà cung cấp"
                            />
                            <span v-if="errors.seller_id" class="field-error">
                                {{ errors.seller_id }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="ocean-card form-card animate-in"
                        style="animation-delay: 0.35s"
                    >
                        <h3 class="card-title">Trạng Thái Xét Duyệt</h3>

                        <div class="form-group">
                            <label>Trạng Thái</label>
                            <select
                                v-model="product.status"
                                class="form-control form-select"
                            >
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
                                    <span
                                        >Sản phẩm này sẽ hiện trên trang chủ mục
                                        tiêu biểu</span
                                    >
                                </span>
                                <div class="toggle-switch">
                                    <input
                                        type="checkbox"
                                        v-model="product.is_featured"
                                        class="toggle-input"
                                    />
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
.create-product-page {
    font-family: var(--font-inter);
    padding-bottom: 40px;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.back-link {
    margin-bottom: 8px;
}

.back-link a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    text-decoration: none;
    transition: color 0.2s;
}

.back-link a:hover {
    color: var(--primary);
}

.page-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1.2;
}

.page-subtitle {
    font-size: 0.9rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-top: 4px;
}

.header-actions {
    display: flex;
    gap: 12px;
}

/* Buttons */
.btn-primary {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    background: var(--primary);
    color: white;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2);
    transition: all 0.2s;
}

.btn-primary:hover {
    background: var(--ocean-bright);
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(230, 59, 111, 0.3);
}

.btn-outline {
    padding: 10px 20px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-main);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-outline:hover {
    background: var(--ocean-deepest);
    border-color: var(--text-light);
}

.btn-outline-small {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    background: transparent;
    color: var(--primary);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-outline-small:hover {
    background: rgba(230, 59, 111, 0.05);
    border-color: var(--primary);
}

.btn-text-link {
    background: none;
    border: none;
    color: var(--primary);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
}

.btn-text-link:hover {
    text-decoration: underline;
}

.btn-icon-danger {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid transparent;
    background: rgba(239, 83, 80, 0.1);
    color: var(--coral);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-icon-danger:hover {
    background: var(--coral);
    color: white;
}

.btn-icon-danger.square {
    width: 32px;
    height: 32px;
    border-radius: 6px;
}

/* Grid Layout */
.form-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}

@media (max-width: 900px) {
    .form-container {
        grid-template-columns: 1fr;
    }
}

.form-column {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* Cards */
.form-card {
    padding: 24px;
}

.card-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-main);
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}

.card-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}

.card-header-flex .card-title {
    border-bottom: none;
    padding-bottom: 0;
    margin-bottom: 0;
}

/* Forms */
.form-group {
    margin-bottom: 18px;
}

.form-group.mb-0 {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 8px;
}

.required {
    color: var(--coral);
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-main);
    font-family: var(--font-inter);
    font-size: 0.85rem;
    transition: all 0.2s;
}

.form-control:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

.form-control::placeholder {
    color: var(--text-light);
}

.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
}

.input-sm {
    padding: 8px 10px;
    font-size: 0.8rem;
}

.price-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.input-with-prefix {
    display: flex;
    align-items: center;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
    background: var(--card-bg);
    transition: all 0.2s;
}

.input-with-prefix:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

.prefix {
    padding: 10px 14px;
    background: var(--ocean-deepest);
    color: var(--text-muted);
    font-weight: 600;
    border-right: 1px solid var(--border-color);
    font-size: 0.85rem;
}

.input-with-prefix .form-control {
    border: none;
    border-radius: 0;
    box-shadow: none !important;
}

/* Radio Type Selector */
.radio-group {
    display: flex;
    gap: 16px;
}

.radio-label {
    flex: 1;
    cursor: pointer;
}

.radio-label input[type="radio"] {
    display: none;
}

.radio-box {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 14px;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.radio-text {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-main);
    display: block;
}

.radio-desc {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
}

.radio-label input[type="radio"]:checked + .radio-box {
    border-color: var(--primary);
    background: rgba(230, 59, 111, 0.05);
    box-shadow: 0 0 0 1px var(--primary);
}

/* Image Upload */
.image-upload-box {
    border: 2px dashed var(--border-color);
    border-radius: 10px;
    background: var(--ocean-deepest);
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}

.image-upload-box:hover {
    border-color: var(--primary);
    background: var(--hover-bg);
}

.upload-placeholder {
    padding: 40px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-align: center;
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
}

.upload-hint {
    font-size: 0.7rem;
    font-weight: 500;
    opacity: 0.7;
}

.file-input-hide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.image-upload-box.small .upload-placeholder {
    padding: 20px 10px;
}

.preview-container {
    position: relative;
    width: 100%;
    padding-top: 100%;
    /* 1:1 Aspect Ratio */
}

.img-preview {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 10px;
}

.remove-img-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--coral);
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    z-index: 10;
}

/* Variants */
.variant-item {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    margin-bottom: 20px;
    overflow: hidden;
    background: var(--ocean-deepest);
}

.variant-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: var(--card-bg);
    border-bottom: 1px solid var(--border-color);
}

.variant-header h4 {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-main);
}

.variant-body {
    padding: 16px;
}

.sizes-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
}

.sizes-table th {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-align: left;
    padding: 0 8px 8px 0;
}

.sizes-table td {
    padding: 0 8px 8px 0;
}

.sizes-table td:last-child {
    padding-right: 0;
    width: 40px;
}

/* Toggle Switch */
.toggle-switch-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}

.toggle-label {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.toggle-label strong {
    font-size: 0.85rem;
    color: var(--text-main);
}

.toggle-label span {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
}

.toggle-switch {
    position: relative;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}

.toggle-input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--text-light);
    transition: 0.3s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: var(--card-bg);
    transition: 0.3s;
    border-radius: 50%;
}

.toggle-input:checked + .toggle-slider {
    background-color: var(--primary);
}

.toggle-input:checked + .toggle-slider:before {
    transform: translateX(20px);
}

/* Utility */
.mt-2 {
    margin-top: 8px;
}

.mt-4 {
    margin-top: 16px;
}

.mb-0 {
    margin-bottom: 0 !important;
}

/* Error & Hints */
.error-message {
    color: #ef5350;
    font-size: 0.78rem;
    font-weight: 600;
    margin-top: 4px;
}

.input-error {
    border-color: #ef5350 !important;
    box-shadow: 0 0 0 2px rgba(239, 83, 80, 0.15) !important;
}

.error-row td {
    border-bottom: none !important;
}

.field-hint {
    display: block;
    font-size: 0.75rem;
    color: var(--text-light);
    font-weight: 500;
    margin-top: 4px;
}

/* Gallery Upload */
.gallery-upload-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.gallery-item {
    width: 80px;
    height: 80px;
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition:
        transform 0.2s,
        box-shadow 0.2s;
}

.gallery-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px;
}

.gallery-item .remove-img-btn {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #ef5350;
    color: white;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    cursor: pointer;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    opacity: 0;
    transition: opacity 0.2s;
}

.gallery-item:hover .remove-img-btn {
    opacity: 1;
}

.gallery-add-btn {
    width: 80px;
    height: 80px;
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    cursor: pointer;
    background: var(--ocean-deepest);
    transition:
        border-color 0.2s,
        background 0.2s;
}

.gallery-add-btn:hover {
    border-color: var(--primary);
    background: rgba(230, 59, 111, 0.04);
}

/* Variant Images Grid */
.variant-images-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.variant-img-item {
    width: 72px;
    height: 72px;
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border-color);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    transition:
        transform 0.2s,
        box-shadow 0.2s;
}

.variant-img-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.variant-img-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.variant-img-item .remove-img-btn {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: rgba(239, 83, 80, 0.9);
    color: white;
    border: none;
    font-size: 12px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s;
    z-index: 5;
    padding: 0;
}

.variant-img-item:hover .remove-img-btn {
    opacity: 1;
}

.variant-img-add {
    width: 72px;
    height: 72px;
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    position: relative;
    cursor: pointer;
    background: var(--ocean-deepest);
    transition:
        border-color 0.2s,
        background 0.2s;
}

.variant-img-add span {
    font-size: 0.6rem;
    color: var(--text-muted);
    font-weight: 600;
}

.variant-img-add:hover {
    border-color: var(--primary);
    background: rgba(230, 59, 111, 0.04);
}

/* Quill Custom Styles */
.quill-wrapper {
    display: flex;
    flex-direction: column;
}

.quill-wrapper :deep(.ql-toolbar.ql-snow) {
    border: 1px solid var(--border-color);
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    background: var(--ocean-deepest, #f8fafc);
    font-family: var(--font-inter);
    transition: border-color 0.2s;
}

.quill-wrapper :deep(.ql-container.ql-snow) {
    border: 1px solid var(--border-color);
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
    border-top: none;
    font-family: var(--font-inter);
    font-size: 0.9rem;
    background: var(--card-bg);
    transition: border-color 0.2s;
}

.quill-wrapper:focus-within :deep(.ql-toolbar.ql-snow) {
    border-color: var(--primary);
}

.quill-wrapper:focus-within :deep(.ql-container.ql-snow) {
    border-color: var(--primary);
}

.quill-wrapper :deep(.ql-editor) {
    color: var(--text-main);
}

.editor-short :deep(.ql-editor) {
    min-height: 100px;
    max-height: 250px;
}

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
    background: var(--surface-container);
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
    color: var(--primary);
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
    background: var(--surface-container);
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-sale-toggle:hover {
    background: var(--ocean-deepest);
    color: var(--primary);
}
.btn-sale-toggle.active {
    background: rgba(230, 59, 111, 0.1);
    color: var(--primary);
    border-color: var(--primary);
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
