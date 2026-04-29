import { clearSession, getToken } from './auth';

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || '';

export class ApiError extends Error {
  constructor(message, status, payload = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload;
  }
}

async function request(path, options = {}) {
  const token = getToken();
  const response = await fetch(`${apiBaseUrl}${path}`, {
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
    if (response.status === 401) {
      clearSession();
    }

    throw new ApiError(payload?.error || payload?.message || 'Request failed.', response.status, payload);
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
  return request('/dashboard');
}
