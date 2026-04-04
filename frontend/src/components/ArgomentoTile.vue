<template>
  <v-card
    :style="{
      borderTop: `4px solid ${argomento.colore || '#607D8B'}`,
      cursor: 'pointer',
      userSelect: 'none',
      opacity: argomento.se_chiuso ? 0.5 : 1,
    }"
    class="tile-card"
    :class="{ 'tile-pausa': argomento.se_pausa }"
    elevation="2"
    rounded="lg"
    @click="handleClick"
    @dblclick.prevent="handleDblClick"
  >
    <v-card-text class="tile-content text-center pa-3">
      <v-icon
        :color="argomento.colore || '#607D8B'"
        size="36"
        class="mb-2"
      >
        {{ argomento.icona || 'mdi-folder' }}
      </v-icon>
      <div class="text-subtitle-2 font-weight-bold text-truncate">
        {{ argomento.nome }}
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
const props = defineProps({
  argomento: { type: Object, required: true }
})
const emit = defineEmits(['click-single', 'click-double'])

let clickTimer = null

function handleClick() {
  if (clickTimer) return
  clickTimer = setTimeout(() => {
    clickTimer = null
    emit('click-single', props.argomento)
  }, 220)
}

function handleDblClick() {
  if (clickTimer) { clearTimeout(clickTimer); clickTimer = null }
  emit('click-double', props.argomento)
}
</script>

<style scoped>
.tile-card {
  transition: transform .15s, box-shadow .15s;
}
.tile-content {
  height: 96px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.tile-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(0,0,0,.15) !important;
}
.tile-pausa {
  background: repeating-linear-gradient(
    45deg,
    transparent,
    transparent 6px,
    rgba(0,0,0,.03) 6px,
    rgba(0,0,0,.03) 12px
  );
}
</style>
