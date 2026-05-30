<template>
  <div class="profile-page">
    <div class="profile-container">
      <ProfileAside />
      <main class="profile-content">
        <router-view></router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import ProfileAside from '@/components/ProfileAside.vue';
</script>

<style scoped>
.profile-page {
  padding: 32px 0;
  min-height: 70vh;
}

.profile-container {
  display: flex;
  gap: 24px;
  align-items: flex-start;
}

.profile-content {
  flex: 1;
  min-width: 0;
}

@media (max-width: 768px) {
  .profile-container {
    flex-direction: column;
  }
}
</style>
