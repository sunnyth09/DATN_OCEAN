<script setup>
const props = defineProps({
    category: { type: Object, required: true },
    level: { type: Number, default: 0 }
});
const emit = defineEmits(['edit', 'delete']);
</script>

<template>
    <tr>
        <td>
            <div class="name-cell" :style="{ paddingLeft: (level * 24) + 'px' }">
                <span v-if="level > 0" class="tree-icon">└</span>
                <!-- Thumbnail ảnh danh mục hoặc icon mặc định -->
                <div class="cat-icon-wrap">
                    <img
                        v-if="category.image_url"
                        :src="category.image_url"
                        :alt="category.name"
                        class="cat-thumbnail"
                    />
                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                    </svg>
                </div>
                <span class="cat-name">{{ category.name }}</span>
                <span v-if="category.children?.length" class="child-badge">{{ category.children.length }}</span>
            </div>
        </td>
        <td><span class="badge-id">#{{ category.category_id || category.post_category_id }}</span></td>
        <td>
            <span class="badge-status" :class="category.is_active ? 'active' : 'inactive'">
                {{ category.is_active ? 'Hiển thị' : 'Đang ẩn' }}
            </span>
        </td>
        <td><span class="val-order">{{ category.sort_order }}</span></td>
        <td>
            <div class="actions-cell">
                <button class="btn-icon edit" @click="emit('edit', category)" title="Chỉnh sửa">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="btn-icon del" @click="emit('delete', category.category_id || category.post_category_id)" title="Xóa">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
            </div>
        </td>
    </tr>
    <template v-if="category.children?.length">
        <AdminCategoryRow
            v-for="child in category.children"
            :key="child.category_id || child.post_category_id"
            :category="child"
            :level="level + 1"
            @edit="emit('edit', $event)"
            @delete="emit('delete', $event)"
        />
    </template>
</template>

<style scoped>
/* Name cell */
.name-cell { display: flex; align-items: center; gap: 10px; }
.tree-icon { color: var(--text-light); font-family: monospace; font-size: 1rem; flex-shrink: 0; }
.cat-icon-wrap {
    width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0;
    background: rgba(230, 59, 111, 0.08); border: 1px solid rgba(230, 59, 111, 0.15);
    display: flex; align-items: center; justify-content: center;
    color: #E63B6F; overflow: hidden;
}
.cat-thumbnail {
    width: 100%; height: 100%; object-fit: cover; border-radius: 7px;
}
.cat-name { font-size: 0.88rem; font-weight: 700; color: var(--text-main); }
.child-badge {
    font-size: 0.7rem; font-weight: 700;
    background: rgba(230, 59, 111, 0.1); color: #E63B6F;
    padding: 2px 8px; border-radius: 10px;
}

/* Badges */
.badge-id {
    padding: 4px 8px; border-radius: 6px; font-size: 0.8rem;
    font-weight: 700; background: rgba(230, 59, 111, 0.1); color: #E63B6F;
}
.badge-status {
    display: inline-block; padding: 4px 10px; border-radius: 6px;
    font-size: 0.75rem; font-weight: 700;
}
.active { background: rgba(38, 166, 154, 0.15); color: #167a70; }
.inactive { background: rgba(158, 158, 158, 0.12); color: #757575; }
.val-order { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }

/* Actions — match AdminProduct style */
.actions-cell { display: flex; gap: 6px; }
.btn-icon {
    width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-muted);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.edit:hover { color: var(--seafoam); border-color: var(--seafoam); background: rgba(38, 166, 154, 0.05); }
.del:hover { color: var(--coral); border-color: var(--coral); background: rgba(239, 83, 80, 0.05); }
</style>
