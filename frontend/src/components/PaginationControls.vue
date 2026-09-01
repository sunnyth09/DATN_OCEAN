<template>
    <div v-if="lastPage > 1" class="pagination-controls">
        <div class="pagination-summary" v-if="total > 0">
            Hiển thị {{ displayFrom }}-{{ displayTo }} / {{ total }}
        </div>

        <div class="pagination-actions">
            <button class="page-btn" type="button" :disabled="disabled || currentPage <= 1" @click="emitPage(currentPage - 1)">
                Trước
            </button>

            <button
                v-for="item in pageItems"
                :key="item.key"
                class="page-number"
                :class="{ active: item.page === currentPage, ellipsis: item.ellipsis }"
                type="button"
                :disabled="disabled || item.ellipsis"
                @click="!item.ellipsis && emitPage(item.page)"
            >
                {{ item.label }}
            </button>

            <button class="page-btn" type="button" :disabled="disabled || currentPage >= lastPage" @click="emitPage(currentPage + 1)">
                Sau
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
    total: { type: Number, default: 0 },
    from: { type: Number, default: null },
    to: { type: Number, default: null },
    perPage: { type: Number, default: 5 },
    disabled: { type: Boolean, default: false },
    maxVisiblePages: { type: Number, default: 5 },
});

const emit = defineEmits(['change']);

const displayFrom = computed(() => props.from ?? ((props.currentPage - 1) * props.perPage + 1));
const displayTo = computed(() => props.to ?? Math.min(props.currentPage * props.perPage, props.total));

const pageItems = computed(() => {
    const last = Math.max(1, props.lastPage);
    const current = Math.min(Math.max(1, props.currentPage), last);
    const maxVisible = Math.max(5, props.maxVisiblePages);

    if (last <= maxVisible) {
        return Array.from({ length: last }, (_, index) => ({
            key: `page-${index + 1}`,
            page: index + 1,
            label: index + 1,
        }));
    }

    const pages = new Set([1, last, current]);
    pages.add(Math.max(1, current - 1));
    pages.add(Math.min(last, current + 1));

    const sorted = Array.from(pages).sort((a, b) => a - b);
    const items = [];

    sorted.forEach((page, index) => {
        const previous = sorted[index - 1];
        if (previous && page - previous > 1) {
            items.push({ key: `ellipsis-${previous}-${page}`, label: '...', ellipsis: true });
        }
        items.push({ key: `page-${page}`, page, label: page });
    });

    return items;
});

function emitPage(page) {
    const nextPage = Math.min(Math.max(Number(page) || 1, 1), props.lastPage);
    if (nextPage !== props.currentPage && !props.disabled) {
        emit('change', nextPage);
    }
}
</script>

<style scoped>
.pagination-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
    flex-wrap: wrap;
}

.pagination-summary {
    color: #6b7280;
    font-size: 0.9rem;
}

.pagination-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.page-btn,
.page-number {
    min-width: 36px;
    height: 36px;
    min-height: unset;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: #fff;
    color: #374151;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.page-btn {
    padding: 0 12px;
}

.page-btn:hover:not(:disabled),
.page-number:hover:not(:disabled) {
    border-color: #ec407a;
    color: #ec407a;
    background: #fff5f8;
}

.page-number.active {
    border-color: #ec407a;
    background: #ec407a;
    color: #fff;
}

.page-number.ellipsis {
    border-color: transparent;
    background: transparent;
    cursor: default;
}

.page-btn:disabled,
.page-number:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

@media (max-width: 640px) {
    .pagination-controls {
        justify-content: center;
    }

    .pagination-summary {
        width: 100%;
        text-align: center;
    }
}
</style>
