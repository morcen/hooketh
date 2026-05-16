<template>
    <AppLayout title="Endpoints">
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="app-label">Delivery targets</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">Endpoints</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage destination URLs, signing secrets, and event subscriptions.
                    </p>
                </div>
                <PrimaryButton @click="showCreateModal = true">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7H5"/>
                    </svg>
                    Add endpoint
                </PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="app-stat">
                        <p class="app-label">Total endpoints</p>
                        <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ endpointStats.total }}</p>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Across this workspace</p>
                    </div>
                    <div class="app-stat">
                        <p class="app-label">Active shown</p>
                        <p class="mt-3 text-3xl font-bold text-emerald-700 dark:text-emerald-300">{{ endpointStats.active }}</p>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">On this page</p>
                    </div>
                    <div class="app-stat">
                        <p class="app-label">Subscriptions shown</p>
                        <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ endpointStats.subscriptions }}</p>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Event links on this page</p>
                    </div>
                </div>

                <div class="app-panel p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <div class="relative flex-1">
                            <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/>
                            </svg>
                            <TextInput
                                v-model="search"
                                placeholder="Search by name, URL, or description"
                                class="w-full pl-9"
                            />
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <select v-model="statusFilter" class="app-select min-w-40">
                                <option value="">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <SecondaryButton @click="clearFilters" :disabled="!search && !statusFilter">
                                Clear
                            </SecondaryButton>
                        </div>
                    </div>
                </div>

                <div v-if="filteredEndpoints.length" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <article
                        v-for="endpoint in filteredEndpoints"
                        :key="endpoint.id"
                        class="app-panel p-5 transition hover:-translate-y-0.5 hover:shadow-md hover:shadow-slate-200/70 dark:hover:shadow-none"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-lg font-semibold text-slate-950 dark:text-white">
                                        {{ endpoint.name }}
                                    </h2>
                                    <span :class="endpoint.is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'" class="app-status">
                                        <span :class="endpoint.is_active ? 'bg-emerald-500' : 'bg-slate-400'" class="mr-1.5 size-1.5 rounded-full"></span>
                                        {{ endpoint.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <p class="mt-2 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">
                                    {{ endpoint.description || 'No description added yet.' }}
                                </p>
                            </div>

                            <Dropdown>
                                <template #trigger>
                                    <button class="rounded-md p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                                        <svg class="size-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                        </svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink @click="editEndpoint(endpoint)">Edit</DropdownLink>
                                    <DropdownLink @click="toggleEndpoint(endpoint)">
                                        {{ endpoint.is_active ? 'Deactivate' : 'Activate' }}
                                    </DropdownLink>
                                    <DropdownLink @click="testEndpoint(endpoint)">Test connection</DropdownLink>
                                    <DropdownLink @click="deleteEndpoint(endpoint)" class="text-rose-600">Delete</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>

                        <div class="mt-5 rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950">
                            <div class="flex items-center justify-between gap-3">
                                <code class="min-w-0 truncate text-xs font-semibold text-slate-700 dark:text-slate-300">{{ endpoint.url }}</code>
                                <button
                                    type="button"
                                    @click="copyUrl(endpoint.url)"
                                    class="shrink-0 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:text-slate-950 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-white"
                                >
                                    {{ copiedUrl === endpoint.url ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="app-muted-panel p-3">
                                <p class="app-label">Events</p>
                                <p class="mt-1 text-lg font-bold text-slate-950 dark:text-white">{{ endpoint.events?.length || 0 }}</p>
                            </div>
                            <div class="app-muted-panel p-3">
                                <p class="app-label">Updated</p>
                                <p class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ formatDate(endpoint.updated_at) }}</p>
                            </div>
                        </div>

                        <div v-if="endpoint.events?.length" class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="event in endpoint.events.slice(0, 4)"
                                :key="event.id"
                                class="rounded-md bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-950 dark:text-teal-300"
                            >
                                {{ event.name }}
                            </span>
                            <span v-if="endpoint.events.length > 4" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                +{{ endpoint.events.length - 4 }} more
                            </span>
                        </div>
                    </article>
                </div>

                <div v-else class="app-panel px-6 py-14 text-center">
                    <div class="mx-auto flex size-14 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                        <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v7.5A2.25 2.25 0 005.25 18h8.25m3-12L21 10.5m0 0L16.5 15M21 10.5H9" />
                        </svg>
                    </div>
                    <h2 class="mt-5 text-lg font-semibold text-slate-950 dark:text-white">No endpoints found</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">
                        {{ search || statusFilter ? 'Adjust the search or status filter to find a matching endpoint.' : 'Create your first endpoint to start receiving signed webhook deliveries.' }}
                    </p>
                    <PrimaryButton v-if="!search && !statusFilter" @click="showCreateModal = true" class="mt-5">
                        Add endpoint
                    </PrimaryButton>
                </div>

                <div v-if="endpoints.last_page > 1" class="mt-6">
                    <Pagination :links="endpoints.links" />
                </div>
            </div>
        </div>

        <DialogModal :show="showCreateModal || showEditModal" @close="closeModal">
            <template #title>
                {{ editingEndpoint ? 'Edit endpoint' : 'Create endpoint' }}
            </template>

            <template #content>
                <form @submit.prevent="saveEndpoint" class="space-y-6">
                    <div>
                        <InputLabel for="name" value="Endpoint name" />
                        <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus placeholder="Production billing receiver" />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="url" value="Destination URL" />
                        <TextInput id="url" v-model="form.url" type="url" class="mt-1 block w-full font-mono text-sm" required placeholder="https://example.com/webhook" />
                        <InputError :message="form.errors.url" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="description" value="Description" />
                        <textarea id="description" v-model="form.description" class="app-input mt-1 block w-full" rows="3" placeholder="What receives these webhooks?"></textarea>
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>

                    <label class="flex items-center rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950">
                        <Checkbox v-model:checked="form.is_active" />
                        <span class="ml-3">
                            <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Endpoint is active</span>
                            <span class="block text-sm text-slate-500 dark:text-slate-400">Active endpoints receive deliveries for subscribed events.</span>
                        </span>
                    </label>

                    <div v-if="editingEndpoint" class="rounded-md border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/40">
                        <InputLabel value="Signing secret" />
                        <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Secret configured
                            </span>
                            <SecondaryButton @click="regenerateSecret" class="whitespace-nowrap">
                                Rotate key
                            </SecondaryButton>
                        </div>
                        <p class="mt-2 text-sm text-emerald-900/75 dark:text-emerald-200/75">
                            Rotating creates a new one-time secret and invalidates the current value.
                        </p>
                    </div>
                </form>
            </template>

            <template #footer>
                <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton @click="saveEndpoint" :disabled="form.processing" class="ml-3">
                    {{ form.processing ? 'Saving...' : (editingEndpoint ? 'Update endpoint' : 'Create endpoint') }}
                </PrimaryButton>
            </template>
        </DialogModal>

        <DialogModal :show="showSecretModal" @close="closeSecretModal">
            <template #title>
                Save your signing secret
            </template>

            <template #content>
                <div class="space-y-4">
                    <div class="rounded-md border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
                        <p class="text-sm text-amber-900 dark:text-amber-200">
                            This secret is shown once. Store it securely and use it to verify webhook signatures on your server.
                        </p>
                    </div>

                    <div>
                        <InputLabel value="Secret key" />
                        <div class="mt-1 flex gap-2">
                            <TextInput :value="revealedSecret" type="text" class="block w-full font-mono text-sm" readonly />
                            <SecondaryButton @click="copyRevealedSecret" class="whitespace-nowrap">
                                {{ secretCopied ? 'Copied' : 'Copy' }}
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <PrimaryButton @click="closeSecretModal">
                    I've saved it
                </PrimaryButton>
            </template>
        </DialogModal>

        <DialogModal :show="showTestModal" @close="showTestModal = false">
            <template #title>
                Connection test
            </template>

            <template #content>
                <div v-if="testResult" class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div :class="testResult.success ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'" class="rounded-full p-2">
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="testResult.success" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-950 dark:text-white">
                                {{ testResult.success ? 'Connection successful' : 'Connection failed' }}
                            </h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Response code: {{ testResult.response_code || 'N/A' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="testResult.message" class="rounded-md bg-slate-100 p-4 dark:bg-slate-950">
                        <pre class="overflow-x-auto text-sm text-slate-700 dark:text-slate-300">{{ testResult.message }}</pre>
                    </div>
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="showTestModal = false">Close</SecondaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
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
    endpoints: Object,
})

const search = ref('')
const statusFilter = ref('')
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showTestModal = ref(false)
const showSecretModal = ref(false)
const editingEndpoint = ref(null)
const testResult = ref(null)
const revealedSecret = ref('')
const secretCopied = ref(false)
const copiedUrl = ref('')

const form = useForm({
    name: '',
    url: '',
    description: '',
    is_active: true,
})

const filteredEndpoints = computed(() => {
    let filtered = props.endpoints.data || []

    if (search.value) {
        const searchLower = search.value.toLowerCase()
        filtered = filtered.filter(endpoint =>
            endpoint.name.toLowerCase().includes(searchLower) ||
            endpoint.url.toLowerCase().includes(searchLower) ||
            (endpoint.description && endpoint.description.toLowerCase().includes(searchLower))
        )
    }

    if (statusFilter.value) {
        filtered = filtered.filter(endpoint => {
            if (statusFilter.value === 'active') return endpoint.is_active
            if (statusFilter.value === 'inactive') return !endpoint.is_active
            return true
        })
    }

    return filtered
})

const endpointStats = computed(() => {
    const all = props.endpoints.data || []

    return {
        total: props.endpoints.total || all.length,
        active: all.filter(endpoint => endpoint.is_active).length,
        subscriptions: all.reduce((count, endpoint) => count + (endpoint.events?.length || 0), 0),
    }
})

function closeModal() {
    showCreateModal.value = false
    showEditModal.value = false
    editingEndpoint.value = null
    form.reset()
    form.clearErrors()
}

function closeSecretModal() {
    showSecretModal.value = false
    revealedSecret.value = ''
    secretCopied.value = false
}

function showOneTimeSecret(secret) {
    revealedSecret.value = secret
    secretCopied.value = false
    showSecretModal.value = true
}

function copyRevealedSecret() {
    navigator.clipboard.writeText(revealedSecret.value).then(() => {
        secretCopied.value = true
    })
}

function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        copiedUrl.value = url
        setTimeout(() => {
            copiedUrl.value = ''
        }, 1600)
    })
}

