<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return 
        [
            'team_name' => $this->name,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'owner' => $this->owner ? $this->owner->name : null,
            'lead' => $this->lead ? $this->lead->employee_name : null,
            'employees' => $this->employees->map(function ($employee) {
                return [
                    'employee_name' => $employee->employee_name,
                    'role' => $employee->pivot->role,
                    'assigned_at' => $employee->pivot->assigned_at,
                    'joined_at' => $employee->pivot->joined_at,
                    'left_at' => $employee->pivot->left_at,
                ];
            }),
            'services' => $this->services->map(function ($service) {
                return [
                    'service_name' => $service->name,
                    'service_slug' => $service->slug,
                    'department_name' => $service->department ? $service->department->name : null,
                ];
            }),
            'departments' => $this->departments->map(function ($department) {
                return [
                    'department_name' => $department->name, 
                    'department_slug' => $department->slug,
                ];
            }),

        ];
    }
}
