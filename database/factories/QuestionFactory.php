<?php

namespace Database\Factories;

use App\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;
    public function definition()
    {
        return [
            'answer'      => 'A',
            'submit'      => 1,
            'rank'        => 1,
            'category_id' => 1,
            'user_id'     => rand(1, 3),
            'description' => '透过阳光，能将干燥的纸屑点燃的镜片是？',
            'selections'  => json_encode(['Selection' => [
                ['Text' => '选项1', 'Value' => 'A'],
                ['Text' => '选项2', 'Value' => 'B'],
                ['Text' => '选项3', 'Value' => 'C'],
            ]], JSON_UNESCAPED_UNICODE),
        ];
    }
}
