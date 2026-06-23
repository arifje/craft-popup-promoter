import { createApp } from 'vue';
import { createVfm } from 'vue-final-modal';
import 'vue-final-modal/style.css';
import '../css/popup-promoter.css';
import PopupPromoter from './components/PopupPromoter.vue';

function bootPopupPromoter() {
  const config = window.CraftPopupPromoterConfig;

  if (!config || !config.endpoint || document.getElementById('craft-popup-promoter-root')) {
    return;
  }

  const mount = document.createElement('div');
  mount.id = 'craft-popup-promoter-root';
  document.body.appendChild(mount);

  const app = createApp(PopupPromoter, { config });
  app.use(createVfm());
  app.mount(mount);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootPopupPromoter, { once: true });
} else {
  bootPopupPromoter();
}
