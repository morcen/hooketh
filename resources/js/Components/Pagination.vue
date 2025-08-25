<template>
    <nav v-if="links.length > 3" class="flex items-center justify-between border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 sm:px-6 rounded-lg">
        <div class="flex flex-1 justify-between sm:hidden">
            <!-- Mobile pagination -->
            <Link
                v-if="prevPageUrl"
                :href="prevPageUrl"
                class="relative inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600"
            >
                Previous
            </Link>
            <span v-else class="relative inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                Previous
            </span>
            
            <Link
                v-if="nextPageUrl"
                :href="nextPageUrl"
                class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600"
            >
                Next
            </Link>
            <span v-else class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                Next
            </span>
        </div>
        
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Showing 
                    <span class="font-medium">{{ from }}</span>
                    to 
                    <span class="font-medium">{{ to }}</span>
                    of 
                    <span class="font-medium">{{ total }}</span>
                    results
                </p>
            </div>
            
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <!-- Previous page link -->
                    <Link
                        v-if="prevPageUrl"
                        :href="prevPageUrl"
                        class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-20 focus:outline-offset-0"
                    >
                        <span class="sr-only">Previous</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                        </svg>
                    </Link>
                    <span v-else class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-300 dark:text-gray-500 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 cursor-not-allowed">
                        <span class="sr-only">Previous</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    
                    <!-- Page number links -->
                    <template v-for="(link, index) in pageLinks" :key="index">
                        <Link
                            v-if="link.url && !link.active"
                            :href="link.url"
                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-20 focus:outline-offset-0"
                            v-html="link.label"
                        />
                        <span
                            v-else-if="link.active"
                            class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-600"
                            v-html="link.label"
                        />
                    </template>
                    
                    <!-- Next page link -->
                    <Link
                        v-if="nextPageUrl"
                        :href="nextPageUrl"
                        class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-20 focus:outline-offset-0"
                    >
                        <span class="sr-only">Next</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                        </svg>
                    </Link>
                    <span v-else class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-300 dark:text-gray-500 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 cursor-not-allowed">
                        <span class="sr-only">Next</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </nav>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    links: {
        type: Array,
        required: true,
    },
    from: {
        type: Number,
        default: null,
    },
    to: {
        type: Number,
        default: null,
    },
    total: {
        type: Number,
        default: null,
    },
})

const pageLinks = computed(() => {
    // Remove first and last links (Previous and Next)
    return props.links.slice(1, -1)
})

const prevPageUrl = computed(() => {
    const prevLink = props.links[0]
    return prevLink && prevLink.url ? prevLink.url : null
})

const nextPageUrl = computed(() => {
    const nextLink = props.links[props.links.length - 1]
    return nextLink && nextLink.url ? nextLink.url : null
})
</script>
