import { clearSession, getToken } from './auth';

export class ApiError extends Error {
  constructor(message, status, payload = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload;
  }
}

function getApiBaseUrl() {
  return import.meta.env.VITE_API_BASE_URL || window.location.origin;
}

async function request(path, options = {}) {
  const token = getToken();
  const baseUrl = getApiBaseUrl();
  
  const response = await fetch(`${baseUrl}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  });

  const payload = await parseJson(response);

  if (!response.ok) {
    if (response.status === 401 && path !== '/dashboard') {
      clearSession();
    }

    throw new ApiError(payload?.error || payload?.detail || payload?.message || 'Request failed.', response.status, payload);
  }

  return payload;
}

async function parseJson(response) {
  const text = await response.text();

  if (!text) {
    return null;
  }

  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

export function registerUser(credentials) {
  return request('/api/register', {
    method: 'POST',
    body: JSON.stringify(credentials),
  });
}

export function loginUser(credentials) {
  return request('/api/login_check', {
    method: 'POST',
    body: JSON.stringify(credentials),
  });
}

export function loadDashboard() {
  return request('/api/dashboard');
}

export function loadMonitors() {
  return request('/api/monitors');
}

export function createMonitor(url) {
  return request('/api/monitors', {
    method: 'POST',
    body: JSON.stringify({ url }),
  });
}

export function saveMonitorNotificationRule(monitorId, notificationRule, hasExistingRule = false) {
  return request("/api/monitors/" + monitorId + "/notification-rule", {
    method: hasExistingRule ? 'PUT' : 'POST',
    body: JSON.stringify({
      channels: notificationRule.channels,
      delayMinutes: notificationRule.delayMinutes,
      isOnlyBusinessHours: notificationRule.isOnlyBusinessHours,
    }),
  });
}

export function saveMonitorEscalationSteps(monitorId, steps) {
  return request("/api/monitors/"+ monitorId +"/escalation-steps", {
    method: 'POST',
    body: JSON.stringify({
      steps,
    }),
  });
}

export function deleteMonitorEscalationStep(monitorId, stepId) {
  return request("/api/monitors/" + monitorId + "/escalation-steps/" + stepId, {
    method: 'DELETE',
  });
}

export function deleteMonitor(id) {
  return request("/api/monitors/"+id, {
    method: 'DELETE',
  });
}
