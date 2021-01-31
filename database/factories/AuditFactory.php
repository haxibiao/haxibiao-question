<?php

namespace Database\Factories;

use App\Audit;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditFactory extends Factory
{
    protected $model = Audit::class;
    public function definition()
    {
        return [
            'status'      => 1, //通过审题
            'question_id' => 1,
            'user_id'     => rand(1, 3),
        ];
    }
}
