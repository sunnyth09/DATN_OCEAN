<template>
  <div class="profile-page container">
    <div class="profile-container">
      <ProfileAside />
      <main class="profile-content">
        <router-view></router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import ProfileAside from '@/features/profile/components/ProfileAside.vue';
</script>

<style scoped>
.profile-page {
  padding-top: 32px;
  padding-bottom: 32px;
  min-height: 70vh;
}

.profile-container {
  display: flex;
  gap: 32px;
  align-items: flex-start;
  max-width: 1100px;
  margin: 0 auto;
}

.profile-content {
  flex: 1;
  min-width: 0;
}

@media (max-width: 768px) {
  .profile-container {
    flex-direction: column;
    align-items: stretch;
  }
  .profile-content {
    width: 100%;
  }
}
</style>
