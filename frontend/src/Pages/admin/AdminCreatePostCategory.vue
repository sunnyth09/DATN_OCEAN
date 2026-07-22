<script setup>
import { ref, reactive } from 'vue';
import AdminCategoryFormTree from '@/components/AdminCategoryFormTree.vue';
import api from '@/axios';
import { useRouter } from 'vue-router';
const router = useRouter();
const isSubmitting = ref(false);
const postCategory = reactive({
    name: '',
    parent_id: null,
    sort_order: 0,
    is_active: 1,
});

const handleSubmit = async () => {
    try {
        isSubmitting.value = true;
        const response = await api.post('/post-categories', postCategory);
        if (response.data.status === 'success') {
            showToast(response.data.message, 'success');
            router.push('/admin/post-categories');
        }
    } catch (error) {
        console.error(error);
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div>
        <h1>Admin Create Post Category</h1>
        <p>Form tạo danh mục bài viết sẽ được đặt ở đây.</p>
        <form action="" @submit.prevent="handleSubmit">
            <div class="form-group">
                <label for="name">Tên danh mục bài viết</label>
                <input
                    type="text"
                    id="name"
                    v-model="postCategory.name"
                    class="form-control"
                />
            </div>
                <div class="form-group">
                    <label for="parent">Danh muc cha</label>
                    <select id="parent" v-model="postCategory.parent_id" class="form-control">
                        <option value="">Chọn danh mục cha</option>
                        <AdminCategoryFormTree :categories="categories" :currentParentId="postCategory.parent_id" />
                    </select>
                </div>
            <button type="submit" class="btn btn-primary" :disabled="isSubmitting">Tạo danh mục {{ isSubmitting ? '...' : '' }}</button>
        </form>
    </div>
</template>
