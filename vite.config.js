import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  // 使用相对资源路径，同时兼容 GitHub Pages 子目录和后续的独立 CDN 域名。
  base: './',
  plugins: [vue()],
})
