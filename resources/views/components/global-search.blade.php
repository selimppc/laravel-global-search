<div x-data="{ q: '', results: null, loading: false, doSearch: _.debounce(async function(){
    if (!this.q) { this.results = null; return; }
    this.loading = true;
    const res = await fetch(`/api/global-search?q=${encodeURIComponent(this.q)}`);
    this.results = await res.json();
    this.loading = false;
    }, 300) }" class="space-y-2">

    <input type="search" x-model="q" @input="doSearch()" class="w-full border rounded p-2" placeholder="Search…" />
    <template x-if="loading"><div>Searching…</div></template>
        <template x-if="results">
            <div class="space-y-3">
                <template x-for="hit in results.hits" :key="(hit.id ?? hit.slug)">
                    <div class="p-3 border rounded">
                    <div class="font-semibold" x-text="hit.title || hit.sku || hit.slug || hit.id"></div>
                    <div class="text-sm text-gray-600" x-text="hit.excerpt || hit.description || ''"></div>
                    <div class="text-xs mt-1" x-text="`Index: ${hit._index}`"></div>
                </template>
            </div>    
        </template>
    </template>
</div>