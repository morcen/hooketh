<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    as: String,
});
</script>

<template>
    <div>
        <button v-if="as == 'button'" type="submit" class="block w-full px-4 py-2 text-start text-sm leading-5 text-slate-100 transition duration-150 ease-in-out hover:bg-slate-800 focus:bg-slate-800 focus:outline-none">
            <slot />
        </button>

        <a v-else-if="as =='a'" :href="href" class="block px-4 py-2 text-sm leading-5 text-slate-100 transition duration-150 ease-in-out hover:bg-slate-800 focus:bg-slate-800 focus:outline-none">
            <slot />
        </a>

        <Link v-else-if="href" :href="href" class="block px-4 py-2 text-sm leading-5 text-slate-100 transition duration-150 ease-in-out hover:bg-slate-800 focus:bg-slate-800 focus:outline-none">
            <slot />
        </Link>

        <button v-else type="button" class="block w-full px-4 py-2 text-start text-sm leading-5 text-slate-100 transition duration-150 ease-in-out hover:bg-slate-800 focus:bg-slate-800 focus:outline-none">
            <slot />
        </button>
    </div>
</template>
