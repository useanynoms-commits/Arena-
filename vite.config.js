import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import generateContent from './api/generate-content.js';

function geminiContentApi(apiKey) {
  return {
    name: 'gemini-content-api',
    configureServer(server) {
      server.middlewares.use('/api/generate-content', (req, res) => generateContent(req, res, apiKey));
    },
  };
}

export default defineConfig(({ mode }) => {
  // Loaded only in the Node process; GEMINI_API_KEY is never exposed to the browser bundle.
  const env = loadEnv(mode, process.cwd(), '');
  return {
    plugins: [react(), geminiContentApi(env.GEMINI_API_KEY)],
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
