<template>
  <div class="min-h-screen bg-slate-950 flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Efecto de gradiente de fondo -->
    <div class="absolute inset-0 bg-gradient-to-br from-sky-900/20 via-slate-950 to-slate-900 pointer-events-none"></div>
    <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10">
      <slot />
    </div>

    <!-- Contenedor Global de Notificaciones Toast y Modal de Confirmación -->
    <ToastContainer />
    <ConfirmModal />
  </div>
</template>
