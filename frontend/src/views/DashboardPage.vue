<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { loadDashboard } from '../services/api';
import { clearSession, getSavedUser } from '../services/auth';

const router = useRouter();
const dashboard = ref(null);
const error = ref('');
const isLoading = ref(true);
const savedUser = getSavedUser();

async function load() {
  error.value = '';
  isLoading.value = true;

  try {
    dashboard.value = await loadDashboard();
  } catch (exception) {
    error.value = exception.message;

    if (exception.message.toLowerCase().includes('jwt')) {
      clearSession();
      router.push({ name: 'login' });
    }
  } finally {
    isLoading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <section class="dashboard-layout">
    <div class="dashboard-header">
      <div>
        <p class="eyebrow">Dashboard</p>
        <h1>{{ dashboard?.message || 'Workspace overview' }}</h1>
      </div>

      <button class="secondary-button" type="button" @click="load" :disabled="isLoading">
        {{ isLoading ? 'Refreshing...' : 'Refresh' }}
      </button>
    </div>

    <p v-if="error" class="notice error">{{ error }}</p>
    <p v-if="isLoading" class="notice">Loading dashboard...</p>

    <div v-if="dashboard" class="dashboard-grid">
      <article class="info-card">
        <span>Tenant</span>
        <strong>{{ dashboard.tenant.name }}</strong>
        <p>{{ dashboard.tenant.subdomain }}</p>
      </article>

      <article class="info-card">
        <span>User</span>
        <strong>{{ dashboard.user.email || savedUser?.email }}</strong>
        <p>{{ dashboard.user.roles.join(', ') }}</p>
      </article>

      <article class="info-card wide">
        <span>Access model</span>
        <strong>Tenant context active</strong>
        <p>Requests are authenticated with a JWT and resolved against the current tenant host.</p>
      </article>
    </div>
  </section>
</template>
