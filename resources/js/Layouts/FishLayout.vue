<template>
  <div class="min-h-screen bg-stone-50 text-stone-800 font-sans pb-20 md:pb-0">
    <!-- Hero Section -->
    <div class="bg-white shadow-sm border-b border-stone-200 relative overflow-hidden">
        <!-- Decoration / Texture Background (Optional) -->
        <div class="absolute inset-0 opacity-5 pointer-events-none bg-[url('/images/wave-pattern.png')]"></div>

        <div class="container mx-auto px-4 py-6 md:py-12">
            <div class="flex flex-col md:flex-row items-center md:items-start gap-6 md:gap-12">
                <!-- Left: Fish Image (Hero) -->
                <div class="w-full md:w-5/12 lg:w-4/12 flex-shrink-0 relative">
                    <div class="aspect-[4/3] w-full rounded-2xl overflow-hidden bg-stone-100 shadow-inner border border-stone-100">
                        <LazyImage
                            :src="fish.display_image_url || fish.image_url"
                            :alt="fish.name"
                            wrapperClass="w-full h-full"
                            imgClass="w-full h-full object-contain p-2 md:p-4 hover:scale-105 transition-transform duration-700 ease-out"
                        />
                    </div>
                </div>

                <!-- Right: Header Info -->
                <div class="w-full md:w-7/12 lg:w-8/12 flex flex-col items-center md:items-start text-center md:text-left space-y-4">

                    <!-- Cultural Name (Tao Name) -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-center md:justify-start gap-3">
                            <h1 class="text-4xl md:text-5xl lg:text-6xl font-serif font-bold text-stone-900 tracking-wide">
                                {{ fish.name }}
                            </h1>
                            <!-- Audio Button -->
                            <button
                                v-if="fish.audio_url || (fish.audios && fish.audios.length > 0)"
                                @click="playAudio"
                                class="flex-shrink-0 w-10 h-10 md:w-12 md:h-12 rounded-full bg-stone-100 hover:bg-stone-200 text-stone-600 flex items-center justify-center transition-colors focus:outline-none focus:ring-2 focus:ring-stone-400"
                                aria-label="Play Pronunciation"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 md:h-6 md:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                </svg>
                            </button>
                        </div>

                        <!-- Scientific / Common Names (Subtitle) -->
                        <div class="text-lg md:text-xl text-stone-500 font-light flex flex-col md:flex-row gap-2 md:gap-4">
                           <span v-if="fish.scientific_name" class="italic">{{ fish.scientific_name }}</span>
                           <span v-if="fish.common_name" class="hidden md:inline text-stone-300">|</span>
                           <span v-if="fish.common_name">{{ fish.common_name }}</span>
                        </div>
                    </div>

                    <!-- Badges / Quick Info -->
                    <div class="flex flex-wrap justify-center md:justify-start gap-2 pt-2">
                         <!-- Example Badges (You can bind these to real data if available in 'fish' object) -->
                        <span v-if="fish.family" class="px-3 py-1 rounded-full bg-stone-100 text-stone-600 text-sm font-medium border border-stone-200">
                           {{ fish.family }}
                        </span>
                        <!-- Add more meta badges here -->
                    </div>

                    <!-- Desktop Navigation (Tabs) - Optional enhancement for desktop users -->
                    <div class="hidden md:flex w-full pt-8 border-b border-transparent">
                        <nav class="flex space-x-1 bg-stone-100/50 p-1 rounded-xl">
                            <Link
                                v-for="tab in tabs"
                                :key="tab.url"
                                :href="tab.url"
                                :class="[
                                    'px-5 py-2.5 rounded-lg text-sm font-medium transition-all duration-200',
                                    isCurrent(tab.name)
                                        ? 'bg-white text-stone-900 shadow-sm'
                                        : 'text-stone-500 hover:text-stone-700 hover:bg-stone-200/50'
                                ]"
                            >
                                {{ tab.label }}
                            </Link>
                        </nav>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Slot -->
    <main class="container mx-auto px-4 py-8 md:py-12 min-h-[50vh]">
        <slot />
    </main>

    <!-- Mobile Bottom Navigation (Preserving original functionality for mobile) -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 z-50">
        <BottomNavBar
            :fishBasicInfo="`/fish/${fish.id}`"
            :tribalKnowledge="`/fish/${fish.id}/tribal-classifications`"
            :captureRecords="`/fish/${fish.id}/capture-records`"
            :knowledge="`/fish/${fish.id}/knowledge`"
            :audioList="`/fish/${fish.id}/audio-list`"
            :currentPage="currentPage"
        />
    </div>

    <!-- Audio Player (Hidden) -->
    <audio ref="audioPlayer" class="hidden"></audio>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import LazyImage from '@/Components/LazyImage.vue'
import BottomNavBar from '@/Components/Global/BottomNavBar.vue'

// With Persistent Layouts, props are not passed directly to the layout component.
// Instead, we access shared data via usePage().props.
// We expect 'fish' to be present in the page props for all these pages.
const page = usePage()
const fish = computed(() => page.props.fish || {})

const audioPlayer = ref(null)

// Determine current page for highlighting
const currentPage = computed(() => {
    const url = page.url
    if (url.endsWith('/tribal-classifications')) return 'tribalKnowledge'
    if (url.endsWith('/capture-records')) return 'captureRecords'
    if (url.endsWith('/knowledge')) return 'knowledge'
    if (url.endsWith('/audio-list')) return 'audioList'
    return 'fishBasicInfo'
})

// Tab configuration
const tabs = computed(() => [
    { name: 'fishBasicInfo', label: '基本資料', url: `/fish/${fish.value.id}` },
    { name: 'tribalKnowledge', label: '地方知識', url: `/fish/${fish.value.id}/tribal-classifications` },
    { name: 'captureRecords', label: '捕獲紀錄', url: `/fish/${fish.value.id}/capture-records` },
    { name: 'knowledge', label: '進階知識', url: `/fish/${fish.value.id}/knowledge` },
    { name: 'audioList', label: '語音存檔', url: `/fish/${fish.value.id}/audio-list` },
])

const isCurrent = (name) => currentPage.value === name

const playAudio = () => {
    if (!audioPlayer.value) return

    // Priority: 1. Direct audio_url on fish, 2. First item in audios array
    let src = fish.value.audio_url
    if (!src && fish.value.audios && fish.value.audios.length > 0) {
        src = fish.value.audios[0].url
    }

    if (src) {
        audioPlayer.value.src = src
        audioPlayer.value.play().catch(e => console.error("Audio play error:", e))
    } else {
        alert('尚無語音檔')
    }
}
</script>

<style scoped>
/* Add any specific transitions or custom font adjustments here */
</style>
