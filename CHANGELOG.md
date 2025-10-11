# Changelog

All notable changes to this project will be documented in this file.

## [v1.1.29] - 2025-10-11

### 🚀 Major: Fully Dynamic & Configurable Package

This release transforms the package into a **100% dynamic, user-configurable system** with **zero hardcoded values**. Every aspect of the package can now be controlled via configuration or environment variables.

### ✨ New Features

#### Dynamic Configuration System
- **Cache Configuration**: Cache TTL, store, and prefix now fully configurable
  - `GLOBAL_SEARCH_CACHE_ENABLED` - Enable/disable caching
  - `GLOBAL_SEARCH_CACHE_STORE` - Choose cache driver (redis, file, etc.)
  - `GLOBAL_SEARCH_CACHE_TTL` - Cache duration in seconds
  - `GLOBAL_SEARCH_CACHE_PREFIX` - Custom cache key prefix

#### Data Transformation Controls
- **Metadata Generation**: Optional via `GLOBAL_SEARCH_ADD_METADATA`
- **Tenant ID Inclusion**: Configurable via `GLOBAL_SEARCH_ADD_TENANT_ID`
- **Data Cleaning**: Remove nulls and empty strings via config
  - `GLOBAL_SEARCH_CLEAN_NULLS` - Remove null values
  - `GLOBAL_SEARCH_CLEAN_EMPTY` - Remove empty strings
- **Relationship Limits**: Configurable max items via `GLOBAL_SEARCH_MAX_RELATIONSHIP_ITEMS`

#### Pipeline Configuration
- **Chunk Size**: Configurable via `GLOBAL_SEARCH_CHUNK_SIZE` (default: 100)
- **Retry Logic**: Fully configurable retry behavior
  - `GLOBAL_SEARCH_RETRY_ATTEMPTS` - Number of retry attempts
  - `GLOBAL_SEARCH_RETRY_DELAY` - Delay between retries (ms)
  - `GLOBAL_SEARCH_MAX_RETRY_WAIT` - Max attempts for index creation

#### API Limits
- **Default Limit**: Configurable via `GLOBAL_SEARCH_DEFAULT_LIMIT`
- **Max Limit**: Configurable via `GLOBAL_SEARCH_MAX_LIMIT` (default: 100, up from 50)

#### Performance Monitoring
- **Slow Query Detection**: Automatic logging of slow queries
  - `GLOBAL_SEARCH_LOG_SLOW_QUERIES` - Enable/disable slow query logging
  - `GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD` - Threshold in milliseconds
- **Metrics Storage**: Configurable via `GLOBAL_SEARCH_MAX_METRICS`
- **Query Duration**: Now included in search response metadata

### 🔧 Improvements

#### Performance Optimizations
- Added query duration tracking to all search requests
- Configurable slow query threshold with automatic logging
- Optimized cache key generation
- Added option to disable caching entirely for real-time requirements
- Reduced memory footprint with configurable chunk sizes

#### Code Quality
- Removed all hardcoded values (60s cache, 50 limit, 100 chunk size, etc.)
- Centralized configuration in `config/global-search.php`
- Improved code readability with consistent config access patterns
- Better separation of concerns in service classes

#### Developer Experience
- Comprehensive `CONFIGURATION.md` with all options documented
- Environment variable examples for common scenarios
- Best practices for production, development, and high-volume setups
- Troubleshooting guide for common configuration issues

### 📚 Documentation

#### New Files
- **`CONFIGURATION.md`**: Complete guide to all configuration options
  - Meilisearch client setup
  - Federation configuration
  - Data transformation options
  - Cache settings
  - Pipeline tuning
  - Performance monitoring
  - Multi-tenancy setup
  - Best practices by environment

#### Updated Files
- **`README.md`**: Updated with sorting documentation
- **`config/global-search.php`**: Added 30+ new configuration options

### 🔄 Breaking Changes

**None** - All changes are backward compatible. Existing configurations will continue to work with sensible defaults.

### 📦 Configuration Migration

If you're upgrading from a previous version, you may want to republish the config:

```bash
php artisan vendor:publish --tag=global-search-config --force
```

Then update your `.env` file with any custom values:

```env
# Example: Increase limits for high-volume app
GLOBAL_SEARCH_MAX_LIMIT=200
GLOBAL_SEARCH_BATCH_SIZE=5000

# Example: Disable metadata for smaller index size
GLOBAL_SEARCH_ADD_METADATA=false

# Example: Enable aggressive slow query detection
GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD=500
```

### 🎯 Use Cases

#### High-Volume Applications
```env
GLOBAL_SEARCH_BATCH_SIZE=5000
GLOBAL_SEARCH_CHUNK_SIZE=500
GLOBAL_SEARCH_MAX_LIMIT=50
GLOBAL_SEARCH_CACHE_TTL=600
```

#### Low-Memory Environments
```env
GLOBAL_SEARCH_BATCH_SIZE=100
GLOBAL_SEARCH_CHUNK_SIZE=10
GLOBAL_SEARCH_MAX_RELATIONSHIP_ITEMS=5
GLOBAL_SEARCH_CLEAN_NULLS=true
```

#### Real-Time Search (No Caching)
```env
GLOBAL_SEARCH_CACHE_ENABLED=false
```

#### Development Environment
```env
GLOBAL_SEARCH_CACHE_ENABLED=false
GLOBAL_SEARCH_PERFORMANCE_MONITORING=true
GLOBAL_SEARCH_LOG_SLOW_QUERIES=true
GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD=100
```

---

## [v1.1.28] - 2025-10-11

### ✨ Features
- Added comprehensive sorting functionality
- Support for multiple sort fields and directions
- Configurable sortable attributes per index

### 🔧 Improvements
- Enhanced GlobalSearchService with sort parameter
- Updated GlobalSearchController to accept sort from API requests
- Added sort information to search response metadata

---

## [v1.1.27] - 2025-10-11

### ✨ Features
- Initial sorting support implementation

---

## Previous Versions

See git history for details on versions v1.1.0 - v1.1.26.

---

## Upgrade Guide

### From v1.1.28 to v1.1.29

1. **Republish Configuration** (Optional but recommended):
   ```bash
   php artisan vendor:publish --tag=global-search-config --force
   ```

2. **Review New Configuration Options**:
   Check `CONFIGURATION.md` for all available options

3. **Optimize for Your Environment**:
   Add relevant environment variables to your `.env` file

4. **Test Your Setup**:
   ```bash
   php artisan search:health
   php artisan search:performance
   ```

No code changes required - all existing functionality remains unchanged!

---

## Support

For issues, questions, or feature requests, please visit:
- [GitHub Issues](https://github.com/laravel-global-search/global-search/issues)
- [Documentation](README.md)
- [Configuration Guide](CONFIGURATION.md)

