import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

const backendUrl = process.env.VITE_BACKEND_URL || 'http://localhost:8080';
const tenantAwareProxy = {
  target: backendUrl,
  changeOrigin: false,
  configure: (proxy) => {
    proxy.on('proxyReq', (proxyReq, req) => {
      const incomingHost = req.headers.host;

      if (incomingHost) {
        proxyReq.setHeader('host', incomingHost);
        proxyReq.setHeader('x-forwarded-host', incomingHost);
      }
    });
  },
};

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    proxy: {
      '/api': tenantAwareProxy
    },
  },
});
