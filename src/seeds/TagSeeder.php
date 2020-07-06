<?php

use Haxibiao\Question\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 避免 getUser() Exception
        Auth::loginUsingId(1);
        $tagNames = ['随机答题', '答题PK', '审核题目'];
        foreach ($tagNames as $name) {
            $tag = \App\Tag::firstOrCreate([
                'tag_id'  => 10,
                'name'    => $name,
                'user_id' => 1,
                'rank'    => 0,
                'status'  => 1,
            ]);
            \App\Taggable::firstOrCreate([
                'tag_id'        => 10,
                'taggable_id'   => $tag->id,
                'taggable_type' => 'tags',
            ]);
        }
    }
}
