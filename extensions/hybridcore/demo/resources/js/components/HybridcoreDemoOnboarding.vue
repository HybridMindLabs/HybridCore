<script setup lang="ts">
import { Puzzle } from '@lucide/vue';
</script>

<template>
    <div class="flex flex-col items-center text-center gap-2 py-6">
        <Puzzle :size="28" :stroke-width="1.5" class="text-zinc-400" />
        <p class="text-sm text-zinc-400">onboardingSteps() example — gated by the demo.show_onboarding_step setting.</p>
    </div>
</template>
