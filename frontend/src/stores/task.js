// stores/task.js
import { defineStore }   from 'pinia'
import { ref, computed } from 'vue'
import dayjs             from 'dayjs'
import duration          from 'dayjs/plugin/duration'
dayjs.extend(duration)

export const useTaskStore = defineStore('task', () => {
  const taskAttivo  = ref(null)   // oggetto task dal server
  const timerTick   = ref(0)      // incrementato ogni secondo
  const panelOpen   = ref(false)  // visibilità dialog TaskAttivoPanel
  let   _interval   = null

  function setTaskAttivo(task) {
    taskAttivo.value = task
    if (task && task.log_inizio) {
      startTimer()
    } else {
      stopTimer()
    }
  }

  function clearTask() {
    taskAttivo.value = null
    stopTimer()
  }

  function startTimer() {
    stopTimer()
    _interval = setInterval(() => { timerTick.value++ }, 1000)
  }

  function stopTimer() {
    if (_interval) { clearInterval(_interval); _interval = null }
  }

  // Secondi trascorsi dall'inizio del log corrente
  // timerTick viene letto per forzare il recompute ogni secondo
  const secondiCorrente = computed(() => {
    void timerTick.value  // dipendenza reattiva
    if (!taskAttivo.value?.log_inizio) return 0
    const s  = taskAttivo.value.log_inizio.replace(' ', '')
    const dt = dayjs(s, 'YYYYMMDDHHmmss')
    return dayjs().diff(dt, 'second')
  })

  // Formatta secondi in "1h 23m 45s"
  function formatDurata(sec) {
    if (!sec || sec < 0) return '0s'
    const h = Math.floor(sec / 3600)
    const m = Math.floor((sec % 3600) / 60)
    const s = sec % 60
    if (h > 0) return `${h}h ${m}m`
    if (m > 0) return `${m}m ${s}s`
    return `${s}s`
  }

  return { taskAttivo, timerTick, panelOpen, secondiCorrente, setTaskAttivo, clearTask, formatDurata }
})
