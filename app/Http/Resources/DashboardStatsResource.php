<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'totalStudents' => $this->resource['totalStudents'],
            'presentToday' => $this->resource['presentToday'],
            'lateToday' => $this->resource['lateToday'],
            'attendanceRate' => $this->resource['attendanceRate'],
        ];
    }
}
