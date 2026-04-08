<template>
  <v-container fluid class="pa-4 d-flex justify-center">
    <v-card max-width="560" width="100%" class="mt-6">
      <v-card-text class="text-center pa-8">
        <v-icon size="64" color="primary" class="mb-4">mdi-clock-check-outline</v-icon>
        <div class="text-h5 font-weight-bold mb-1">Task Tracker</div>
        <div class="text-caption text-medium-emphasis mb-6">
          Gestione attività con integrazione Mantis
        </div>

        <v-divider class="mb-6" />

        <div class="text-body-1 mb-1">Realizzato da</div>
        <div class="text-h6 font-weight-bold mb-6">Paolo Conzon</div>

        <v-chip
          color="primary"
          variant="tonal"
          prepend-icon="mdi-github"
          href="https://github.com/paoloconzon/taskTracker"
          target="_blank"
          class="mb-6"
        >
          github.com/paoloconzon/taskTracker
        </v-chip>

        <v-divider class="mb-6" />

        <div class="text-caption text-medium-emphasis">
          Distribuito con licenza
        </div>
        <div class="text-body-2 font-weight-bold mb-2">MIT License</div>
        <div class="text-caption text-medium-emphasis">
          È consentito l'uso, la copia, la modifica e la distribuzione libera del software,
          a condizione che venga mantenuto questo avviso di copyright.
        </div>
      </v-card-text>
    </v-card>
  </v-container>
</template>
