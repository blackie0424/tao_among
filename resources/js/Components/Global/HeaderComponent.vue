<template>
  <header>
    <picture>
      <source media="(min-width: 1025px)" :srcset="headerM" />
      <source media="(min-width: 481px)" :srcset="headerS" />
      <img :src="headerL" class="responsive-img" loading="lazy" />
    </picture>
    <div class="header-content">
      <h1>nivasilan ko a among</h1>
    </div>
  </header>
</template>

<script setup>
const headerM = '/images/header-m.png'
const headerS = '/images/header-s.png'
const headerL = '/images/header-l.png'
</script>
