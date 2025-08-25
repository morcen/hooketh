<template>
    <AppLayout title="Webhook Deliveries">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Webhook Deliveries
                </h2>
                <div class="flex gap-2">
                    <SecondaryButton @click="retryFailedDeliveries" :disabled="retryProcessing">
                        {{ retryProcessing ? 'Processing...' : 'Retry Failed' }}
                    </SecondaryButton>
                    <PrimaryButton @click="refreshDeliveries">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Refresh
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ deliveries.total || 0 }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Successful</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ successfulCount }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 dark:bg-red-900">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Failed</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ failedCount }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900">
                                <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending/Retrying</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ pendingCount }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <InputLabel for="status-filter" value="Status" />
                                <select 
                                    id="status-filter"
                                    v-model="filters.status" 
                                    @change="applyFilters"
                                    class="mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="success">Success</option>
                                    <option value="failed">Failed</option>
                                    <option value="pending">Pending</option>
                                    <option value="retrying">Retrying</option>
                                </select>
                            </div>

                            <div>
                                <InputLabel for="endpoint-filter" value="Endpoint" />
                                <select 
                                    id="endpoint-filter"
                                    v-model="filters.endpoint_id" 
                                    @change="applyFilters"
                                    class="mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full"
                                >
                                    <option value="">All Endpoints</option>
                                    <option v-for="endpoint in endpoints" :key="endpoint.id" :value="endpoint.id">
                                        {{ endpoint.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <InputLabel for="event-filter" value="Event" />
                                <TextInput
                                    id="event-filter"
                                    v-model="filters.event_name"
                                    @keyup.enter="applyFilters"
                                    placeholder="Event name..."
                                    class="mt-1 w-full"
                                />
                            </div>

                            <div class="flex items-end">
                                <SecondaryButton @click="clearFilters" class="w-full">
                                    Clear Filters
                                </SecondaryButton>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deliveries Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Event
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Endpoint
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Response
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Attempts
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Time
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="delivery in deliveries.data" :key="delivery.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ delivery.event?.name || 'Unknown Event' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ delivery.event?.event_type || 'No type' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            {{ delivery.endpoint?.name || 'Unknown Endpoint' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">
                                            {{ delivery.endpoint?.url || 'No URL' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="getStatusClass(delivery.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ delivery.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div v-if="delivery.response_code" class="text-sm text-gray-900 dark:text-white">
                                            {{ delivery.response_code }}
                                        </div>
                                        <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                                            No response
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ delivery.attempt_count }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            {{ formatDate(delivery.delivered_at || delivery.created_at) }}
                                        </div>
                                        <div v-if="delivery.next_retry_at" class="text-xs text-gray-500 dark:text-gray-400">
                                            Retry: {{ formatDate(delivery.next_retry_at) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button 
                                                @click="viewDetails(delivery)"
                                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                            >
                                                Details
                                            </button>
                                            <button 
                                                v-if="delivery.status === 'failed'"
                                                @click="retryDelivery(delivery)"
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                            >
                                                Retry
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-if="!deliveries.data || deliveries.data.length === 0" class="text-center py-12">
                        <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No deliveries found</h3>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">
                            {{ hasFilters ? 'Try adjusting your filters.' : 'No webhook deliveries have been made yet.' }}
                        </p>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="deliveries.last_page > 1" class="mt-6">
                    <Pagination :links="deliveries.links" />
                </div>
            </div>
        </div>

        <!-- Delivery Details Modal -->
        <DialogModal :show="showDetailsModal" @close="showDetailsModal = false" max-width="4xl">
            <template #title>
                Delivery Details
            </template>

            <template #content>
                <div v-if="selectedDelivery" class="space-y-6">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white mb-2">Event Information</h4>
                            <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg space-y-2 text-sm">
                                <div><strong>Name:</strong> {{ selectedDelivery.event?.name }}</div>
                                <div><strong>Type:</strong> {{ selectedDelivery.event?.event_type || 'N/A' }}</div>
                                <div><strong>Created:</strong> {{ formatDateTime(selectedDelivery.event?.created_at) }}</div>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white mb-2">Endpoint Information</h4>
                            <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg space-y-2 text-sm">
                                <div><strong>Name:</strong> {{ selectedDelivery.endpoint?.name }}</div>
                                <div><strong>URL:</strong> {{ selectedDelivery.endpoint?.url }}</div>
                                <div><strong>Active:</strong> {{ selectedDelivery.endpoint?.is_active ? 'Yes' : 'No' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Status -->
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white mb-2">Delivery Status</h4>
                        <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div><strong>Status:</strong> 
                                    <span :class="getStatusClass(selectedDelivery.status)" class="ml-2 px-2 py-1 text-xs font-semibold rounded-full">
                                        {{ selectedDelivery.status }}
                                    </span>
                                </div>
                                <div><strong>Attempts:</strong> {{ selectedDelivery.attempt_count }}</div>
                                <div><strong>Response Code:</strong> {{ selectedDelivery.response_code || 'N/A' }}</div>
                                <div><strong>Delivered At:</strong> {{ formatDateTime(selectedDelivery.delivered_at) }}</div>
                                <div><strong>Next Retry:</strong> {{ formatDateTime(selectedDelivery.next_retry_at) }}</div>
                                <div><strong>Created:</strong> {{ formatDateTime(selectedDelivery.created_at) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Payload -->
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white mb-2">Sent Payload</h4>
                        <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg">
                            <pre class="text-sm text-gray-800 dark:text-gray-200 overflow-x-auto">{{ formatPayload(selectedDelivery.payload) }}</pre>
                        </div>
                    </div>

                    <!-- Response -->
                    <div v-if="selectedDelivery.response_body">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-2">Response Body</h4>
                        <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg">
                            <pre class="text-sm text-gray-800 dark:text-gray-200 overflow-x-auto">{{ selectedDelivery.response_body }}</pre>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="flex justify-between w-full">
                    <div>
                        <PrimaryButton 
                            v-if="selectedDelivery?.status === 'failed'"
                            @click="retryDelivery(selectedDelivery)"
                            class="mr-3"
                        >
                            Retry Delivery
                        </PrimaryButton>
                    </div>
                    <SecondaryButton @click="showDetailsModal = false">
                        Close
                    </SecondaryButton>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import InputLabel from '@/Components/InputLabel.vue'
import DialogModal from '@/Components/DialogModal.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
    deliveries: Object,
    endpoints: Array,
    filters: Object,
})

// State
const showDetailsModal = ref(false)
const selectedDelivery = ref(null)
const retryProcessing = ref(false)
const filters = ref({
    status: props.filters?.status || '',
    endpoint_id: props.filters?.endpoint_id || '',
    event_name: props.filters?.event_name || '',
})

// Computed
const successfulCount = computed(() => {
    return props.deliveries.data?.filter(d => d.status === 'success').length || 0
})

const failedCount = computed(() => {
    return props.deliveries.data?.filter(d => d.status === 'failed').length || 0
})

const pendingCount = computed(() => {
    return props.deliveries.data?.filter(d => ['pending', 'retrying'].includes(d.status)).length || 0
})

const hasFilters = computed(() => {
    return filters.value.status || filters.value.endpoint_id || filters.value.event_name
})

// Methods
function getStatusClass(status) {
    const classes = {
        success: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        retrying: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    }
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
}

function formatDate(dateString) {
    if (!dateString) return 'N/A'
    return new Date(dateString).toLocaleDateString()
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A'
    return new Date(dateString).toLocaleString()
}

function formatPayload(payload) {
    if (!payload) return 'No payload'
    try {
        return typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2)
    } catch (e) {
        return 'Invalid JSON'
    }
}

function viewDetails(delivery) {
    selectedDelivery.value = delivery
    showDetailsModal.value = true
}

function retryDelivery(delivery) {
    router.post(route('deliveries.retry', delivery.id), {}, {
        onSuccess: () => {
            showDetailsModal.value = false
        }
    })
}

function retryFailedDeliveries() {
    if (confirm('Are you sure you want to retry all failed deliveries?')) {
        retryProcessing.value = true
        router.post(route('deliveries.retry-failed'), {}, {
            onFinish: () => {
                retryProcessing.value = false
            }
        })
    }
}

function refreshDeliveries() {
    router.reload({ only: ['deliveries'] })
}

function applyFilters() {
    router.get(route('deliveries'), filters.value, {
        preserveState: true,
        replace: true,
    })
}

function clearFilters() {
    filters.value = {
        status: '',
        endpoint_id: '',
        event_name: '',
    }
    applyFilters()
}
</script>
