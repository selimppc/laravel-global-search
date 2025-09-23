<?php

namespace App\Transformers;

use LaravelGlobalSearch\GlobalSearch\Contracts\DataTransformer;

/**
 * Example: Custom User Data Transformer
 * Shows how to handle complex user data transformation.
 */
class UserDataTransformer implements DataTransformer
{
    public function transform($model, ?string $tenant = null): array
    {
        $data = [
            'id' => $model->id,
            'name' => $model->name,
            'email' => $model->email,
            'first_name' => $model->first_name,
            'last_name' => $model->last_name,
            'phone' => $this->formatPhone($model->phone),
            'avatar_url' => $this->getAvatarUrl($model),
            'full_name' => $model->first_name . ' ' . $model->last_name,
            'initials' => $this->getInitials($model),
            'status' => $this->getStatusText($model->status),
            'created_at' => $model->created_at?->toISOString(),
            'updated_at' => $model->updated_at?->toISOString(),
        ];

        // Add tenant context
        if ($tenant) {
            $data['tenant_id'] = $tenant;
        }

        // Add relationships
        if ($model->relationLoaded('roles')) {
            $data['roles'] = $model->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ];
            })->toArray();
        }

        if ($model->relationLoaded('profile')) {
            $data['profile'] = [
                'bio' => $model->profile->bio,
                'location' => $model->profile->location,
                'website' => $model->profile->website,
                'social_links' => $model->profile->social_links ?? [],
            ];
        }

        // Add computed fields
        $data['is_active'] = $model->status === 1;
        $data['is_verified'] = !is_null($model->email_verified_at);
        $data['search_text'] = $this->buildSearchText($model);

        // Add metadata
        $data['_search_metadata'] = [
            'model_type' => 'User',
            'model_class' => get_class($model),
            'indexed_at' => now()->toISOString(),
            'url' => route('users.show', $model->id),
        ];

        return $data;
    }

    public function getModelClass(): string
    {
        return \App\Models\User::class;
    }

    public function getSearchableFields(): array
    {
        return [
            'name', 'email', 'first_name', 'last_name', 'phone', 
            'search_text', 'profile.bio', 'profile.location'
        ];
    }

    public function getFilterableFields(): array
    {
        return [
            'status', 'is_active', 'is_verified', 'created_at', 
            'roles.name', 'profile.location'
        ];
    }

    public function getSortableFields(): array
    {
        return [
            'name', 'email', 'created_at', 'updated_at'
        ];
    }

    private function formatPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Format as (XXX) XXX-XXXX
        if (strlen($phone) === 10) {
            return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
        }
        
        return $phone;
    }

    private function getAvatarUrl($model): ?string
    {
        if ($model->avatar) {
            return asset('storage/' . $model->avatar);
        }
        
        // Generate Gravatar URL
        return 'https://www.gravatar.com/avatar/' . md5(strtolower($model->email)) . '?d=identicon';
    }

    private function getInitials($model): string
    {
        $first = substr($model->first_name ?? '', 0, 1);
        $last = substr($model->last_name ?? '', 0, 1);
        
        return strtoupper($first . $last);
    }

    private function getStatusText(int $status): string
    {
        return match($status) {
            0 => 'Inactive',
            1 => 'Active',
            2 => 'Pending',
            3 => 'Suspended',
            default => 'Unknown'
        };
    }

    private function buildSearchText($model): string
    {
        $parts = [
            $model->name,
            $model->email,
            $model->first_name,
            $model->last_name,
            $model->phone,
        ];

        if ($model->relationLoaded('profile')) {
            $parts[] = $model->profile->bio;
            $parts[] = $model->profile->location;
        }

        if ($model->relationLoaded('roles')) {
            $parts = array_merge($parts, $model->roles->pluck('name')->toArray());
        }

        return implode(' ', array_filter($parts));
    }
}
