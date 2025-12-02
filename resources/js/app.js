import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { injectSpeedInsights } from '@vercel/speed-insights'
import './bootstrap'

const pages = import.meta.glob('./Pages/**/*.vue')

createInertiaApp({
  resolve: (name) => {
    return pages[`./Pages/${name}.vue`]().then((module) => module.default)
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)

    // Inject Vercel Speed Insights (client-side only)
    injectSpeedInsights()
  },
})
