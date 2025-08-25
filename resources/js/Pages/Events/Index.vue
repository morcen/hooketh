<template>
    <AppLayout title="Manage Events">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Manage Events
                </h2>
                <PrimaryButton @click="showCreateModal = true">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Create Event
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Search and Filters -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex flex-col md:flex-row gap-4">
                            <div class="flex-1">
                                <TextInput
                                    v-model="search"
                                    placeholder="Search events..."
                                    class="w-full"
                                />
                            </div>
                            <div class="flex gap-2">
                                <select 
                                    v-model="typeFilter" 
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                >
                                    <option value="">All Types</option>
                                    <option v-for="type in eventTypes" :key="type" :value="type">
                                        {{ type }}
                                    </option>
                                </select>
                                <SecondaryButton @click="clearFilters">
                                    Clear Filters
                                </SecondaryButton>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Events List -->
                <div class="space-y-4">
                    <div 
                        v-for="event in filteredEvents" 
                        :key="event.id"
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg"
                    >
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-3">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ event.name }}
                                        </h3>
                                        <span 
                                            v-if="event.event_type"
                                            class="ml-3 px-3 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 rounded-full"
                                        >
                                            {{ event.event_type }}
                                        </span>
                                    </div>
                                    
                                    <p v-if="event.description" class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        {{ event.description }}
                                    </p>
                                    
                                    <!-- Endpoints -->
                                    <div class="mb-4">
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Subscribed Endpoints ({{ event.endpoints?.length || 0 }})
                                        </h4>
                                        <div class="flex flex-wrap gap-2">
                                            <span 
                                                v-for="endpoint in event.endpoints" 
                                                :key="endpoint.id"
                                                :class="endpoint.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'"
                                                class="px-2 py-1 text-xs font-medium rounded"
                                            >
                                                {{ endpoint.name }}
                                            </span>
                                            <span 
                                                v-if="!event.endpoints || event.endpoints.length === 0"
                                                class="text-xs text-gray-500 dark:text-gray-400 italic"
                                            >
                                                No endpoints subscribed
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Payload Preview -->
                                    <div v-if="event.payload" class="mb-4">
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Payload Sample
                                        </h4>
                                        <div class="bg-gray-100 dark:bg-gray-700 rounded p-3 text-xs">
                                            <pre class="text-gray-800 dark:text-gray-200 overflow-x-auto">{{ formatPayload(event.payload) }}</pre>
                                        </div>
                                    </div>
                                </div>

                                <div class="ml-4">
                                    <Dropdown>
                                        <template #trigger>
                                            <button class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <DropdownLink @click="editEvent(event)">
                                                Edit Event
                                            </DropdownLink>
                                            <DropdownLink @click="manageEndpoints(event)">
                                                Manage Endpoints
                                            </DropdownLink>
                                            <DropdownLink @click="triggerEvent(event)">
                                                Trigger Event
                                            </DropdownLink>
                                            <DropdownLink @click="viewDeliveries(event)" class="border-t border-gray-100 dark:border-gray-700">
                                                View Deliveries
                                            </DropdownLink>
                                            <DropdownLink @click="duplicateEvent(event)">
                                                Duplicate
                                            </DropdownLink>
                                            <DropdownLink @click="deleteEvent(event)" class="text-red-600">
                                                Delete
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                            
                            <!-- Stats -->
                            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <span>Created: {{ formatDate(event.created_at) }}</span>
                                <span>Last updated: {{ formatDate(event.updated_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="filteredEvents.length === 0" class="text-center py-12">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-12">
                        <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2h4a1 1 0 110 2h-1v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6H3a1 1 0 010-2h4z"/>
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No events found</h3>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">
                            {{ search || typeFilter ? 'Try adjusting your search or filters.' : 'Get started by creating your first webhook event.' }}
                        </p>
                        <PrimaryButton @click="showCreateModal = true" class="mt-4" v-if="!search && !typeFilter">
                            Create Event
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="events.last_page > 1" class="mt-6">
                    <Pagination :links="events.links" />
                </div>
            </div>
        </div>

        <!-- Create/Edit Event Modal -->
        <DialogModal :show="showCreateModal || showEditModal" @close="closeModal" max-width="3xl">
            <template #title>
                {{ editingEvent ? 'Edit Event' : 'Create New Event' }}
            </template>

            <template #content>
                <form @submit.prevent="saveEvent" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel for="name" value="Event Name" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                                autofocus
                                placeholder="e.g., user.created"
                            />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="event_type" value="Event Type" />
                            <TextInput
                                id="event_type"
                                v-model="form.event_type"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="e.g., user.created, order.updated"
                            />
                            <InputError :message="form.errors.event_type" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm mt-1 block w-full"
                            rows="3"
                            placeholder="Describe what triggers this event..."
                        ></textarea>
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="payload" value="Payload Template (JSON)" />
                        <textarea
                            id="payload"
                            v-model="payloadText"
                            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm mt-1 block w-full font-mono text-sm"
                            rows="8"
                            placeholder="Enter JSON payload template..."
                        ></textarea>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            This JSON template will be used when triggering the event. Use variables like {<!-- -->{user_id}<!-- -->} for dynamic values.
                        </p>
                        <InputError :message="form.errors.payload" class="mt-2" />
                    </div>
                </form>
            </template>

            <template #footer>
                <SecondaryButton @click="closeModal">
                    Cancel
                </SecondaryButton>

                <PrimaryButton 
                    @click="saveEvent" 
                    :disabled="form.processing"
                    class="ml-3"
                >
                    {{ form.processing ? 'Saving...' : (editingEvent ? 'Update' : 'Create') }}
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- Manage Endpoints Modal -->
        <DialogModal :show="showEndpointsModal" @close="showEndpointsModal = false" max-width="2xl">
            <template #title>
                Manage Endpoints for "{{ managingEvent?.name }}"
            </template>

            <template #content>
                <div v-if="availableEndpoints.length > 0" class="space-y-4">
                    <div 
                        v-for="endpoint in availableEndpoints" 
                        :key="endpoint.id"
                        class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg"
                    >
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900 dark:text-white">{{ endpoint.name }}</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ endpoint.url }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                {{ endpoint.description || 'No description' }}
                            </p>
                        </div>
                        <div class="ml-4">
                            <Checkbox 
                                :checked="isEndpointSubscribed(endpoint.id)"
                                @update:checked="toggleEndpointSubscription(endpoint.id)"
                            />
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <p class="text-gray-500 dark:text-gray-400">No endpoints available. Create endpoints first.</p>
                </div>
            </template>

            <template #footer>
                <PrimaryButton @click="saveEndpointSubscriptions">
                    Save Changes
                </PrimaryButton>
                <SecondaryButton @click="showEndpointsModal = false" class="ml-3">
                    Cancel
                </SecondaryButton>
            </template>
        </DialogModal>

        <!-- Trigger Event Modal -->
        <DialogModal :show="showTriggerModal" @close="showTriggerModal = false" max-width="2xl">
            <template #title>
                Trigger Event: "{{ triggeringEvent?.name }}"
            </template>

            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel for="trigger-payload" value="Event Payload" />
                        <textarea
                            id="trigger-payload"
                            v-model="triggerPayload"
                            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm mt-1 block w-full font-mono text-sm"
                            rows="10"
                        ></textarea>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            This payload will be sent to all subscribed endpoints.
                        </p>
                    </div>
                    
                    <div v-if="triggeringEvent?.endpoints?.length">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-2">
                            Will be sent to {{ triggeringEvent.endpoints.length }} endpoint(s):
                        </h4>
                        <ul class="text-sm space-y-1">
                            <li 
                                v-for="endpoint in triggeringEvent.endpoints" 
                                :key="endpoint.id"
                                class="text-gray-600 dark:text-gray-400"
                            >
                                • {{ endpoint.name }} ({{ endpoint.url }})
                            </li>
                        </ul>
                    </div>
                </div>
            </template>

            <template #footer>
                <PrimaryButton @click="executeEventTrigger" :disabled="triggerProcessing">
                    {{ triggerProcessing ? 'Triggering...' : 'Trigger Event' }}
                </PrimaryButton>
                <SecondaryButton @click="showTriggerModal = false" class="ml-3">
                    Cancel
                </SecondaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'
import Checkbox from '@/Components/Checkbox.vue'
import DialogModal from '@/Components/DialogModal.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
    events: Object,
    endpoints: Array,
})

// State
const search = ref('')
const typeFilter = ref('')
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showEndpointsModal = ref(false)
const showTriggerModal = ref(false)
const editingEvent = ref(null)
const managingEvent = ref(null)
const triggeringEvent = ref(null)
const payloadText = ref('')
const triggerPayload = ref('')
const triggerProcessing = ref(false)
const selectedEndpoints = ref([])

// Form
const form = useForm({
    name: '',
    event_type: '',
    description: '',
    payload: null,
})

// Computed
const filteredEvents = computed(() => {
    let filtered = props.events.data || []
    
    if (search.value) {
        const searchLower = search.value.toLowerCase()
        filtered = filtered.filter(event => 
            event.name.toLowerCase().includes(searchLower) ||
            (event.event_type && event.event_type.toLowerCase().includes(searchLower)) ||
            (event.description && event.description.toLowerCase().includes(searchLower))
        )
    }
    
    if (typeFilter.value) {
        filtered = filtered.filter(event => event.event_type === typeFilter.value)
    }
    
    return filtered
})

const eventTypes = computed(() => {
    const types = new Set()
    props.events.data?.forEach(event => {
        if (event.event_type) types.add(event.event_type)
    })
    return Array.from(types).sort()
})

const availableEndpoints = computed(() => props.endpoints || [])

// Watch for payload changes
watch(payloadText, (newValue) => {
    try {
        form.payload = newValue ? JSON.parse(newValue) : null
    } catch (e) {
        // Invalid JSON - will be handled by backend validation
    }
})

// Methods
function closeModal() {
    showCreateModal.value = false
    showEditModal.value = false
    editingEvent.value = null
    form.reset()
    form.clearErrors()
    payloadText.value = ''
}

function editEvent(event) {
    editingEvent.value = event
    form.name = event.name
    form.event_type = event.event_type || ''
    form.description = event.description || ''
    form.payload = event.payload
    payloadText.value = event.payload ? JSON.stringify(event.payload, null, 2) : ''
    showEditModal.value = true
}

function saveEvent() {
    if (editingEvent.value) {
        form.put(route('events.update', editingEvent.value.id), {
            onSuccess: () => closeModal()
        })
    } else {
        form.post(route('events.store'), {
            onSuccess: () => closeModal()
        })
    }
}

function deleteEvent(event) {
    if (confirm('Are you sure you want to delete this event?')) {
        router.delete(route('events.destroy', event.id))
    }
}

function duplicateEvent(event) {
    editingEvent.value = null
    form.name = event.name + ' (Copy)'
    form.event_type = event.event_type || ''
    form.description = event.description || ''
    form.payload = event.payload
    payloadText.value = event.payload ? JSON.stringify(event.payload, null, 2) : ''
    showCreateModal.value = true
}

function manageEndpoints(event) {
    managingEvent.value = event
    selectedEndpoints.value = event.endpoints?.map(e => e.id) || []
    showEndpointsModal.value = true
}

function isEndpointSubscribed(endpointId) {
    return selectedEndpoints.value.includes(endpointId)
}

function toggleEndpointSubscription(endpointId) {
    if (selectedEndpoints.value.includes(endpointId)) {
        selectedEndpoints.value = selectedEndpoints.value.filter(id => id !== endpointId)
    } else {
        selectedEndpoints.value.push(endpointId)
    }
}

function saveEndpointSubscriptions() {
    router.post(route('events.endpoints', managingEvent.value.id), {
        endpoint_ids: selectedEndpoints.value
    }, {
        onSuccess: () => {
            showEndpointsModal.value = false
        }
    })
}

function triggerEvent(event) {
    triggeringEvent.value = event
    triggerPayload.value = event.payload ? JSON.stringify(event.payload, null, 2) : '{}'
    showTriggerModal.value = true
}

function executeEventTrigger() {
    triggerProcessing.value = true
    
    let payload
    try {
        payload = JSON.parse(triggerPayload.value)
    } catch (e) {
        alert('Invalid JSON payload')
        triggerProcessing.value = false
        return
    }

    router.post(route('events.trigger', triggeringEvent.value.id), {
        payload: payload
    }, {
        onSuccess: () => {
            showTriggerModal.value = false
            triggerProcessing.value = false
            // Show success message or redirect to deliveries
            alert('Event triggered successfully!')
        },
        onError: () => {
            triggerProcessing.value = false
        }
    })
}

function viewDeliveries(event) {
    router.get(route('deliveries'), { event_id: event.id })
}

function clearFilters() {
    search.value = ''
    typeFilter.value = ''
}

function formatDate(dateString) {
    if (!dateString) return 'N/A'
    return new Date(dateString).toLocaleDateString()
}

function formatPayload(payload) {
    if (!payload) return 'No payload'
    try {
        return JSON.stringify(payload, null, 2)
    } catch (e) {
        return 'Invalid JSON'
    }
}
</script>
