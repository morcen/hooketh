<template>
    <AppLayout title="Manage Endpoints">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Manage Endpoints
                </h2>
                <PrimaryButton @click="showCreateModal = true">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add Endpoint
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
                                    placeholder="Search endpoints..."
                                    class="w-full"
                                />
                            </div>
                            <div class="flex gap-2">
                                <select 
                                    v-model="statusFilter" 
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                >
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <PrimaryButton @click="clearFilters" variant="outline">
                                    Clear Filters
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Endpoints Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                    <div 
                        v-for="endpoint in filteredEndpoints" 
                        :key="endpoint.id"
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg"
                    >
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ endpoint.name }}
                                        </h3>
                                        <span 
                                            :class="endpoint.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'"
                                            class="ml-2 px-2 py-1 text-xs font-medium rounded-full"
                                        >
                                            {{ endpoint.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                        {{ endpoint.description || 'No description' }}
                                    </p>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                                        <p class="truncate">{{ endpoint.url }}</p>
                                        <p>{{ endpoint.events?.length || 0 }} subscribed events</p>
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
                                            <DropdownLink @click="editEndpoint(endpoint)">
                                                Edit
                                            </DropdownLink>
                                            <DropdownLink @click="toggleEndpoint(endpoint)">
                                                {{ endpoint.is_active ? 'Deactivate' : 'Activate' }}
                                            </DropdownLink>
                                            <DropdownLink @click="testEndpoint(endpoint)">
                                                Test Connection
                                            </DropdownLink>
                                            <DropdownLink @click="copySecret(endpoint)" class="border-t border-gray-100 dark:border-gray-700">
                                                Copy Secret
                                            </DropdownLink>
                                            <DropdownLink @click="deleteEndpoint(endpoint)" class="text-red-600">
                                                Delete
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                            
                            <!-- Stats -->
                            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <span>Last used: {{ formatDate(endpoint.updated_at) }}</span>
                                <span>Created: {{ formatDate(endpoint.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="filteredEndpoints.length === 0" class="text-center py-12">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-12">
                        <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No endpoints found</h3>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">
                            {{ search || statusFilter ? 'Try adjusting your search or filters.' : 'Get started by creating your first webhook endpoint.' }}
                        </p>
                        <PrimaryButton @click="showCreateModal = true" class="mt-4" v-if="!search && !statusFilter">
                            Add Endpoint
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="endpoints.last_page > 1" class="mt-6">
                    <Pagination :links="endpoints.links" />
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <DialogModal :show="showCreateModal || showEditModal" @close="closeModal">
            <template #title>
                {{ editingEndpoint ? 'Edit Endpoint' : 'Create New Endpoint' }}
            </template>

            <template #content>
                <form @submit.prevent="saveEndpoint" class="space-y-6">
                    <div>
                        <InputLabel for="name" value="Endpoint Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                        />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="url" value="URL" />
                        <TextInput
                            id="url"
                            v-model="form.url"
                            type="url"
                            class="mt-1 block w-full"
                            required
                            placeholder="https://example.com/webhook"
                        />
                        <InputError :message="form.errors.url" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="description" value="Description (Optional)" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm mt-1 block w-full"
                            rows="3"
                            placeholder="Brief description of this endpoint..."
                        ></textarea>
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>

                    <div>
                        <label class="flex items-center">
                            <Checkbox v-model:checked="form.is_active" />
                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Active</span>
                        </label>
                    </div>

                    <div v-if="editingEndpoint">
                        <InputLabel for="secret_key" value="Secret Key" />
                        <div class="flex mt-1">
                            <TextInput
                                id="secret_key"
                                :value="editingEndpoint.secret_key"
                                type="text"
                                class="block w-full"
                                readonly
                            />
                            <SecondaryButton @click="regenerateSecret" class="ml-2">
                                Regenerate
                            </SecondaryButton>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Use this secret to verify webhook signatures.
                        </p>
                    </div>
                </form>
            </template>

            <template #footer>
                <SecondaryButton @click="closeModal">
                    Cancel
                </SecondaryButton>

                <PrimaryButton 
                    @click="saveEndpoint" 
                    :disabled="form.processing"
                    class="ml-3"
                >
                    {{ form.processing ? 'Saving...' : (editingEndpoint ? 'Update' : 'Create') }}
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- Test Result Modal -->
        <DialogModal :show="showTestModal" @close="showTestModal = false">
            <template #title>
                Connection Test Result
            </template>

            <template #content>
                <div v-if="testResult">
                    <div class="flex items-center mb-4">
                        <div 
                            :class="testResult.success ? 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400' : 'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400'"
                            class="p-2 rounded-full mr-3"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="testResult.success" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium">
                                {{ testResult.success ? 'Connection Successful' : 'Connection Failed' }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Response Code: {{ testResult.response_code }}
                            </p>
                        </div>
                    </div>
                    
                    <div v-if="testResult.message" class="bg-gray-100 dark:bg-gray-700 p-4 rounded-md">
                        <pre class="text-sm">{{ testResult.message }}</pre>
                    </div>
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="showTestModal = false">
                    Close
                </SecondaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
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
    endpoints: Object,
})

// State
const search = ref('')
const statusFilter = ref('')
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showTestModal = ref(false)
const editingEndpoint = ref(null)
const testResult = ref(null)

// Form
const form = useForm({
    name: '',
    url: '',
    description: '',
    is_active: true,
})

// Computed
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

// Methods
function closeModal() {
    showCreateModal.value = false
    showEditModal.value = false
    editingEndpoint.value = null
    form.reset()
    form.clearErrors()
}

function editEndpoint(endpoint) {
    editingEndpoint.value = endpoint
    form.name = endpoint.name
    form.url = endpoint.url
    form.description = endpoint.description
    form.is_active = endpoint.is_active
    showEditModal.value = true
}

function saveEndpoint() {
    if (editingEndpoint.value) {
        form.put(route('endpoints.update', editingEndpoint.value.id), {
            onSuccess: () => closeModal()
        })
    } else {
        form.post(route('endpoints.store'), {
            onSuccess: () => closeModal()
        })
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

function copySecret(endpoint) {
    navigator.clipboard.writeText(endpoint.secret_key).then(() => {
        // You might want to show a toast notification here
        alert('Secret key copied to clipboard!')
    })
}

function regenerateSecret() {
    if (confirm('Are you sure you want to regenerate the secret key? This will invalidate the current key.')) {
        router.post(route('endpoints.regenerate-secret', editingEndpoint.value.id))
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
