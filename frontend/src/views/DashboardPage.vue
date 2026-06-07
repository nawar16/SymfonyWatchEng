<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { createMonitor, deleteMonitor, loadDashboard, loadMonitors, saveMonitorEscalationSteps, saveMonitorNotificationRule } from '../services/api';
import { clearSession, getSavedUser } from '../services/auth';

const router = useRouter();
const dashboard = ref(null);
const monitors = ref([]);
const monitorUrl = ref('');
const editingRuleMonitorId = ref(null);
const activeRule = ref({
  channels: ['email'],
  delayMinutes: 0,
  isOnlyBusinessHours: false,
});
const escalationStepsByMonitor = ref({});
let escalationStepSequence = 0;
const error = ref('');
const monitorError = ref('');
const monitorFeedback = ref('');
const isLoading = ref(true);
const isMonitorsLoading = ref(false);
const isCreatingMonitor = ref(false);
const isSavingRule = ref(false);
const isSavingEscalation = ref(false);
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

function toggleRuleEditor(monitorId) {
  if (editingRuleMonitorId.value === monitorId) {
    editingRuleMonitorId.value = null;
    resetActiveRule();
    return;
  }

  editingRuleMonitorId.value = monitorId;
  resetActiveRule();
  ensureEscalationSteps(monitorId);
}

async function saveNotificationRule(monitorId) {
  monitorError.value = '';
  monitorFeedback.value = '';
  isSavingRule.value = true;

  try {
    await saveMonitorNotificationRule(monitorId, activeRule.value);
    editingRuleMonitorId.value = null;
    resetActiveRule();
    monitorFeedback.value = 'Notification rules saved.';
  } catch (exception) {
    monitorError.value = exception.message;
  } finally {
    isSavingRule.value = false;
  }
}

function resetActiveRule() {
  activeRule.value = {
    channels: ['email'],
    delayMinutes: 0,
    isOnlyBusinessHours: false,
  };
}

function ensureEscalationSteps(monitorId) {
  if (!escalationStepsByMonitor.value[monitorId]) {
    escalationStepsByMonitor.value[monitorId] = [];
  }
}

function addLocalEscalationStep(monitorId = editingRuleMonitorId.value) {
  if (monitorId === null) {
    return;
  }

  ensureEscalationSteps(monitorId);
  escalationStepsByMonitor.value[monitorId].push({
    id: `step-${Date.now()}-${escalationStepSequence++}`,
    channel: 'email',
    escalateAfterMinutes: 0,
  });
}

function sortedEscalationSteps(monitorId) {
  return [...(escalationStepsByMonitor.value[monitorId] || [])].sort(
    (firstStep, secondStep) => Number(firstStep.escalateAfterMinutes) - Number(secondStep.escalateAfterMinutes),
  );
}

function removeLocalEscalationStep(monitorId, stepId) {
  escalationStepsByMonitor.value[monitorId] = (escalationStepsByMonitor.value[monitorId] || []).filter(
    (step) => step.id !== stepId,
  );
}

