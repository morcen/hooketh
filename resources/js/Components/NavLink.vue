<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
});

const classes = computed(() => {
    return props.active
        ? 'inline-flex items-center rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold leading-5 text-white transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-emerald-500 dark:text-slate-950'
        : 'inline-flex items-center rounded-md px-3 py-2 text-sm font-medium leading-5 text-slate-600 transition duration-150 ease-in-out hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white';
});
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
