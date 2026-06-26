import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import axios from 'axios';
import '../css/app.css';

// Axios butuh CSRF token Laravel di setiap request POST/PUT/DELETE
// (dipakai oleh ChatRoom.vue saat mengirim pesan chat).
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const csrfToken = document.head.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}
window.axios = axios;

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
    return pages[`./Pages/${name}.vue`];
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) });
    app.use(plugin);
    app.config.globalProperties.route = window.route; // daftarkan route() Ziggy ke instance Vue
    app.mount(el);
  },
});