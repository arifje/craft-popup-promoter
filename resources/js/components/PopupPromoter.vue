<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { ModalsContainer, VueFinalModal } from 'vue-final-modal';

const props = defineProps({
  config: {
    type: Object,
    required: true,
  },
});

const popup = ref(null);
const visible = ref(false);
const dismissed = ref(false);
const loading = ref(false);

const variant = computed(() => popup.value?.variant || 'center');
const titleId = computed(() => (popup.value ? `craft-popup-promoter-title-${popup.value.id}` : null));
const modalClass = computed(() => ['cpp-modal', `cpp-modal--${variant.value}`]);
const contentClass = computed(() => ['cpp-content', `cpp-content--${variant.value}`]);
const hasImage = computed(() => Boolean(popup.value?.image?.url));

onMounted(async () => {
  if (loading.value) {
    return;
  }

  loading.value = true;

  try {
    const response = await fetch(props.config.endpoint, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
    });
    const data = await response.json();

    if (response.ok && data?.popup) {
      await showPopup(data.popup);
    }
  } catch (error) {
    document.dispatchEvent(new CustomEvent('craft-popup-promoter:error', { detail: error }));
  } finally {
    loading.value = false;
  }
});

async function showPopup(nextPopup) {
  const delayMs = Math.max(0, Number(nextPopup.delayMs) || 0);

  popup.value = nextPopup;

  if (delayMs > 0) {
    await new Promise((resolve) => {
      window.setTimeout(resolve, delayMs);
    });
  }

  visible.value = true;
  document.dispatchEvent(new CustomEvent('craft-popup-promoter:shown', { detail: nextPopup }));
}

watch(visible, (isVisible) => {
  if (!isVisible && popup.value) {
    rememberDismissal();
  }
});

function closeModal() {
  rememberDismissal();
  visible.value = false;
}

function rememberDismissal() {
  if (dismissed.value || !popup.value?.cookieName) {
    return;
  }

  dismissed.value = true;

  const cookieParts = [
    `${encodeURIComponent(popup.value.cookieName)}=1`,
    'path=/',
    'SameSite=Lax',
  ];
  const duration = Number(popup.value.cookieDurationDays);

  if (duration > 0) {
    const expires = new Date();
    expires.setDate(expires.getDate() + duration);
    cookieParts.push(`expires=${expires.toUTCString()}`);
  }

  if (window.location.protocol === 'https:') {
    cookieParts.push('Secure');
  }

  document.cookie = cookieParts.join('; ');
  document.dispatchEvent(new CustomEvent('craft-popup-promoter:dismissed', { detail: popup.value }));
}
</script>

<template>
  <VueFinalModal
    v-if="popup"
    v-model="visible"
    :class="modalClass"
    :content-class="contentClass"
    overlay-class="cpp-overlay"
    :click-to-close="popup.closeOnBackdrop"
    :esc-to-close="popup.closeOnEsc"
  >
    <article class="cpp-card" role="dialog" aria-modal="true" :aria-labelledby="titleId">
      <button class="cpp-close" type="button" aria-label="Close popup" @click="closeModal">
        &times;
      </button>

      <div v-if="hasImage" class="cpp-media">
        <img :src="popup.image.url" :alt="popup.image.alt || ''">
      </div>

      <div class="cpp-main">
        <p class="cpp-kicker">Promoted</p>
        <h2 :id="titleId" class="cpp-title">{{ popup.title }}</h2>
        <p v-if="popup.description" class="cpp-description">{{ popup.description }}</p>
        <a
          v-if="popup.cta"
          class="cpp-cta"
          :href="popup.cta.url"
          :target="popup.cta.target"
          :rel="popup.cta.target === '_blank' ? 'noopener noreferrer' : null"
          @click="rememberDismissal"
        >
          {{ popup.cta.label }}
        </a>
      </div>
    </article>
  </VueFinalModal>

  <ModalsContainer />
</template>
