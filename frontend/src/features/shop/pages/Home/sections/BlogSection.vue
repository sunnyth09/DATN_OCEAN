<script setup>
defineProps(['testimonials']);
</script>
<template>
        <section class="py-5 bg-light reveal-on-scroll">
            <div class="container">
                <div class="text-center mb-5">
                    <h2 class="section-title mb-2">KHÁCH HÀNG NÓI GÌ?</h2>
                    <p class="section-subtitle">Hơn 50,000 khách hàng đã tin tưởng và yêu thích Ocean Sport</p>
                </div>
                <div class="row g-4">
                    <div v-for="t in testimonials" :key="t.id" class="col-md-4">
                        <div class="testimonial-card h-100">
                            <div class="d-flex gap-1 mb-3">
                                <svg v-for="s in 5" :key="s" width="16" height="16" viewBox="0 0 24 24" fill="#FBBF24">
                                    <polygon
                                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                </svg>
                            </div>
                            <p class="testimonial-text">"{{ t.text }}"</p>
                            <div class="d-flex align-items-center gap-3 pt-3 border-top">
                                <img :src="t.avatar" :alt="t.name" class="testimonial-avatar" />
                                <div>
                                    <span class="d-block fw-bold" style="font-size:.9rem;">{{ t.name }}</span>
                                    <span class="d-block text-muted" style="font-size:.78rem;">{{ t.role }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
</template>


<style scoped>
.testimonial-card {
    background: var(--card-bg);
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 28px 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, .04);
    transition: transform .3s, box-shadow .3s;
    position: relative;
}

.testimonial-card::before {
    content: '"';
    position: absolute;
    top: 12px;
    right: 20px;
    font-size: 5rem;
    font-weight: 900;
    color: #fde8ef;
    line-height: 1;
    font-family: Georgia, serif;
}

.testimonial-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 40px rgba(0, 0, 0, .08);
}

.testimonial-text {
    color: #4B5563;
    font-size: .92rem;
    line-height: 1.7;
    margin: 0 0 0;
    font-style: italic;
}

.testimonial-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #fde8ef;
}
</style>
