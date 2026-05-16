<template>
    <AppLayout title="Dashboard">
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="app-label">Operations</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">Webhook dashboard</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Monitor delivery health, endpoint coverage, and recent webhook activity.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('events')" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        Manage events
                    </Link>
                    <Link :href="route('endpoints')" class="inline-flex items-center justify-center rounded-md bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400">
                        Add endpoint
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="app-stat">
                        <p class="app-label">Endpoints</p>
                        <div class="mt-3 flex items-end justify-between">
                            <p class="text-3xl font-bold text-slate-950 dark:text-white">{{ stats.endpoints }}</p>
                            <span class="rounded-md bg-slate-100 p-2 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v7.5A2.25 2.25 0 005.25 18h8.25m3-12L21 10.5m0 0L16.5 15M21 10.5H9" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Configured delivery targets</p>
                    </div>

                    <div class="app-stat">
                        <p class="app-label">Events</p>
                        <div class="mt-3 flex items-end justify-between">
                            <p class="text-3xl font-bold text-slate-950 dark:text-white">{{ stats.events }}</p>
                            <span class="rounded-md bg-teal-50 p-2 text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8.25 6.75h7.5M8.25 12h7.5m-7.5 5.25h4.5M5.25 4.5h13.5A1.5 1.5 0 0120.25 6v12a1.5 1.5 0 01-1.5 1.5H5.25A1.5 1.5 0 013.75 18V6a1.5 1.5 0 011.5-1.5z" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Trigger definitions</p>
                    </div>

                    <div class="app-stat">
                        <p class="app-label">Deliveries</p>
                        <div class="mt-3 flex items-end justify-between">
                            <p class="text-3xl font-bold text-slate-950 dark:text-white">{{ stats.total_deliveries }}</p>
                            <span class="rounded-md bg-indigo-50 p-2 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 12L3.75 5.25 20.25 12 3.75 18.75 6 12zm0 0h7.5" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Total attempts created</p>
                    </div>

                    <div class="app-stat">
                        <p class="app-label">Success rate</p>
                        <div class="mt-3 flex items-end justify-between">
                            <p class="text-3xl font-bold text-slate-950 dark:text-white">{{ successRate }}%</p>
                            <span class="rounded-md bg-emerald-50 p-2 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75l2.25 2.25L15.75 9M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ stats.successful_deliveries }} successful deliveries</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="app-panel p-6 lg:col-span-2">
                        <div class="flex items-center justify-between gap-4 border-b border-slate-200 pb-4 dark:border-slate-800">
                            <div>
                                <p class="app-label">Activity</p>
                                <h2 class="mt-1 text-lg font-semibold text-slate-950 dark:text-white">Recent deliveries</h2>
                            </div>
                            <Link :href="route('deliveries')" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200">
                                View all
                            </Link>
                        </div>

                        <div v-if="recentDeliveries.length" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                                <thead>
                                    <tr>
                                        <th class="py-3 pr-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Event</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Endpoint</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Attempts</th>
                                        <th class="py-3 pl-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Time</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="delivery in recentDeliveries" :key="delivery.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/70">
                                        <td class="py-4 pr-4 text-sm font-semibold text-slate-900 dark:text-white">{{ delivery.event.name }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300">{{ delivery.endpoint.name }}</td>
                                        <td class="px-4 py-4">
                                            <span :class="getStatusClass(delivery.status)" class="app-status">
                                                {{ delivery.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300">{{ delivery.attempt_count }}</td>
                                        <td class="py-4 pl-4 text-right text-sm text-slate-500 dark:text-slate-400">{{ formatDate(delivery.delivered_at || delivery.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="py-12 text-center">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No deliveries yet</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Trigger an event to see delivery attempts here.</p>
                        </div>
                    </div>

                    <aside class="app-panel p-6">
                        <p class="app-label">Readiness</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-950 dark:text-white">Setup checklist</h2>
                        <div class="mt-5 space-y-4">
                            <div class="flex gap-3">
                                <span :class="stats.endpoints ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'" class="mt-0.5 flex size-6 items-center justify-center rounded-full text-xs font-bold">1</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Create endpoint targets</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Add URLs that should receive signed webhooks.</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <span :class="stats.events ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'" class="mt-0.5 flex size-6 items-center justify-center rounded-full text-xs font-bold">2</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Define events</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Model payloads and validation rules before sending.</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <span :class="stats.total_deliveries ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'" class="mt-0.5 flex size-6 items-center justify-center rounded-full text-xs font-bold">3</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Send and inspect</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Review response codes, retries, and payload logs.</p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    stats: Object,
    recentDeliveries: Array,
})

const successRate = computed(() => {
    if (!props.stats.total_deliveries) return 0

    return Math.round((props.stats.successful_deliveries / props.stats.total_deliveries) * 100)
})

function getStatusClass(status) {
    const classes = {
        success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
        failed: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        retrying: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    }
    return classes[status] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
}

function formatDate(dateString) {
    if (!dateString) return 'N/A'
    return new Date(dateString).toLocaleString()
}
</script>
