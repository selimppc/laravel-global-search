<div x-data="globalSearch()" class="global-search-container">
    <!-- Search Input -->
    <div class="search-input-container">
        <div class="relative">
            <input 
                type="search" 
                x-model="query" 
                @input="performSearch()" 
                @focus="showResults = true"
                @blur="hideResults()"
                class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                placeholder="Search across all content..."
                autocomplete="off"
            />
            <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                <div x-show="loading" class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"></div>
                <svg x-show="!loading" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Search Results -->
    <div x-show="showResults && (query.length > 0)" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95"
         class="search-results-container">
        
        <!-- Loading State -->
        <div x-show="loading" class="p-4 text-center text-gray-500">
            <div class="inline-flex items-center">
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500 mr-2"></div>
                Searching...
            </div>
        </div>

        <!-- Error State -->
        <div x-show="error" class="p-4 text-center text-red-500 bg-red-50 rounded-lg">
            <div class="flex items-center justify-center">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span x-text="error"></span>
            </div>
        </div>

        <!-- Results -->
        <div x-show="!loading && !error && results && results.hits.length > 0" class="search-results">
            <div class="px-4 py-2 text-sm text-gray-500 border-b">
                <span x-text="results.meta.total"></span> results found
            </div>
            
            <div class="max-h-96 overflow-y-auto">
                <template x-for="hit in results.hits" :key="hit.id || hit.slug || Math.random()">
                    <div class="search-result-item">
                        <div class="flex items-start space-x-3 p-4 hover:bg-gray-50 transition-colors duration-150">
                            <!-- Result Icon -->
                            <div class="flex-shrink-0 mt-1">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <!-- Result Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-medium text-gray-900 truncate" x-text="hit.title || hit.name || hit.sku || 'Untitled'"></h3>
                                    <span class="text-xs text-gray-400 uppercase tracking-wide" x-text="hit._index"></span>
                                </div>
                                
                                <p class="mt-1 text-sm text-gray-600 line-clamp-2" x-text="hit.excerpt || hit.description || ''"></p>
                                
                                <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500">
                                    <template x-if="hit.url">
                                        <a :href="hit.url" class="text-blue-600 hover:text-blue-800 font-medium">View Details</a>
                                    </template>
                                    <template x-if="hit.formatted_price">
                                        <span class="font-medium text-green-600" x-text="hit.formatted_price"></span>
                                    </template>
                                    <template x-if="hit.created_at">
                                        <span x-text="formatDate(hit.created_at)"></span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- No Results -->
        <div x-show="!loading && !error && results && results.hits.length === 0" class="p-8 text-center text-gray-500">
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-lg font-medium">No results found</p>
            <p class="text-sm">Try adjusting your search terms</p>
        </div>
    </div>
</div>

<script>
function globalSearch() {
    return {
        query: '',
        results: null,
        loading: false,
        error: null,
        showResults: false,
        
        performSearch: _.debounce(async function() {
            if (!this.query.trim()) {
                this.results = null;
                this.error = null;
                return;
            }
            
            this.loading = true;
            this.error = null;
            
            try {
                const response = await fetch(`/api/global-search?q=${encodeURIComponent(this.query)}`);
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Search failed');
                }
                
                this.results = data.data;
            } catch (err) {
                this.error = err.message;
                this.results = null;
            } finally {
                this.loading = false;
            }
        }, 300),
        
        hideResults() {
            // Delay hiding to allow clicking on results
            setTimeout(() => {
                this.showResults = false;
            }, 200);
        },
        
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
    }
}
</script>

<style>
.global-search-container {
    position: relative;
}

.search-results-container {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 50;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    margin-top: 0.25rem;
}

.search-result-item:not(:last-child) {
    border-bottom: 1px solid #f3f4f6;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>