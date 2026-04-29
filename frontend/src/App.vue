<script setup>
import { computed } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { clearSession, getToken } from './services/auth';

const router = useRouter();
const isAuthenticated = computed(() => Boolean(getToken()));

function logout() {
  clearSession();
  router.push({ name: 'login' });
}
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <RouterLink class="brand" to="/dashboard">WatchEng</RouterLink>

      <nav class="nav-actions" aria-label="Primary navigation">
        <RouterLink v-if="!isAuthenticated" to="/login">Login</RouterLink>
        <RouterLink v-if="!isAuthenticated" to="/register">Register</RouterLink>
        <button v-if="isAuthenticated" class="ghost-button" type="button" @click="logout">Logout</button>
      </nav>
    </header>

    <main>
      <RouterView />
    </main>
  </div>
</template>
