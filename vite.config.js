import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import generateContent from './api/generate-content.js';
import catalogSearch from './api/catalog-search.js';

function storeApis(apiKey) {
  return {
    name: 'store-apis',
    configureServer(server) {
      server.middlewares.use('/api/generate-content', (req, res) => generateContent(req, res, apiKey));
      server.middlewares.use('/api/catalog-search', (req, res) => catalogSearch(req, res));
    },
  };
}

export default defineConfig(({ mode }) => {
  // Loaded only in the Node process; GEMINI_API_KEY is never exposed to the browser bundle.
  const env = loadEnv(mode, process.cwd(), '');
  return {
    plugins: [react(), storeApis(env.GEMINI_API_KEY)],
    server: {
      host: '0.0.0.0',
      allowedHosts: ['.e2b.app'],
    },
    preview: {
      host: '0.0.0.0',
      allowedHosts: ['.e2b.app'],
    },
  };
});
