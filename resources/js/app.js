import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import './bootstrap'
import AppLayout from './Layouts/AppLayout.vue'

const pages = import.meta.glob('./Pages/**/*.vue')

createInertiaApp({
  resolve: (name) => {
    const page = pages[`./Pages/${name}.vue`]().then((module) => {
      // 為所有頁面設定預設 layout（除非頁面自己定義了 layout）
      module.default.layout = module.default.layout || AppLayout
      return module.default
    })
    return page
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})
