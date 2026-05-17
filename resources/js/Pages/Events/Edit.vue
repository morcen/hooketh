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
                            <InputError :message="form.errors.payload" class="mt-2" />
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
                            <InputError :message="form.errors.schema" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <Link :href="route('events')">
                                <SecondaryButton type="button">Cancel</SecondaryButton>
                            </Link>
                            <PrimaryButton type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Saving...' : 'Update Event' }}
                            </PrimaryButton>
                        </div>
                    </form>
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

watch(payloadText, (val) => {
    try { form.payload = val ? JSON.parse(val) : null } catch {}
})

watch(schemaText, (val) => {
    try { form.schema = val ? JSON.parse(val) : null } catch {}
})

function save() {
    form.put(route('events.update', props.event.id), {
        onSuccess: () => router.visit(route('events')),
    })
}
</script>