async function saveEscalationSteps(monitorId) {
  monitorError.value = '';
  monitorFeedback.value = '';
  isSavingEscalation.value = true;

  try {
    const steps = sortedEscalationSteps(monitorId).map((step) => ({
      channel: step.channel,
      escalateAfterMinutes: Number(step.escalateAfterMinutes) || 0,
    }));

    await saveMonitorEscalationSteps(monitorId, steps);
    monitorFeedback.value = 'Escalation policy saved.';
  } catch (exception) {
    monitorError.value = exception.message;
  } finally {
    isSavingEscalation.value = false;
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
          </div>

          <button class="secondary-button" type="button" @click="loadMonitorList" :disabled="isMonitorsLoading">
            {{ isMonitorsLoading ? 'Loading...' : 'Reload' }}
          </button>
        </div>

        <form class="monitor-form" @submit.prevent="addMonitor">
          <div class="monitor-url-row">
            <label>
              Website URL
              <input v-model="monitorUrl" type="url" required placeholder="https://example.com" />
            </label>

            <button class="primary-button" type="submit" :disabled="isCreatingMonitor">
              {{ isCreatingMonitor ? 'Adding...' : 'Add monitor' }}
            </button>
          </div>
        </form>

        <p v-if="monitorError" class="notice error">{{ monitorError }}</p>
        <p v-if="monitorFeedback" class="notice success">{{ monitorFeedback }}</p>
        <p v-if="isMonitorsLoading" class="notice">Loading monitors...</p>

        <div v-else-if="monitors.length" class="monitor-table-wrap">
          <table class="monitor-table">
            <thead>
              <tr>
                <th>URL</th>
                <th>Status</th>
                <th>Response Time</th>
                <th>Frequency</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="monitor in monitors" :key="monitor.id">
                <tr>
                  <td>
                    <div class="url-wrapper">
                      <a :href="monitor.url" target="_blank" rel="noreferrer">{{ monitor.url }}</a>
                      <span v-if="monitor.has_incident" class="badge incident-badge">! INCIDENT</span>
                    </div>
                  </td>
                  <td>
                    <span :class="['status-badge', `status-${monitor.status?.toLowerCase()}`]">
                      {{ monitor.status }}
                    </span>
                    <small v-if="monitor.status_code" class="status-code">({{ monitor?.status_code }})</small>
                  </td>
                  <td>
                    <span v-if="monitor.response_time">{{ monitor?.response_time }}ms</span>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td>{{ monitor?.frequency }}s</td>
                  <td class="monitor-actions">
                    <button class="secondary-button compact-button" type="button" @click="toggleRuleEditor(monitor.id)">
                      &#9881;&#65039; Rules
                    </button>
                    <button class="danger-button compact-button" type="button" @click="removeMonitor(monitor.id)" :disabled="removingMonitorId === monitor.id">
                      {{ removingMonitorId === monitor.id ? 'Removing...' : 'Remove' }}
                    </button>
                  </td>
                </tr>

                <tr v-if="editingRuleMonitorId === monitor.id" class="rule-editor-row">
                  <td colspan="5">
                    <form class="rule-editor-form" @submit.prevent="saveNotificationRule(monitor.id)">
                      <fieldset class="rule-fieldset">
                        <legend>Alert channels</legend>

                        <label class="rule-option">
                          <input v-model="activeRule.channels" type="checkbox" value="slack" />
                          <small>Slack</small>
                        </label>

                        <label class="rule-option">
                          <input v-model="activeRule.channels" type="checkbox" value="email" />
                          <small>Email</small>
                        </label>
                      </fieldset>

                      <label class="rule-field">
                        <small>Only alert if down for X minutes</small>
                        <input v-model.number="activeRule.delayMinutes" type="number" min="0" step="1" inputmode="numeric" />
                      </label>

                      <label class="rule-toggle">
                        <input v-model="activeRule.isOnlyBusinessHours" type="checkbox" />
                        <small>Only notify during business hours</small>
                      </label>

                      <button class="primary-button compact-button" type="submit" :disabled="isSavingRule">
                        {{ isSavingRule ? 'Saving...' : 'Save Rules' }}
                      </button>
                    </form>

                    <section class="escalation-panel" aria-label="Escalation Policy Timeline">
                      <div class="escalation-header">
                        <div>
                          <h3>&#128200; Escalation Policy Timeline</h3>
                        </div>

                        <button class="secondary-button compact-button" type="button" @click="addLocalEscalationStep(monitor.id)">
                          + Add Step
                        </button>
                      </div>

                      <form class="escalation-form" @submit.prevent="saveEscalationSteps(monitor.id)">
                        <div v-if="sortedEscalationSteps(monitor.id).length" class="escalation-list">
                          <div v-for="(step, index) in sortedEscalationSteps(monitor.id)" :key="step.id" class="escalation-step">
                            <span class="step-index">Tier {{ index + 1 }}</span>

                            <label class="step-field">
                              <small>Channel</small>
                              <select v-model="step.channel">
                                <option value="slack">Slack</option>
                                <option value="discord">Discord</option>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                              </select>
                            </label>

                            <label class="step-field">
                              <small>Escalate after minutes</small>
                              <input v-model.number="step.escalateAfterMinutes" type="number" min="0" step="1" inputmode="numeric" />
                            </label>

                            <button class="danger-button compact-button" type="button" @click="removeLocalEscalationStep(monitor.id, step.id)">
                              Delete
                            </button>
                          </div>
                        </div>

                        <p v-else class="escalation-empty">No escalation steps configured.</p>

                        <button class="primary-button compact-button escalation-save" type="submit" :disabled="isSavingEscalation">
                          {{ isSavingEscalation ? 'Saving...' : 'Save Escalation Policy' }}
                        </button>
                      </form>
                    </section>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <p v-else class="empty-state">No monitors yet.</p>
      </article>
    </div>
  </section>
</template>

<style scoped>
.monitor-form {
  display: grid;
  gap: 14px;
}

.monitor-url-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 14px;
  align-items: end;
}

.compact-button {
  min-height: 34px;
  padding: 7px 10px;
}

.monitor-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.rule-editor-row td {
  border-bottom: 1px solid #dce3e1;
  background: #f8fbfa;
  padding: 0;
}

.rule-editor-form {
  display: grid;
  grid-template-columns: minmax(180px, 1fr) minmax(180px, 240px) minmax(220px, 1fr) auto;
  gap: 16px;
  align-items: end;
  padding: 16px;
}

.rule-fieldset {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 16px;
  margin: 0;
  padding: 0;
  border: 0;
}

.rule-fieldset legend,
.rule-field span,
.rule-toggle span {
  width: 100%;
  color: #33404a;
  font-size: 0.88rem;
  font-weight: 600;
}

.rule-option,
.rule-toggle {
  display: flex;
  align-items: center;
  gap: 9px;
  color: #33404a;
}

.rule-option input,
.rule-toggle input {
  width: 18px;
  height: 18px;
  accent-color: #116149;
}

.rule-field {
  display: grid;
  gap: 8px;
}

.rule-field input {
  min-width: 0;
}

.escalation-panel {
  display: grid;
  gap: 14px;
  margin: 0 16px 16px;
  padding: 16px;
  border: 1px solid #dce3e1;
  border-radius: 8px;
  background: #ffffff;
}

.escalation-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.escalation-header h3 {
  margin: 0;
  color: #17202a;
  font-size: 1rem;
  line-height: 1.3;
}

.escalation-form {
  display: grid;
  gap: 12px;
}

.escalation-list {
  display: grid;
  gap: 10px;
}

.escalation-step {
  display: grid;
  grid-template-columns: auto minmax(160px, 1fr) minmax(160px, 220px) auto;
  gap: 12px;
  align-items: end;
  padding: 12px;
  border: 1px solid #e1e7e5;
  border-radius: 8px;
  background: #f8fbfa;
}

.step-index {
  align-self: center;
  color: #b15831;
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  white-space: nowrap;
}

.step-field {
  display: grid;
  gap: 8px;
}

.step-field small {
  color: #33404a;
  font-weight: 700;
}

.step-field select,
.step-field input {
  width: 100%;
  border: 1px solid #cbd6d3;
  border-radius: 6px;
  background: #ffffff;
  color: #17202a;
  padding: 10px 12px;
  font: inherit;
  outline: none;
}

.step-field select:focus,
.step-field input:focus {
  border-color: #116149;
  box-shadow: 0 0 0 3px rgba(17, 97, 73, 0.15);
}

.escalation-empty {
  border: 1px dashed #cbd6d3;
  border-radius: 8px;
  color: #56616b;
  margin: 0;
  padding: 14px;
  text-align: center;
}

.escalation-save {
  justify-self: end;
}

@media (max-width: 760px) {
  .monitor-url-row {
    grid-template-columns: 1fr;
  }

  .monitor-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .rule-editor-form {
    grid-template-columns: 1fr;
  }

  .escalation-header {
    align-items: stretch;
    flex-direction: column;
  }

  .escalation-step {
    grid-template-columns: 1fr;
  }

  .escalation-save {
    justify-self: stretch;
  }
}
</style>
