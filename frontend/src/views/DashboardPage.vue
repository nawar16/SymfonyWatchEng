<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { createMonitor, deleteMonitor, loadDashboard, loadMonitors } from '../services/api';
import { clearSession, getSavedUser } from '../services/auth';

const router = useRouter();
const dashboard = ref(null);
const monitors = ref([]);
const monitorUrl = ref('');
const error = ref('');
const monitorError = ref('');
const monitorFeedback = ref('');
const isLoading = ref(true);
const isMonitorsLoading = ref(false);
const isCreatingMonitor = ref(false);
const removingMonitorId = ref(null);
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

async function loadMonitorList() {
  monitorError.value = '';
  isMonitorsLoading.value = true;

  try {
    monitors.value = await loadMonitors();
  } catch (exception) {
    monitorError.value = exception.message;
  } finally {
    isMonitorsLoading.value = false;
  }
}

async function addMonitor() {
  monitorError.value = '';
  monitorFeedback.value = '';
  isCreatingMonitor.value = true;

  try {
    const monitor = await createMonitor(monitorUrl.value);
    monitors.value = [monitor, ...monitors.value];
    monitorUrl.value = '';
    monitorFeedback.value = 'Monitor added.';
  } catch (exception) {
    monitorError.value = exception.message;
  } finally {
    isCreatingMonitor.value = false;
  }
}

async function removeMonitor(id) {
  monitorError.value = '';
  monitorFeedback.value = '';
  removingMonitorId.value = id;

  try {
    await deleteMonitor(id);
    monitors.value = monitors.value.filter((monitor) => monitor.id !== id);
    monitorFeedback.value = 'Monitor removed.';
  } catch (exception) {
    monitorError.value = exception.message;
  } finally {
    removingMonitorId.value = null;
  }
}

onMounted(async () => {
  await load();

  if (dashboard.value) {
    await loadMonitorList();
  }
});
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
        <div class="monitor-header">
          <div>
            <span>Monitor management</span>
            <!-- <strong>Website checks</strong> -->
          </div>

          <button class="secondary-button" type="button" @click="loadMonitorList" :disabled="isMonitorsLoading">
            {{ isMonitorsLoading ? 'Loading...' : 'Reload' }}
          </button>
        </div>

        <form class="monitor-form" @submit.prevent="addMonitor">
          <label>
            Website URL
            <input v-model="monitorUrl" type="url" required placeholder="https://example.com" />
          </label>

          <button class="primary-button" type="submit" :disabled="isCreatingMonitor">
            {{ isCreatingMonitor ? 'Adding...' : 'Add monitor' }}
          </button>
        </form>

        <p v-if="monitorError" class="notice error">{{ monitorError }}</p>
        <p v-if="monitorFeedback" class="notice success">{{ monitorFeedback }}</p>
        <p v-if="isMonitorsLoading" class="notice">Loading monitors...</p>

        <div v-else-if="monitors.length" class="monitor-table-wrap">
          <table class="monitor-table">
            <thead>
              <tr>
                <th>URL</th>
                <th>Frequency</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="monitor in monitors" :key="monitor.id">
                <td>
                  <a :href="monitor.url" target="_blank" rel="noreferrer">{{ monitor.url }}</a>
                </td>
                <td>{{ monitor.frequency }}s</td>
                <td class="monitor-actions">
                  <button class="danger-button" type="button" @click="removeMonitor(monitor.id)" :disabled="removingMonitorId === monitor.id">
                    {{ removingMonitorId === monitor.id ? 'Removing...' : 'Remove' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-else class="empty-state">No monitors yet.</p>
      </article>
    </div>
  </section>
</template>
