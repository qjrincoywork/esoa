<template>
  <div class="vcloader"></div>
</template>

<script setup>
// No logic needed unless you later add props
</script>

<style scoped>

.vcloader {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: linear-gradient(#c3ab00 0 0),
          linear-gradient(#c3ab00 0 0),
          linear-gradient(#c3ab00 0 0),
          linear-gradient(#c3ab00 0 0),
          linear-gradient(#cb0a2f 0 0),
          linear-gradient(#cb0a2f 0 0),
          linear-gradient(#cb0a2f 0 0),
          linear-gradient(#cb0a2f 0 0),
          #c3ab00;
  background-position: 0 0,100% 0,100% 100%, 0 100%;
  background-repeat: no-repeat;
  animation: vcloader-jdc0p7 2.4s infinite;
}

@keyframes vcloader-jdc0p7 {
  0% {
    background-size: 0 0,0 0,0 0,0 0,0 0,0 0,0 0,0 0;
  }

  40%, 50% {
    background-size: 0 0,0 0,0 0,0 0,51% 51%,51% 51%,51% 51%,51% 51%;
  }

  90%, 100% {
    background-size: 51% 51%,51% 51%,51% 51%,51% 51%,51% 51%,51% 51%,51% 51%,51% 51%;
  }
}
</style>
