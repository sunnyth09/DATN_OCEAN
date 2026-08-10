<script setup>
defineProps({
    columns: {
        type: Number,
        default: 5
    },
    rows: {
        type: Number,
        default: 5
    }
});
</script>

<template>
    <div class="table-container ocean-card" style="position: relative;">
        <!-- Header Actions Skeleton -->
        <div class="table-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="skeleton-text" style="width: 150px; height: 20px; margin-top: 4px;"></div>
            <div class="skeleton-text" style="width: 100px; height: 32px; border-radius: 8px;"></div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th v-for="c in columns" :key="'th-' + c" :style="c === 1 ? 'width: 50px;' : (c === columns ? 'text-align: right; width: 120px;' : '')">
                        <div class="skeleton-text" style="width: 80%; height: 16px; display: inline-block;"></div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in rows" :key="'tr-' + r">
                    <!-- Checkbox or index column -->
                    <td><div class="skeleton-box" style="width: 20px; height: 20px; border-radius: 4px;"></div></td>
                    
                    <!-- Middle columns -->
                    <td v-for="c in (columns - 2)" :key="'td-' + r + '-' + c">
                        <div class="skeleton-text" :style="`width: ${Math.floor(Math.random() * 40 + 40)}%; height: 20px; border-radius: 8px;`"></div>
                    </td>

                    <!-- Actions column -->
                    <td>
                        <div class="actions-cell" style="justify-content: flex-end; display: flex; gap: 8px;">
                            <div class="skeleton-box" style="width: 36px; height: 36px; border-radius: 10px;"></div>
                            <div class="skeleton-box" style="width: 36px; height: 36px; border-radius: 10px;"></div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th, .data-table td {
    padding: 12px 16px;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}
.table-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}

/* ===== Premium Skeleton Loader ===== */
.skeleton-box, .skeleton-text {
    background: #e2e8f0;
    position: relative;
    overflow: hidden;
}
.skeleton-text { border-radius: 4px; }
.skeleton-box::after, .skeleton-text::after {
    content: ''; position: absolute;
    top: 0; right: 0; bottom: 0; left: 0;
    transform: translateX(-100%);
    background-image: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0,
        rgba(255, 255, 255, 0.4) 20%,
        rgba(255, 255, 255, 0.7) 60%,
        rgba(255, 255, 255, 0)
    );
    animation: shimmer 1.5s infinite;
}
@keyframes shimmer { 100% { transform: translateX(100%); } }
</style>
