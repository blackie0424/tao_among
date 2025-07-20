<template>
  <div class="container mx-auto p-4">
    <div id="index">
      <picture>
        <source media="(min-width: 1025px)" :srcset="headerM" />
        <source media="(min-width: 481px)" :srcset="headerS" />
        <img :src="headerL" class="responsive-img" loading="lazy" />
      </picture>
      <div class="index-content">
        <h1 @click="goFishs" style="cursor: pointer">nivasilan ko a among</h1>
      </div>
    </div>
    <footer class="mt-8 text-center text-gray-500">Copyright © 2025 Chungyueh</footer>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
const headerM = '/images/header-m.png'
const headerS = '/images/header-s.png'
const headerL = '/images/header-l.jpg'

function goFishs() {
  router.visit('/fishs')
}
</script>