function editEndpoint(endpoint) {
    editingEndpoint.value = endpoint
    form.name = endpoint.name
    form.url = endpoint.url
    form.description = endpoint.description
    form.is_active = endpoint.is_active
    showEditModal.value = true
}

async function saveEndpoint() {
    if (editingEndpoint.value) {
        form.put(route('endpoints.update', editingEndpoint.value.id), {
            onSuccess: () => closeModal()
        })
    } else {
        form.processing = true
        try {
            const response = await axios.post(route('endpoints.store'), {
                name: form.name,
                url: form.url,
                description: form.description,
                is_active: form.is_active,
            })
            closeModal()
            router.reload({ only: ['endpoints'] })
            showOneTimeSecret(response.data.plain_secret)
        } catch (error) {
            if (error.response?.data?.errors) {
                form.setError(error.response.data.errors)
            }
        } finally {
            form.processing = false
        }
    }
}

function toggleEndpoint(endpoint) {
    router.patch(route('endpoints.update', endpoint.id), {
        is_active: !endpoint.is_active
    })
}

function deleteEndpoint(endpoint) {
    if (confirm('Are you sure you want to delete this endpoint?')) {
        router.delete(route('endpoints.destroy', endpoint.id))
    }
}

function testEndpoint(endpoint) {
    router.post(route('endpoints.test', endpoint.id), {}, {
        onSuccess: (response) => {
            testResult.value = response.props.testResult
            showTestModal.value = true
        }
    })
}

async function regenerateSecret() {
    if (confirm('Are you sure you want to regenerate the secret key? This will invalidate the current key.')) {
        const endpointId = editingEndpoint.value.id
        closeModal()
        const response = await axios.post(route('endpoints.regenerate-secret', endpointId))
        showOneTimeSecret(response.data.plain_secret)
    }
}

function clearFilters() {
    search.value = ''
    statusFilter.value = ''
}

function formatDate(dateString) {
    if (!dateString) return 'N/A'
    return new Date(dateString).toLocaleDateString()
}
</script>
