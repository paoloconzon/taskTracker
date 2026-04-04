<template>
  <div v-if="task" class="d-flex align-center gap-2">
    <v-chip
      :color="task.argomento_colore || 'primary'"
      variant="elevated"
      size="small"
      :prepend-icon="task.argomento_icona || 'mdi-clock'"
      class="cursor-pointer"
      @click="$emit('click')"
    >
      {{ task.argomento_nome }} — {{ durataStr }}
    </v-chip>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useTaskStore }    from '../stores/task.js'
import dayjs               from 'dayjs'

defineEmits(['click'])

const store = useTaskStore()
const task  = computed(() => store.taskAttivo)

const durataStr = computed(() => {
  // Forza recompute ogni secondo tramite timerTick
  store.timerTick
  if (!task.value?.log_inizio) return ''
  const s = task.value.log_inizio.replace(' ', '')
  const dt = dayjs(s, 'YYYYMMDDHHmmss')
  const sec = dayjs().diff(dt, 'second')
  return store.formatDurata(sec)
})
</script>
