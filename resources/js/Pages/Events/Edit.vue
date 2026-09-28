<template>
    <AppLayout :title="`Edit Event: ${event.name}`">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('events')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Edit Event: {{ event.name }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <form @submit.prevent="save" class="p-6 space-y-6">
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
                            <InputError :message="payloadJsonError || form.errors.payload" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="schema" value="Payload Schema (Optional)" />
                            <textarea
                                id="schema"
                                v-model="schemaText"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm mt-1 block w-full font-mono text-sm"
                                rows="5"
                                placeholder='{ "user_id": "required|integer", "email": "required|string" }'
                            ></textarea>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Optional. Define expected payload fields using Laravel validation rules. Trigger requests that don't match will be rejected with a 422.
                            </p>
                            <InputError :message="schemaJsonError || form.errors.schema" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <Link :href="route('events')">
                                <SecondaryButton type="button">Cancel</SecondaryButton>
                            </Link>
                            <PrimaryButton type="submit" :disabled="form.processing || !!payloadJsonError || !!schemaJsonError">
                                {{ form.processing ? 'Saving...' : 'Update Event' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 space-y-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Subscribed Endpoints</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Choose which endpoints receive this event when it's triggered.
                            </p>
                        </div>

                        <div v-if="endpoints.length > 0" class="space-y-4">
                            <div
                                v-for="endpoint in endpoints"
                                :key="endpoint.id"
                                class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg"
                            >
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900 dark:text-white">{{ endpoint.name }}</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-[40ch]">{{ endpoint.url }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 truncate max-w-[40ch]">
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

                        <div class="flex items-center justify-end pt-2 border-t border-gray-200 dark:border-gray-700">
                            <PrimaryButton @click="saveEndpointSubscriptions" :disabled="endpointsProcessing">
                                {{ endpointsProcessing ? 'Saving...' : 'Save Endpoint Subscriptions' }}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'
import Checkbox from '@/Components/Checkbox.vue'

const props = defineProps({
    event: Object,
    endpoints: Array,
})

const form = useForm({
    name: props.event.name,
    event_type: props.event.event_type || '',
    description: props.event.description || '',
    payload: props.event.payload,
    schema: props.event.schema,
})

const payloadText = ref(props.event.payload ? JSON.stringify(props.event.payload, null, 2) : '')
const schemaText = ref(props.event.schema ? JSON.stringify(props.event.schema, null, 2) : '')
const payloadJsonError = ref('')
const schemaJsonError = ref('')

watch(payloadText, (val) => {
    if (!val) {
        form.payload = null
        payloadJsonError.value = ''
        return
    }

    try {
        form.payload = JSON.parse(val)
        payloadJsonError.value = ''
    } catch (e) {
        // Invalid JSON - surface the error and stop the stale value from being submitted
        payloadJsonError.value = 'Invalid JSON: ' + e.message
    }
})

watch(schemaText, (val) => {
    if (!val) {
        form.schema = null
        schemaJsonError.value = ''
        return
    }

    try {
        form.schema = JSON.parse(val)
        schemaJsonError.value = ''
    } catch (e) {
        // Invalid JSON - surface the error and stop the stale value from being submitted
        schemaJsonError.value = 'Invalid JSON: ' + e.message
    }
})

function save() {
    if (payloadJsonError.value || schemaJsonError.value) {
        return
    }

    form.put(route('events.update', props.event.id), {
        onSuccess: () => router.visit(route('events')),
    })
}

const selectedEndpoints = ref(props.event.endpoints?.map(e => e.id) || [])
const endpointsProcessing = ref(false)

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
    endpointsProcessing.value = true

    router.post(route('events.endpoints', props.event.id), {
        endpoint_ids: selectedEndpoints.value,
    }, {
        onFinish: () => {
            endpointsProcessing.value = false
        },
    })
}
</script>
