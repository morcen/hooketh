<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    active: Boolean,
    href: String,
    as: String,
});

const classes = computed(() => {
    return props.active
        ? 'block w-full border-l-4 border-emerald-500 bg-emerald-50 py-2 pe-4 ps-3 text-start text-base font-semibold text-emerald-800 transition duration-150 ease-in-out focus:outline-none dark:bg-emerald-950 dark:text-emerald-200'
        : 'block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-slate-600 transition duration-150 ease-in-out hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white';
});
</script>

<template>
    <div>
        <button v-if="as == 'button'" :class="classes" class="w-full text-start">
            <slot />
        </button>

        <a v-else-if="as == 'a'" :class="classes" class="w-full text-start" :href="href">
            <slot />
        </a>

        <Link v-else :href="href" :class="classes">
            <slot />
        </Link>
    </div>
</template>
